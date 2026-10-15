<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Lisbon');
ini_set('display_errors', '0');

function env_value(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('viano_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $https,
    'samesite' => 'Lax',
    'path' => '/',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env_value('VIANO_DB_HOST', '127.0.0.1');
    $port = env_value('VIANO_DB_PORT', '3306');
    $database = env_value('VIANO_DB_NAME', 'viano_db');
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

    $pdo = new PDO($dsn, env_value('VIANO_DB_USER', 'root'), env_value('VIANO_DB_PASSWORD', ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_input(): array
{
    $decoded = json_decode(file_get_contents('php://input'), true);
    if (!is_array($decoded)) {
        json_response(['error' => 'Pedido JSON inválido.'], 400);
    }
    return $decoded;
}

function require_method(string $method): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($method)) {
        header('Allow: ' . strtoupper($method));
        json_response(['error' => 'Método não permitido.'], 405);
    }
}

function require_role(string ...$roles): array
{
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user) || !in_array($user['perfil'] ?? '', $roles, true)) {
        json_response(['error' => 'Sessão inválida ou acesso não autorizado.'], 401);
    }
    return $user;
}

function require_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';
    if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
        json_response(['error' => 'Token de segurança inválido. Atualize a página.'], 403);
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function google_server_key(): string
{
    return env_value('GOOGLE_MAPS_SERVER_KEY', '') ?? '';
}

function google_browser_key(): string
{
    return env_value('GOOGLE_MAPS_BROWSER_KEY', '') ?? '';
}

function point_wkt(float $latitude, float $longitude): string
{
    return 'POINT(' . number_format($longitude, 7, '.', '') . ' ' . number_format($latitude, 7, '.', '') . ')';
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}