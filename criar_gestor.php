<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/config.php';

$email = 'gestor@viano.pt';
$check = db()->prepare('SELECT id FROM utilizadores WHERE email = :email LIMIT 1');
$check->execute(['email' => $email]);
if ($check->fetch()) {
    fwrite(STDERR, "Já existe uma conta com o email {$email}.\n");
    exit(1);
}

$password = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
$insert = db()->prepare(
    'INSERT INTO utilizadores (nome, email, password_hash, perfil) '
    . 'VALUES (:nome, :email, :password_hash, :perfil)'
);
$insert->execute([
    'nome' => 'Gestor Viano',
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'perfil' => 'gestor',
]);

echo "Conta de gestor criada. Guarde a password, pois não será apresentada novamente.\n";
echo "Email: {$email}\n";
echo "Password: {$password}\n";