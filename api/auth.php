<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $user = $_SESSION['user'] ?? null;
    json_response(['user' => $user, 'csrf' => csrf_token()]);
}

require_method('POST');
$input = json_input();
$action = $input['action'] ?? 'login';

if ($action === 'login') {
    $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';
    if (!$email || !is_string($password) || $password === '' || strlen($password) > 4096) {
        json_response(['error' => 'Email ou palavra-passe inválidos.'], 422);
    }

    $statement = db()->prepare('SELECT id, nome, email, password_hash, perfil FROM utilizadores WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['error' => 'Credenciais inválidas.'], 401);
    }

    session_regenerate_id(true);
    unset($user['password_hash']);
    $_SESSION['user'] = $user;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    json_response(['user' => $user, 'csrf' => csrf_token()]);
}

if ($action === 'logout') {
    require_csrf();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    json_response(['ok' => true]);
}

json_response(['error' => 'Ação desconhecida.'], 400);