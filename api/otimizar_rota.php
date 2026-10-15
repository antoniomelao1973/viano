<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

require_method('POST');
require_csrf();
$manager = require_role('gestor');
$input = json_input();
$driverId = filter_var($input['entregador_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$deliveryIds = $input['entrega_ids'] ?? null;
if (!$driverId || !is_array($deliveryIds) || count($deliveryIds) < 1 || count($deliveryIds) > 24) {
    json_response(['error' => 'Indique um estafeta e entre 1 e 24 entregas.'], 422);
}

$ids = [];
foreach ($deliveryIds as $id) {
    $validated = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$validated) {
        json_response(['error' => 'A lista contém um identificador inválido.'], 422);
    }
    $ids[] = (int) $validated;
}
if (count(array_unique($ids)) !== count($ids)) {
    json_response(['error' => 'A lista contém entregas repetidas.'], 422);
}

$pdo = db();
$driverQuery = $pdo->prepare("SELECT u.id, ep.localizacao_inicial_lat AS latitude, ep.localizacao_inicial_lng AS longitude, ep.estado_disponibilidade FROM utilizadores u JOIN entregadores_perfil ep ON ep.utilizador_id = u.id WHERE u.id = :id AND u.perfil = 'entregador'");
$driverQuery->execute(['id' => $driverId]);
$driver = $driverQuery->fetch();
if (!$driver || $driver['estado_disponibilidade'] !== 'disponivel') {
    json_response(['error' => 'O estafeta não está disponível.'], 409);
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$deliveryQuery = $pdo->prepare("SELECT id, cliente_nome, morada_destino, latitude, longitude, estado FROM entregas WHERE id IN ({$placeholders})");
$deliveryQuery->execute($ids);
$deliveriesById = [];
foreach ($deliveryQuery->fetchAll() as $delivery) {
    $deliveriesById[(int) $delivery['id']] = $delivery;
}
if (count($deliveriesById) !== count($ids)) {
    json_response(['error' => 'Uma ou mais entregas não existem.'], 404);
}
foreach ($ids as $id) {
    if ($deliveriesById[$id]['estado'] !== 'pendente') {
        json_response(['error' => 'Todas as entregas têm de estar pendentes.'], 409);
    }
}

$activeRoute = $pdo->prepare("SELECT id FROM rotas WHERE entregador_id = :driver AND estado IN ('planeada', 'em_curso') LIMIT 1");
$activeRoute->execute(['driver' => $driverId]);
if ($activeRoute->fetch()) {
    json_response(['error' => 'O estafeta já tem uma rota ativa.'], 409);
}

$key = google_server_key();
if ($key === '') {
    json_response(['error' => 'Configure GOOGLE_MAPS_SERVER_KEY no servidor.'], 503);
}
$coordinates = [[(float) $driver['latitude'], (float) $driver['longitude']]];
foreach ($ids as $id) {
    $coordinates[] = [(float) $deliveriesById[$id]['latitude'], (float) $deliveriesById[$id]['longitude']];
}
$waypoint = static fn (array $point): array => ['waypoint' => ['location' => ['latLng' => ['latitude' => $point[0], 'longitude' => $point[1]]]]];
$requestBody = [
    'origins' => array_map($waypoint, $coordinates),
    'destinations' => array_map($waypoint, array_slice($coordinates, 1)),
    'travelMode' => 'DRIVE',
    'routingPreference' => 'TRAFFIC_AWARE',
    'languageCode' => 'pt-PT',
    'units' => 'METRIC',
];
$handle = curl_init('https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix');
curl_setopt_array($handle, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestBody),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-Goog-Api-Key: ' . $key,
        'X-Goog-FieldMask: originIndex,destinationIndex,duration,distanceMeters,condition,status',
    ],
]);
$responseBody = curl_exec($handle);
$httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
$curlError = curl_error($handle);
curl_close($handle);
if (!is_string($responseBody) || $httpStatus < 200 || $httpStatus >= 300) {
    error_log('Google Routes API: ' . $curlError . ' HTTP ' . $httpStatus);
    json_response(['error' => 'Não foi possível calcular a matriz de distâncias. Verifique a chave e as APIs Google.'], 502);
}

$matrixRows = json_decode($responseBody, true);
if (!is_array($matrixRows)) {
    $matrixRows = [];
    foreach (preg_split('/\R/', trim($responseBody)) as $line) {
        $row = json_decode($line, true);
        if (is_array($row)) {
            $matrixRows[] = $row;
        }
    }
}
$matrix = [];
foreach ($matrixRows as $row) {
    if (!is_array($row) || ($row['condition'] ?? '') !== 'ROUTE_EXISTS') {
        continue;
    }
    $originIndex = (int) ($row['originIndex'] ?? -1);
    $destinationIndex = (int) ($row['destinationIndex'] ?? -1);
    $durationText = (string) ($row['duration'] ?? '0s');
    $seconds = preg_match('/^(\d+(?:\.\d+)?)s$/', $durationText, $matches) ? (int) ceil((float) $matches[1]) : 0;
    if ($originIndex >= 0 && $originIndex <= count($ids) && $destinationIndex >= 0 && $destinationIndex < count($ids)) {
        $matrix[$originIndex][$destinationIndex] = [
            'seconds' => $seconds,
            'meters' => (int) ($row['distanceMeters'] ?? 0),
        ];
    }
}

$remaining = range(0, count($ids) - 1);
$order = [];
$current = 0;
while ($remaining !== []) {
    $best = null;
    foreach ($remaining as $candidate) {
        if (isset($matrix[$current][$candidate]) && ($best === null || $matrix[$current][$candidate]['seconds'] < $matrix[$current][$best]['seconds'])) {
            $best = $candidate;
        }
    }
    if ($best === null) {
        json_response(['error' => 'A Google Maps não encontrou um percurso válido para todas as entregas.'], 422);
    }
    $order[] = $best;
    $remaining = array_values(array_diff($remaining, [$best]));
    $current = $best + 1;
}

$routeCost = static function (array $sequence) use ($matrix): ?int {
    $total = 0;
    $origin = 0;
    foreach ($sequence as $stop) {
        if (!isset($matrix[$origin][$stop])) {
            return null;
        }
        $total += $matrix[$origin][$stop]['seconds'];
        $origin = $stop + 1;
    }
    return $total;
};
for ($pass = 0; $pass < count($order); $pass++) {
    $improved = false;
    for ($start = 0; $start < count($order) - 1; $start++) {
        for ($end = $start + 1; $end < count($order); $end++) {
            $candidateOrder = $order;
            $candidateOrder = array_merge(array_slice($order, 0, $start), array_reverse(array_slice($order, $start, $end - $start + 1)), array_slice($order, $end + 1));
            $candidateCost = $routeCost($candidateOrder);
            if ($candidateCost !== null && $candidateCost < $routeCost($order)) {
                $order = $candidateOrder;
                $improved = true;
            }
        }
    }
    if (!$improved) {
        break;
    }
}

$stopRows = [];
$totalSeconds = 0;
$totalMeters = 0;
$origin = 0;
foreach ($order as $sequence => $stopIndex) {
    $leg = $matrix[$origin][$stopIndex];
    $totalSeconds += $leg['seconds'];
    $totalMeters += $leg['meters'];
    $stopRows[] = ['entrega_id' => $ids[$stopIndex], 'ordem' => $sequence + 1, 'eta' => date('Y-m-d H:i:s', time() + $totalSeconds)];
    $origin = $stopIndex + 1;
}

$pdo->beginTransaction();
try {
    $lockDriver = $pdo->prepare("SELECT estado_disponibilidade FROM entregadores_perfil WHERE utilizador_id = :id FOR UPDATE");
    $lockDriver->execute(['id' => $driverId]);
    if ($lockDriver->fetchColumn() !== 'disponivel') {
        throw new DomainException('O estafeta deixou de estar disponível.');
    }
    $lockDeliveries = $pdo->prepare("SELECT id, estado FROM entregas WHERE id IN ({$placeholders}) FOR UPDATE");
    $lockDeliveries->execute($ids);
    $locked = $lockDeliveries->fetchAll();
    if (count($locked) !== count($ids)) {
        throw new DomainException('Uma ou mais entregas já não existem.');
    }
    foreach ($locked as $delivery) {
        if ($delivery['estado'] !== 'pendente') {
            throw new DomainException('Uma ou mais entregas já foram atribuídas.');
        }
    }
    $activeRoute->execute(['driver' => $driverId]);
    if ($activeRoute->fetch()) {
        throw new DomainException('O estafeta já tem uma rota ativa.');
    }

    $insertRoute = $pdo->prepare("INSERT INTO rotas (gestor_id, entregador_id, data_rota, distancia_total_km, tempo_estimado_min) VALUES (:manager, :driver, :route_date, :distance, :duration)");
    $insertRoute->execute([
        'manager' => $manager['id'], 'driver' => $driverId,
        'route_date' => date('Y-m-d'),
        'distance' => round($totalMeters / 1000, 2), 'duration' => (int) ceil($totalSeconds / 60),
    ]);
    $routeId = (int) $pdo->lastInsertId();
    $insertStop = $pdo->prepare('INSERT INTO rota_paragens (rota_id, entrega_id, ordem_sequencia, hora_prevista) VALUES (:route, :delivery, :sequence, :eta)');
    $updateDelivery = $pdo->prepare("UPDATE entregas SET estado = 'atribuida' WHERE id = :id AND estado = 'pendente'");
    foreach ($stopRows as $stop) {
        $insertStop->execute(['route' => $routeId, 'delivery' => $stop['entrega_id'], 'sequence' => $stop['ordem'], 'eta' => $stop['eta']]);
        $updateDelivery->execute(['id' => $stop['entrega_id']]);
        if ($updateDelivery->rowCount() !== 1) {
            throw new DomainException('Uma entrega já foi atribuída a outra rota.');
        }
    }
    $updateDriver = $pdo->prepare("UPDATE entregadores_perfil SET estado_disponibilidade = 'em_rota' WHERE utilizador_id = :id");
    $updateDriver->execute(['id' => $driverId]);
    $pdo->commit();
    json_response(['rota_id' => $routeId, 'ordem_entregas' => array_column($stopRows, 'entrega_id'), 'distancia_total_km' => round($totalMeters / 1000, 2), 'tempo_estimado_min' => (int) ceil($totalSeconds / 60)]);
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
    json_response(['error' => 'Não foi possível gravar a rota.'], 500);
}