<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/google.php';

$user = require_role('gestor');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $rows = db()->query("SELECT id, cliente_nome, morada_destino, latitude, longitude, telefone_contacto, notas, estado, created_at FROM entregas ORDER BY created_at DESC LIMIT 250")->fetchAll();
    json_response(['entregas' => $rows]);
}

require_method('POST');
require_csrf();
$input = json_input();
$items = $input['items'] ?? [$input];
if (!is_array($items) || count($items) < 1 || count($items) > 50) {
    json_response(['error' => 'Envie entre 1 e 50 entregas por pedido.'], 422);
}

$prepared = [];
$errors = [];
foreach ($items as $index => $item) {
    if (!is_array($item)) {
        $errors[] = ['linha' => $index + 1, 'erro' => 'Registo inválido.'];
        continue;
    }
    $name = trim((string) ($item['cliente_nome'] ?? ''));
    $address = trim((string) ($item['morada_destino'] ?? ''));
    $phone = trim((string) ($item['telefone_contacto'] ?? ''));
    $notes = trim((string) ($item['notas'] ?? ''));
    if ($name === '' || strlen($name) > 120 || $address === '' || strlen($address) > 500 || strlen($phone) > 32 || strlen($notes) > 5000) {
        $errors[] = ['linha' => $index + 1, 'erro' => 'Nome, morada ou dimensões dos campos inválidos.'];
        continue;
    }
    try {
        $coordinates = geocode_address($address);
        $prepared[] = compact('name', 'address', 'phone', 'notes') + $coordinates;
    } catch (Throwable $exception) {
        $errors[] = ['linha' => $index + 1, 'erro' => $exception->getMessage()];
    }
}

if ($prepared === []) {
    json_response(['criados' => [], 'erros' => $errors], 422);
}

$pdo = db();
$pdo->beginTransaction();
try {
    $insert = $pdo->prepare("INSERT INTO entregas (cliente_nome, morada_destino, latitude, longitude, localizacao, telefone_contacto, notas) VALUES (:name, :address, :latitude, :longitude, ST_PointFromText(:point), :phone, :notes)");
    $created = [];
    foreach ($prepared as $item) {
        $insert->execute([
            'name' => $item['name'], 'address' => $item['address'],
            'latitude' => $item['latitude'], 'longitude' => $item['longitude'],
            'point' => point_wkt($item['latitude'], $item['longitude']), 'phone' => $item['phone'] ?: null, 'notes' => $item['notes'] ?: null,
        ]);
        $created[] = (int) $pdo->lastInsertId();
    }
    $pdo->commit();
    json_response(['criados' => $created, 'erros' => $errors], 201);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    json_response(['error' => 'Não foi possível guardar as entregas.'], 500);
}