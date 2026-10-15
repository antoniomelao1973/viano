<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_method('GET');
$user = require_role('gestor', 'entregador');
$pdo = db();

$routeQuery = $pdo->prepare("SELECT r.id, r.entregador_id, r.data_rota, r.distancia_total_km, r.tempo_estimado_min, r.estado, u.nome AS entregador_nome FROM rotas r JOIN utilizadores u ON u.id = r.entregador_id WHERE r.estado IN ('planeada', 'em_curso') AND (:is_manager = 1 OR r.entregador_id = :driver_id) ORDER BY r.created_at DESC");
$isManager = $user['perfil'] === 'gestor';
$routeQuery->execute(['is_manager' => $isManager ? 1 : 0, 'driver_id' => $user['id']]);
$routes = $routeQuery->fetchAll();

$routeIds = array_map(static fn (array $route): int => (int) $route['id'], $routes);
$stopsByRoute = [];
if ($routeIds !== []) {
    $placeholders = implode(',', array_fill(0, count($routeIds), '?'));
    $stopQuery = $pdo->prepare("SELECT rp.rota_id, rp.entrega_id, rp.ordem_sequencia, rp.hora_prevista, rp.hora_chegada, rp.hora_conclusao, e.cliente_nome, e.morada_destino, e.latitude, e.longitude, e.telefone_contacto, e.notas, e.estado AS entrega_estado FROM rota_paragens rp JOIN entregas e ON e.id = rp.entrega_id WHERE rp.rota_id IN ({$placeholders}) ORDER BY rp.rota_id, rp.ordem_sequencia");
    $stopQuery->execute($routeIds);
    foreach ($stopQuery->fetchAll() as $stop) {
        $stopsByRoute[(int) $stop['rota_id']][] = $stop;
    }
}
foreach ($routes as &$route) {
    $route['paragens'] = $stopsByRoute[(int) $route['id']] ?? [];
    $done = count(array_filter($route['paragens'], static fn (array $stop): bool => in_array($stop['entrega_estado'], ['entregue', 'falhada'], true)));
    $route['total_paragens'] = count($route['paragens']);
    $route['concluidas'] = $done;
    $route['percentagem'] = $route['total_paragens'] > 0 ? (int) round($done / $route['total_paragens'] * 100) : 0;
}
unset($route);

if (!$isManager) {
    json_response(['rota' => $routes[0] ?? null]);
}

$drivers = $pdo->query("SELECT u.id, u.nome, ep.estado_disponibilidade, loc.latitude, loc.longitude, loc.atualizado_em FROM utilizadores u JOIN entregadores_perfil ep ON ep.utilizador_id = u.id LEFT JOIN entregadores_localizacoes loc ON loc.entregador_id = u.id AND ep.estado_disponibilidade = 'em_rota' AND loc.atualizado_em >= NOW() - INTERVAL 10 MINUTE WHERE u.perfil = 'entregador' ORDER BY u.nome")->fetchAll();
$deliveries = $pdo->query("SELECT id, cliente_nome, morada_destino, latitude, longitude, telefone_contacto, estado, created_at FROM entregas WHERE estado = 'pendente' ORDER BY created_at ASC LIMIT 250")->fetchAll();
json_response(['rotas' => $routes, 'entregadores' => $drivers, 'entregas_pendentes' => $deliveries]);