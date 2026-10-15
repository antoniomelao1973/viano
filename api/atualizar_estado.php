<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_method('POST');
require_csrf();
$driver = require_role('entregador');
$input = json_input();
$deliveryId = filter_var($input['entrega_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$action = $input['acao'] ?? '';
if (!$deliveryId || !in_array($action, ['cheguei', 'entregue', 'falhada'], true)) {
    json_response(['error' => 'Ação ou entrega inválida.'], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $routeQuery = $pdo->prepare("SELECT id FROM rotas WHERE entregador_id = :driver AND estado IN ('planeada', 'em_curso') ORDER BY created_at DESC LIMIT 1 FOR UPDATE");
    $routeQuery->execute(['driver' => $driver['id']]);
    $routeId = $routeQuery->fetchColumn();
    if (!$routeId) {
        throw new DomainException('Não existe uma rota ativa.');
    }

    $stopQuery = $pdo->prepare("SELECT rp.id, rp.entrega_id, rp.hora_chegada, e.estado FROM rota_paragens rp JOIN entregas e ON e.id = rp.entrega_id WHERE rp.rota_id = :route AND e.estado NOT IN ('entregue', 'falhada') ORDER BY rp.ordem_sequencia LIMIT 1 FOR UPDATE");
    $stopQuery->execute(['route' => $routeId]);
    $stop = $stopQuery->fetch();
    if (!$stop || (int) $stop['entrega_id'] !== (int) $deliveryId) {
        throw new DomainException('Atualize apenas a próxima paragem da rota.');
    }

    if ($action === 'cheguei') {
        $updateStop = $pdo->prepare('UPDATE rota_paragens SET hora_chegada = COALESCE(hora_chegada, NOW()) WHERE id = :id');
        $updateStop->execute(['id' => $stop['id']]);
        $pdo->prepare("UPDATE entregas SET estado = 'em_transito' WHERE id = :id AND estado = 'atribuida'")->execute(['id' => $deliveryId]);
        $pdo->prepare("UPDATE rotas SET estado = 'em_curso' WHERE id = :id AND estado = 'planeada'")->execute(['id' => $routeId]);
    } else {
        if (!$stop['hora_chegada']) {
            throw new DomainException('Registe primeiro a chegada à paragem.');
        }
        $status = $action === 'entregue' ? 'entregue' : 'falhada';
        $updateStop = $pdo->prepare('UPDATE rota_paragens SET hora_conclusao = NOW() WHERE id = :id');
        $updateStop->execute(['id' => $stop['id']]);
        $pdo->prepare('UPDATE entregas SET estado = :status WHERE id = :id')->execute(['status' => $status, 'id' => $deliveryId]);

        $unfinished = $pdo->prepare("SELECT COUNT(*) FROM rota_paragens rp JOIN entregas e ON e.id = rp.entrega_id WHERE rp.rota_id = :route AND e.estado NOT IN ('entregue', 'falhada')");
        $unfinished->execute(['route' => $routeId]);
        if ((int) $unfinished->fetchColumn() === 0) {
            $pdo->prepare("UPDATE rotas SET estado = 'concluida' WHERE id = :id")->execute(['id' => $routeId]);
            $pdo->prepare("UPDATE entregadores_perfil SET estado_disponibilidade = 'disponivel' WHERE utilizador_id = :id")->execute(['id' => $driver['id']]);
        }
    }

    $pdo->commit();
    json_response(['ok' => true]);
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['error' => $exception->getMessage()], 409);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    json_response(['error' => 'Não foi possível atualizar o estado.'], 500);
}