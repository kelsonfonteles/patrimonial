<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

startSession();

if (empty($_SESSION['user_id'])) jsonResponse(['error' => 'Não autenticado'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['error' => 'Method not allowed'], 405);

$data  = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int)($data['id'] ?? 0);
$setor = strtoupper(trim($data['setor_usuario'] ?? ''));
$chip  = $data['tem_chip_fisico'] ?? null;

if (!$id)    jsonResponse(['error' => 'ID inválido'], 400);
if (!$setor) jsonResponse(['error' => 'Setor ou Usuário é obrigatório'], 400);

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM linhas WHERE id = ?");
$stmt->execute([$id]);
$linha = $stmt->fetch();

if (!$linha) jsonResponse(['error' => 'Registro não encontrado'], 404);

$isSuperAdmin = isSuperAdmin();

if (!$isSuperAdmin && (int)$linha['filial_id'] !== (int)$_SESSION['filial_id']) {
    jsonResponse(['error' => 'Sem permissão'], 403);
}

if (!$isSuperAdmin && !empty($linha['setor_usuario'])) {
    jsonResponse(['error' => 'Registro já identificado e bloqueado'], 403);
}

$chipValue = null;
if ($chip === 1 || $chip === '1') $chipValue = 1;
elseif ($chip === 0 || $chip === '0') $chipValue = 0;

$stmt = $db->prepare("
    UPDATE linhas
    SET setor_usuario = ?, tem_chip_fisico = ?, identificado_por = ?, identificado_em = NOW()
    WHERE id = ?
");
$stmt->execute([$setor, $chipValue, $_SESSION['user_id'], $id]);

jsonResponse(['success' => true, 'setor_usuario' => $setor, 'tem_chip_fisico' => $chipValue]);
