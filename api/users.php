<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSession();
if (!isSuperAdmin()) jsonResponse(['error' => 'Acesso negado'], 403);

$db     = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'filiais') {
        $rows = $db->query("SELECT id, nome FROM filiais ORDER BY nome")->fetchAll();
        jsonResponse(['filiais' => $rows]);
    }
    $rows = $db->query("
        SELECT u.id, u.username, u.nome, u.role, u.ativo, u.created_at,
               f.nome AS filial_nome, u.filial_id
        FROM users u
        LEFT JOIN filiais f ON f.id = u.filial_id
        ORDER BY u.role DESC, u.nome
    ")->fetchAll();
    jsonResponse(['users' => $rows]);
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST') {
    $username  = trim($data['username'] ?? '');
    $nome      = trim($data['nome'] ?? '');
    $password  = $data['password'] ?? '';
    $filial_id = !empty($data['filial_id']) ? (int)$data['filial_id'] : null;
    $role      = in_array($data['role'] ?? '', ['superadmin', 'gestor']) ? $data['role'] : 'gestor';

    if (!$username || !$password) jsonResponse(['error' => 'Username e senha obrigatórios'], 400);

    try {
        $stmt = $db->prepare("INSERT INTO users (username, nome, password, filial_id, role) VALUES (?,?,?,?,?)");
        $stmt->execute([$username, $nome, password_hash($password, PASSWORD_DEFAULT), $filial_id, $role]);
        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()]);
    } catch (PDOException $e) {
        jsonResponse(['error' => 'Username já existe'], 409);
    }
}

if ($method === 'PUT') {
    $id        = (int)($data['id'] ?? 0);
    $nome      = trim($data['nome'] ?? '');
    $filial_id = !empty($data['filial_id']) ? (int)$data['filial_id'] : null;
    $role      = in_array($data['role'] ?? '', ['superadmin', 'gestor']) ? $data['role'] : 'gestor';
    $ativo     = isset($data['ativo']) ? (int)(bool)$data['ativo'] : 1;

    if (!$id) jsonResponse(['error' => 'ID inválido'], 400);

    if (!empty($data['password'])) {
        $stmt = $db->prepare("UPDATE users SET nome=?, filial_id=?, role=?, ativo=?, password=? WHERE id=?");
        $stmt->execute([$nome, $filial_id, $role, $ativo, password_hash($data['password'], PASSWORD_DEFAULT), $id]);
    } else {
        $stmt = $db->prepare("UPDATE users SET nome=?, filial_id=?, role=?, ativo=? WHERE id=?");
        $stmt->execute([$nome, $filial_id, $role, $ativo, $id]);
    }
    jsonResponse(['success' => true]);
}

if ($method === 'DELETE') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'ID inválido'], 400);
    $db->prepare("UPDATE users SET ativo=0 WHERE id=?")->execute([$id]);
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Method not allowed'], 405);
