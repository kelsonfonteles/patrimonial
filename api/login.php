<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSession();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';

if (!$username || !$password) {
    jsonResponse(['error' => 'Usuário e senha são obrigatórios'], 400);
}

$db   = getDB();
$stmt = $db->prepare("
    SELECT u.*, f.nome AS filial_nome
    FROM users u
    LEFT JOIN filiais f ON f.id = u.filial_id
    WHERE u.username = ? AND u.ativo = 1
");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(['error' => 'Usuário ou senha inválidos'], 401);
}

$_SESSION['user_id']    = $user['id'];
$_SESSION['username']   = $user['username'];
$_SESSION['nome']       = $user['nome'] ?: $user['username'];
$_SESSION['role']       = $user['role'];
$_SESSION['filial_id']  = $user['filial_id'];
$_SESSION['filial_nome'] = $user['filial_nome'];

jsonResponse(['success' => true, 'redirect' => APP_URL . '/dashboard.php']);
