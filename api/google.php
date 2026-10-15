<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

function google_get(string $url): array
{
    $key = google_server_key();
    if ($key === '') {
        json_response(['error' => 'Configure GOOGLE_MAPS_SERVER_KEY no servidor.'], 503);
    }
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['X-Goog-Api-Key: ' . $key],
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    $data = is_string($body) ? json_decode($body, true) : null;
    if ($status < 200 || $status >= 300 || !is_array($data)) {
        throw new RuntimeException('Falha ao contactar o serviço de geocodificação.');
    }
    return $data;
}

function geocode_address(string $address): array
{
    $url = 'https://geocode.googleapis.com/v4/geocode/address/' . rawurlencode($address);
    $data = google_get($url);
    $location = $data['location'] ?? null;
    if (!is_array($location) || !isset($location['latitude'], $location['longitude'])) {
        throw new RuntimeException('Não foi possível localizar a morada fornecida.');
    }
    $latitude = (float) $location['latitude'];
    $longitude = (float) $location['longitude'];
    if (abs($latitude) > 90 || abs($longitude) > 180) {
        throw new RuntimeException('A morada devolveu coordenadas inválidas.');
    }
    return ['latitude' => $latitude, 'longitude' => $longitude];
}