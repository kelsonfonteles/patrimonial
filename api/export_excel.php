<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';

requireLogin();

$user       = currentUser();
$db         = getDB();
$superAdmin = isSuperAdmin();

$filialFilter = $superAdmin ? (int)($_GET['filial'] ?? 0) : (int)$user['filial_id'];

if ($superAdmin && !$filialFilter) {
    $linhas = $db->query("
        SELECT l.*, f.nome AS filial_nome, u.nome AS id_por
        FROM linhas l
        JOIN filiais f ON f.id = l.filial_id
        LEFT JOIN users u ON u.id = l.identificado_por
        ORDER BY f.nome, l.telefone
    ")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $db->prepare("
        SELECT l.*, f.nome AS filial_nome, u.nome AS id_por
        FROM linhas l
        JOIN filiais f ON f.id = l.filial_id
        LEFT JOIN users u ON u.id = l.identificado_por
        WHERE l.filial_id = ?
        ORDER BY l.telefone
    ");
    $stmt->execute([$filialFilter ?: $user['filial_id']]);
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$filename = 'chips_e_linhas_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');

$out = fopen('php://output', 'w');

// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Filial', 'Número', 'Plano', 'Setor / Usuário', 'Chip Físico', 'Status', 'Identificado Por'], ';');

foreach ($linhas as $l) {
    $chip   = $l['tem_chip_fisico'];
    $chipTxt = ($chip === '1' || $chip === 1) ? 'Sim' : (($chip === '0' || $chip === 0) ? 'Não' : 'Não informado');
    $status  = !empty($l['setor_usuario']) ? 'Identificado' : 'Pendente';

    fputcsv($out, [
        $l['filial_nome'] ?? '',
        $l['telefone']    ?? '',
        $l['parametro']   ?? '',
        $l['setor_usuario'] ?? '',
        $chipTxt,
        $status,
        $l['id_por'] ?? '',
    ], ';');
}

fclose($out);
exit;
