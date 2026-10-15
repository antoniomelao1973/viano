<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_method('POST');
require_csrf();
$driver = require_role('entregador');
$input = json_input();
$latitude = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
if ($latitude === false || $longitude === false || abs((float) $latitude) > 90 || abs((float) $longitude) > 180) {
    json_response(['error' => 'Coordenadas inválidas.'], 422);
}

$pdo = db();
$active = $pdo->prepare("SELECT id FROM rotas WHERE entregador_id = :id AND estado IN ('planeada', 'em_curso') LIMIT 1");
$active->execute(['id' => $driver['id']]);
if (!$active->fetchColumn()) {
    json_response(['error' => 'A localização só é aceite durante uma rota ativa.'], 409);
}
$statement = $pdo->prepare('INSERT INTO entregadores_localizacoes (entregador_id, latitude, longitude, localizacao) VALUES (:id, :latitude, :longitude, ST_PointFromText(:point)) ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), localizacao = VALUES(localizacao), atualizado_em = CURRENT_TIMESTAMP');
$statement->execute([
    'id' => $driver['id'], 'latitude' => $latitude, 'longitude' => $longitude,
    'point' => point_wkt((float) $latitude, (float) $longitude),
]);
json_response(['ok' => true]);