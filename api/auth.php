<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $user = $_SESSION['user'] ?? null;
    $managers = db()->query("SELECT COUNT(*) FROM utilizadores WHERE perfil = 'gestor'")->fetchColumn();
    json_response(['user' => $user, 'csrf' => csrf_token(), 'managerSetupNeeded' => (int) $managers === 0]);
}

require_method('POST');
$input = json_input();
$action = $input['action'] ?? 'login';

if ($action === 'setup_manager') {
    require_csrf();

    $name = trim((string) ($input['nome'] ?? ''));
    $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $password = $input['password'] ?? '';
    if ($name === '' || strlen($name) > 120 || !$email || strlen($email) > 190
        || !is_string($password) || strlen($password) < 12 || strlen($password) > 4096) {
        json_response(['error' => 'Indique um nome, email válido e palavra-passe com pelo menos 12 caracteres.'], 422);
    }

    $database = db();
    if ((int) $database->query("SELECT GET_LOCK('viano_first_manager_setup', 10)")->fetchColumn() !== 1) {
        json_response(['error' => 'Não foi possível iniciar o registo. Tente novamente.'], 503);
    }

    try {
        $managers = $database->query("SELECT COUNT(*) FROM utilizadores WHERE perfil = 'gestor'")->fetchColumn();
        if ((int) $managers > 0) {
            json_response(['error' => 'O registo inicial já foi concluído.'], 409);
        }

        $insert = $database->prepare(
            "INSERT INTO utilizadores (nome, email, password_hash, perfil) VALUES (:nome, :email, :password_hash, 'gestor')"
        );
        $insert->execute([
            'nome' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        ]);

        $user = [
            'id' => (int) $database->lastInsertId(),
            'nome' => $name,
            'email' => $email,
            'perfil' => 'gestor',
        ];
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        json_response(['user' => $user, 'csrf' => csrf_token()]);
    } finally {
        $database->query("SELECT RELEASE_LOCK('viano_first_manager_setup')");
    }
}

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