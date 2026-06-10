<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();
$user       = currentUser();
$db         = getDB();
$superAdmin = isSuperAdmin();

// Filial filter
$filialFilter = $superAdmin ? (int)($_GET['filial'] ?? 0) : (int)$user['filial_id'];

// Load linhas
if ($superAdmin && !$filialFilter) {
    $linhas = $db->query("
        SELECT l.*, f.nome AS filial_nome, u.nome AS id_por
        FROM linhas l
        JOIN filiais f ON f.id = l.filial_id
        LEFT JOIN users u ON u.id = l.identificado_por
        ORDER BY f.nome, l.telefone
    ")->fetchAll();
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
    $linhas = $stmt->fetchAll();
}

// Stats
$total        = count($linhas);
$identificados = count(array_filter($linhas, fn($l) => !empty($l['setor_usuario'])));
$pendentes    = $total - $identificados;
$comChip      = count(array_filter($linhas, fn($l) => $l['tem_chip_fisico'] === '1'));
$progPct      = $total > 0 ? round($identificados / $total * 100) : 0;

// Filiais list for filter
$filiais = [];
if ($superAdmin) {
    $filiais = $db->query("SELECT id, nome FROM filiais ORDER BY nome")->fetchAll();
}

// Current filial label
$filialLabel = '';
if (!$superAdmin) {
    $filialLabel = $user['filial_nome'] ?? 'Sem filial';
} elseif ($filialFilter && $filiais) {
    foreach ($filiais as $f) { if ($f['id'] == $filialFilter) { $filialLabel = $f['nome']; break; } }
} else {
    $filialLabel = 'Todas as filiais';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chips e Linha</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{jb:{950:'#02081a',900:'#050f1e',800:'#091627',700:'#0f2038'}}}}}</script>
    <style>
        body { background:#02081a; font-family:'Segoe UI',system-ui,sans-serif; color:#fff; }
        .hdr { background:#050f1e; border-bottom:1px solid #1a2e45; }
        .card { background:#091627; border:1px solid #1a2e45; border-radius:.75rem; }
        .inp-setor {
            background:#0f1f30; border:1px solid #1e3248; color:#fff;
            padding:.5rem .75rem; border-radius:.5rem; font-size:.875rem;
            text-transform:uppercase; width:100%;
        }
        .inp-setor:focus { outline:none; border-color:#22c55e; }
        .inp-setor::placeholder { color:#3d5468; text-transform:none; }
        .inp-setor:disabled { opacity:.5; cursor:not-allowed; }
        .chip-btn { padding:.25rem .6rem; border-radius:.375rem; font-size:.75rem; font-weight:600; border:1px solid; cursor:pointer; transition:all .15s; }
        .chip-btn.active-sim  { background:#14532d; border-color:#16a34a; color:#4ade80; }
        .chip-btn.active-nao  { background:#450a0a; border-color:#b91c1c; color:#f87171; }
        .chip-btn.active-dun  { background:#1f2937; border-color:#4b5563; color:#9ca3af; }
        .chip-btn.idle { background:transparent; border-color:#374151; color:#6b7280; }
        .chip-btn.idle:hover { border-color:#6b7280; color:#d1d5db; }
        .btn-save { background:#22c55e; color:#000; font-weight:700; padding:.4rem 1rem; border-radius:.5rem; font-size:.8rem; cursor:pointer; transition:background .15s; }
        .btn-save:hover { background:#16a34a; }
        .btn-save:disabled { background:#1a3a1a; color:#4b5563; cursor:not-allowed; }
        .btn-edit { background:transparent; border:1px solid #2563eb; color:#60a5fa; padding:.3rem .6rem; border-radius:.375rem; font-size:.75rem; cursor:pointer; }
        .btn-edit:hover { background:#1d3a6e; }
        .locked-badge { display:flex; align-items:center; gap:.375rem; color:#4ade80; font-size:.8rem; }
        .tbl th { background:#050f1e; padding:.625rem 1rem; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#4b6280; white-space:nowrap; }
        .tbl td { padding:.625rem 1rem; border-bottom:1px solid #0f1f30; font-size:.85rem; vertical-align:middle; }
        .tbl tr:last-child td { border-bottom:none; }
        .tbl tr:hover td { background:#0a1828; }
        .badge-plan { background:#0f2038; color:#60a5fa; border:1px solid #1e3a6e; padding:.15rem .5rem; border-radius:.3rem; font-size:.7rem; white-space:nowrap; }
        .filial-tag { background:#1a1a2e; color:#a78bfa; border:1px solid #3730a3; padding:.15rem .5rem; border-radius:.3rem; font-size:.7rem; }
        .stat-card { background:#091627; border:1px solid #1a2e45; border-radius:.75rem; padding:1rem 1.25rem; }
        .progress-bar { background:#1a2e45; border-radius:999px; height:8px; overflow:hidden; }
        .progress-fill { background:linear-gradient(90deg,#16a34a,#22c55e); height:100%; border-radius:999px; transition:width .5s ease; }
        select.filter-sel { background:#091627; border:1px solid #1e3248; color:#fff; padding:.5rem .75rem; border-radius:.5rem; font-size:.875rem; }
        select.filter-sel:focus { outline:none; border-color:#3b82f6; }
        .search-wrap { position:relative; }
        .search-inp { background:#091627; border:1px solid #1a2e45; color:#fff; padding:.625rem 2.5rem .625rem 2.75rem; border-radius:.625rem; font-size:.875rem; width:100%; transition:border-color .2s; }
        .search-inp:focus { outline:none; border-color:#3b82f6; }
        .search-inp::placeholder { color:#2d4558; }
        .search-icon { position:absolute; left:.75rem; top:50%; transform:translateY(-50%); color:#2d4558; pointer-events:none; }
        .search-clear { position:absolute; right:.75rem; top:50%; transform:translateY(-50%); background:none; border:none; color:#4b6280; cursor:pointer; font-size:1rem; line-height:1; padding:.1rem; }
        .search-clear:hover { color:#fff; }
        .search-count { font-size:.72rem; color:#4b6280; margin-top:.375rem; }
    </style>
</head>
<body class="min-h-screen">

<!-- HEADER -->
<header class="hdr px-4 py-3 sticky top-0 z-30">
    <div class="flex items-center justify-between max-w-7xl mx-auto">
        <div class="flex items-center gap-3">
            <img src="<?= APP_URL ?>/img/logo.webp" alt="JB" height="56"
                 style="max-height:56px;width:auto;object-fit:contain"
                 onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='block'">
            <svg width="80" height="56" viewBox="0 0 80 56" style="display:none">
                <ellipse cx="24" cy="17" rx="22" ry="15" fill="none" stroke="#c8a026" stroke-width="2"/>
                <ellipse cx="24" cy="17" rx="17" ry="10" fill="none" stroke="#c8a026" stroke-width="1" opacity=".4"/>
                <text x="24" y="23" text-anchor="middle" fill="#fff" font-size="14" font-weight="800" font-family="Arial,sans-serif">JB</text>
            </svg>
            <span class="text-white font-semibold hidden sm:block">Chips e Linha</span>
        </div>
        <div class="flex items-center gap-3">
            <?php if ($superAdmin): ?>
            <a href="<?= APP_URL ?>/admin.php" class="text-xs text-purple-400 hover:text-purple-300 hidden sm:block border border-purple-800 px-3 py-1.5 rounded-lg">
                Gestão de Usuários
            </a>
            <?php endif; ?>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-full bg-blue-700 flex items-center justify-center text-xs font-bold">
                    <?= strtoupper(substr($user['nome'] ?: $user['username'], 0, 2)) ?>
                </div>
                <div class="hidden sm:block text-right">
                    <div class="text-sm font-medium text-white leading-tight"><?= htmlspecialchars($user['nome'] ?: $user['username']) ?></div>
                    <div class="text-xs text-gray-500"><?= htmlspecialchars($user['username']) ?> | <?= $user['role'] === 'superadmin' ? 'SuperAdmin' : 'Gestor' ?></div>
                </div>
            </div>
            <a href="<?= APP_URL ?>/api/logout.php" class="text-gray-400 hover:text-red-400 ml-1" title="Sair">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                </svg>
            </a>
        </div>
    </div>
</header>

<!-- MAIN -->
<main class="max-w-7xl mx-auto px-4 py-6">

    <!-- Page title -->
    <div class="mb-5">
        <h1 class="text-xl font-bold text-white">Identificação de Linhas</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            Filial: <span class="text-blue-400 font-medium"><?= htmlspecialchars($filialLabel) ?></span>
        </p>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
        <div class="stat-card">
            <p class="text-xs text-gray-500 mb-1">Total de Linhas</p>
            <p class="text-2xl font-bold text-blue-400"><?= $total ?></p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 mb-1">Identificadas</p>
            <p class="text-2xl font-bold text-green-400" id="cnt-id"><?= $identificados ?></p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 mb-1">Pendentes</p>
            <p class="text-2xl font-bold text-orange-400" id="cnt-pend"><?= $pendentes ?></p>
        </div>
        <div class="stat-card">
            <p class="text-xs text-gray-500 mb-1">Com Chip Físico</p>
            <p class="text-2xl font-bold text-purple-400" id="cnt-chip"><?= $comChip ?></p>
        </div>
    </div>

    <!-- Progress -->
    <div class="mb-5">
        <div class="flex justify-between text-xs text-gray-500 mb-1.5">
            <span>Progresso de identificação</span>
            <span id="prog-txt"><?= $identificados ?> / <?= $total ?> (<?= $progPct ?>%)</span>
        </div>
        <div class="progress-bar">
            <div class="progress-fill" id="prog-bar" style="width:<?= $progPct ?>%"></div>
        </div>
    </div>

    <!-- BUSCA + EXPORT -->
    <div class="mb-5">
        <div class="flex gap-2 mb-2">
            <div class="search-wrap flex-1">

            <span class="search-icon">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
            </span>
            <input type="text" id="busca" class="search-inp"
                   placeholder="Pesquisar por número, setor ou plano..."
                   oninput="filtrar(this.value)">
            <button class="search-clear" id="busca-clear" style="display:none" onclick="limparBusca()">✕</button>
            </div>
            <a href="<?= APP_URL ?>/api/export_excel.php<?= $filialFilter ? '?filial=' . $filialFilter : '' ?>"
               class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-semibold whitespace-nowrap"
               style="background:#0f2038;border:1px solid #1e4620;color:#4ade80;"
               onmouseover="this.style.background='#14532d'" onmouseout="this.style.background='#0f2038'">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Excel
            </a>
        </div>
        <p class="search-count" id="busca-count"></p>
    </div>

    <?php if ($superAdmin): ?>
    <!-- Filial filter (superadmin) -->
    <div class="mb-5 flex items-center gap-3 flex-wrap">
        <label class="text-sm text-gray-400">Filtrar por filial:</label>
        <select class="filter-sel" onchange="window.location.href='?filial='+this.value">
            <option value="0" <?= !$filialFilter ? 'selected' : '' ?>>Todas as filiais</option>
            <?php foreach ($filiais as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filialFilter == $f['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($f['nome']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <?php if (empty($linhas)): ?>
    <div class="card p-10 text-center text-gray-500">
        <p class="text-lg">Nenhuma linha encontrada.</p>
        <?php if (!$superAdmin && !$user['filial_id']): ?>
        <p class="text-sm mt-2">Seu usuário não está associado a nenhuma filial. Contate o administrador.</p>
        <?php endif; ?>
    </div>
    <?php else: ?>

    <!-- ========== DESKTOP TABLE (md+) ========== -->
    <div class="hidden md:block card overflow-hidden">
        <table class="tbl w-full">
            <thead>
                <tr>
                    <th class="text-left">#</th>
                    <?php if ($superAdmin && !$filialFilter): ?>
                    <th class="text-left">Filial</th>
                    <?php endif; ?>
                    <th class="text-left">Número</th>
                    <th class="text-left">Plano</th>
                    <th class="text-left" style="min-width:200px">Setor / Usuário</th>
                    <th class="text-center">Chip Físico</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Ação</th>
                </tr>
            </thead>
            <tbody id="tbody-linhas">
            <?php foreach ($linhas as $i => $l):
                $locked  = !empty($l['setor_usuario']) && !$superAdmin;
                $filled  = !empty($l['setor_usuario']);
                $chip    = $l['tem_chip_fisico'];
                $srch    = strtolower(preg_replace('/\D/','',$l['telefone']).' '.($l['setor_usuario']??'').' '.($l['parametro']??'').' '.($l['filial_nome']??''));
            ?>
                <tr id="tr-<?= $l['id'] ?>" data-id="<?= $l['id'] ?>" data-filled="<?= $filled ? '1' : '0' ?>" data-search="<?= htmlspecialchars($srch) ?>">
                    <td class="text-gray-600 text-xs"><?= $i+1 ?></td>
                    <?php if ($superAdmin && !$filialFilter): ?>
                    <td><span class="filial-tag"><?= htmlspecialchars($l['filial_nome']) ?></span></td>
                    <?php endif; ?>
                    <td class="font-mono font-semibold text-blue-300"><?= formatPhone($l['telefone']) ?></td>
                    <td><span class="badge-plan"><?= htmlspecialchars($l['parametro']) ?></span></td>
                    <td>
                        <?php if ($filled && !$superAdmin): ?>
                        <span class="text-white font-medium uppercase"><?= htmlspecialchars($l['setor_usuario']) ?></span>
                        <?php else: ?>
                        <input type="text" id="setor-<?= $l['id'] ?>"
                               class="inp-setor"
                               placeholder="Digite o setor ou usuário..."
                               value="<?= htmlspecialchars($l['setor_usuario'] ?? '') ?>"
                               <?= ($locked) ? 'disabled' : '' ?>
                               onkeyup="this.value=this.value.toUpperCase()">
                        <?php endif; ?>
                    </td>
                    <td class="text-center" id="chip-cell-<?= $l['id'] ?>">
                        <?php if ($filled && !$superAdmin): ?>
                        <span class="chip-btn <?= $chip === '1' ? 'active-sim' : ($chip === '0' ? 'active-nao' : 'active-dun') ?>">
                            <?= chipLabel($chip) ?>
                        </span>
                        <?php else: ?>
                        <div class="flex justify-center gap-1">
                            <button class="chip-btn <?= $chip === '1' ? 'active-sim' : 'idle' ?>" onclick="setChip(<?= $l['id'] ?>,1,this)" data-val="1">Sim</button>
                            <button class="chip-btn <?= $chip === '0' ? 'active-nao' : 'idle' ?>" onclick="setChip(<?= $l['id'] ?>,0,this)" data-val="0">Não</button>
                            <button class="chip-btn <?= $chip === null ? 'active-dun' : 'idle' ?>" onclick="setChip(<?= $l['id'] ?>,null,this)" data-val="">?</button>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" id="status-<?= $l['id'] ?>">
                        <?php if ($filled): ?>
                        <span class="text-xs text-green-400 flex items-center justify-center gap-1">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
                            Identificado
                        </span>
                        <?php else: ?>
                        <span class="text-xs text-orange-400">Pendente</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" id="acao-<?= $l['id'] ?>">
                        <?php if ($filled && !$superAdmin): ?>
                        <svg width="16" height="16" fill="none" stroke="#22c55e" stroke-width="2" viewBox="0 0 24 24" class="mx-auto">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <?php elseif ($superAdmin && $filled): ?>
                        <button class="btn-edit" onclick="salvarRow(<?= $l['id'] ?>)">Atualizar</button>
                        <?php else: ?>
                        <button class="btn-save" onclick="salvarRow(<?= $l['id'] ?>)" id="btn-<?= $l['id'] ?>">Salvar</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- ========== MOBILE CARDS (< md) ========== -->
    <div class="md:hidden space-y-3" id="cards-container">
    <?php foreach ($linhas as $l):
        $locked = !empty($l['setor_usuario']) && !$superAdmin;
        $filled = !empty($l['setor_usuario']);
        $chip   = $l['tem_chip_fisico'];
        $srch   = strtolower(preg_replace('/\D/','',$l['telefone']).' '.($l['setor_usuario']??'').' '.($l['parametro']??'').' '.($l['filial_nome']??''));
    ?>
        <div class="card p-4" id="card-<?= $l['id'] ?>" data-id="<?= $l['id'] ?>" data-filled="<?= $filled ? '1' : '0' ?>" data-search="<?= htmlspecialchars($srch) ?>">
            <!-- Phone + badges -->
            <div class="flex items-start justify-between mb-3">
                <div>
                    <p class="font-mono font-bold text-blue-300 text-lg tracking-wide"><?= formatPhone($l['telefone']) ?></p>
                    <div class="flex gap-2 mt-1 flex-wrap">
                        <span class="badge-plan"><?= htmlspecialchars($l['parametro']) ?></span>
                        <?php if ($superAdmin): ?>
                        <span class="filial-tag"><?= htmlspecialchars($l['filial_nome']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div id="card-status-<?= $l['id'] ?>">
                    <?php if ($filled): ?>
                    <span class="text-green-400">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>
                        </svg>
                    </span>
                    <?php else: ?>
                    <span class="text-orange-500">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Setor -->
            <div class="mb-3">
                <label class="text-xs text-gray-500 mb-1 block">SETOR / USUÁRIO</label>
                <?php if ($filled && !$superAdmin): ?>
                <p class="text-white font-semibold uppercase text-sm" id="card-setor-txt-<?= $l['id'] ?>"><?= htmlspecialchars($l['setor_usuario']) ?></p>
                <?php else: ?>
                <input type="text" id="card-setor-<?= $l['id'] ?>"
                       class="inp-setor"
                       placeholder="Digite o setor ou usuário..."
                       value="<?= htmlspecialchars($l['setor_usuario'] ?? '') ?>"
                       <?= ($locked) ? 'disabled' : '' ?>
                       onkeyup="this.value=this.value.toUpperCase()">
                <?php endif; ?>
            </div>

            <!-- Chip + Save -->
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <label class="text-xs text-gray-500 mb-1.5 block">CHIP FÍSICO</label>
                    <?php if ($filled && !$superAdmin): ?>
                    <span class="chip-btn <?= $chip === '1' ? 'active-sim' : ($chip === '0' ? 'active-nao' : 'active-dun') ?>">
                        <?= chipLabel($chip) ?>
                    </span>
                    <?php else: ?>
                    <div class="flex gap-1.5" id="card-chip-<?= $l['id'] ?>">
                        <button class="chip-btn <?= $chip === '1' ? 'active-sim' : 'idle' ?>" onclick="setChipCard(<?= $l['id'] ?>,1,this)" data-val="1">Sim</button>
                        <button class="chip-btn <?= $chip === '0' ? 'active-nao' : 'idle' ?>" onclick="setChipCard(<?= $l['id'] ?>,0,this)" data-val="0">Não</button>
                        <button class="chip-btn <?= $chip === null ? 'active-dun' : 'idle' ?>" onclick="setChipCard(<?= $l['id'] ?>,null,this)" data-val="">?</button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!$filled || $superAdmin): ?>
                <button class="btn-save" onclick="salvarCard(<?= $l['id'] ?>)" id="card-btn-<?= $l['id'] ?>">
                    <?= ($superAdmin && $filled) ? 'Atualizar' : 'Salvar' ?>
                </button>
                <?php else: ?>
                <div class="flex items-center gap-1 text-green-400 text-xs">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Bloqueado
                </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>

    <?php endif; // end if linhas ?>

</main>

<!-- TOAST -->
<div id="toast" class="fixed top-4 right-4 z-50 px-4 py-3 rounded-xl text-sm font-semibold shadow-xl hidden"
     style="min-width:200px;transition:opacity .3s"></div>

<script>
// Chip selection state per row
const chipState = {};

function setChip(id, val, btn) {
    chipState[id] = val;
    const cell = document.getElementById('chip-cell-' + id);
    cell.querySelectorAll('.chip-btn').forEach(b => {
        b.className = 'chip-btn idle';
        if ((b.dataset.val === '' && val === null) ||
            (b.dataset.val === '1' && val === 1) ||
            (b.dataset.val === '0' && val === 0)) {
            b.className = 'chip-btn ' + (val === 1 ? 'active-sim' : val === 0 ? 'active-nao' : 'active-dun');
        }
    });
}

function setChipCard(id, val, btn) {
    chipState[id] = val;
    const container = document.getElementById('card-chip-' + id);
    container.querySelectorAll('.chip-btn').forEach(b => {
        b.className = 'chip-btn idle';
        if ((b.dataset.val === '' && val === null) ||
            (b.dataset.val === '1' && val === 1) ||
            (b.dataset.val === '0' && val === 0)) {
            b.className = 'chip-btn ' + (val === 1 ? 'active-sim' : val === 0 ? 'active-nao' : 'active-dun');
        }
    });
}

async function salvarRow(id) {
    const setor = (document.getElementById('setor-' + id)?.value || '').trim().toUpperCase();
    const chip  = id in chipState ? chipState[id] : null;
    await salvar(id, setor, chip, 'row');
}

async function salvarCard(id) {
    const setor = (document.getElementById('card-setor-' + id)?.value || '').trim().toUpperCase();
    const chip  = id in chipState ? chipState[id] : null;
    await salvar(id, setor, chip, 'card');
}

async function salvar(id, setor, chip, type) {
    if (!setor) { toast('Informe o Setor ou Usuário', 'err'); return; }

    const btn = document.getElementById((type==='row'?'btn-':'card-btn-') + id);
    if (btn) { btn.disabled = true; btn.textContent = 'Salvando...'; }

    try {
        const r = await fetch('<?= APP_URL ?>/api/update_linha.php', {
            method : 'POST',
            headers: {'Content-Type':'application/json'},
            body   : JSON.stringify({id, setor_usuario: setor, tem_chip_fisico: chip})
        });
        const d = await r.json();
        if (d.success) {
            toast('Salvo com sucesso!', 'ok');
            lockRow(id, setor, chip);
            updateStats();
        } else {
            toast(d.error || 'Erro ao salvar', 'err');
            if (btn) { btn.disabled = false; btn.textContent = 'Salvar'; }
        }
    } catch(e) {
        toast('Erro de conexão', 'err');
        if (btn) { btn.disabled = false; btn.textContent = 'Salvar'; }
    }
}

const isSuperAdmin = <?= $superAdmin ? 'true' : 'false' ?>;

function lockRow(id, setor, chip) {
    if (isSuperAdmin) return; // superadmin always editable

    // Table row
    const tr = document.getElementById('tr-' + id);
    if (tr) {
        const inp = document.getElementById('setor-' + id);
        if (inp) inp.disabled = true;
        document.getElementById('status-' + id).innerHTML =
            '<span class="text-xs text-green-400 flex items-center justify-center gap-1"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>Identificado</span>';
        document.getElementById('acao-' + id).innerHTML =
            '<svg width="16" height="16" fill="none" stroke="#22c55e" stroke-width="2" viewBox="0 0 24 24" class="mx-auto"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>';
        tr.dataset.filled = '1';
    }

    // Card
    const card = document.getElementById('card-' + id);
    if (card) {
        const cinp = document.getElementById('card-setor-' + id);
        if (cinp) cinp.disabled = true;
        const chipLabel = chip === 1 ? 'Sim' : chip === 0 ? 'Não' : '?';
        const chipCls   = chip === 1 ? 'active-sim' : chip === 0 ? 'active-nao' : 'active-dun';
        document.getElementById('card-status-' + id).innerHTML =
            '<span class="text-green-400"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/></svg></span>';
        card.dataset.filled = '1';
    }
}

function updateStats() {
    const all    = document.querySelectorAll('[data-id]');
    const filled = [...all].filter(el => el.dataset.filled === '1');
    // Count unique IDs
    const allIds    = new Set([...document.querySelectorAll('[data-id]')].map(e=>e.dataset.id));
    const filledIds = new Set([...document.querySelectorAll('[data-filled="1"]')].map(e=>e.dataset.id));

    const total = allIds.size;
    const ident = filledIds.size;
    const pend  = total - ident;
    const pct   = total > 0 ? Math.round(ident/total*100) : 0;

    document.getElementById('cnt-id').textContent   = ident;
    document.getElementById('cnt-pend').textContent = pend;
    document.getElementById('prog-txt').textContent = `${ident} / ${total} (${pct}%)`;
    document.getElementById('prog-bar').style.width = pct + '%';
}

function filtrar(q) {
    const raw   = q.trim().toLowerCase();
    const digit = raw.replace(/\D/g, '');
    const clear = document.getElementById('busca-clear');
    const count = document.getElementById('busca-count');
    clear.style.display = raw ? 'block' : 'none';

    let visiveis = 0;

    // Table rows
    document.querySelectorAll('#tbody-linhas tr[data-search]').forEach(tr => {
        const s = tr.dataset.search || '';
        const ok = !raw || s.includes(raw) || (digit && s.includes(digit));
        tr.style.display = ok ? '' : 'none';
        if (ok) visiveis++;
    });

    // Mobile cards
    document.querySelectorAll('#cards-container > div[data-search]').forEach(card => {
        const s = card.dataset.search || '';
        const ok = !raw || s.includes(raw) || (digit && s.includes(digit));
        card.style.display = ok ? '' : 'none';
    });

    count.textContent = raw ? `${visiveis} resultado${visiveis !== 1 ? 's' : ''} encontrado${visiveis !== 1 ? 's' : ''}` : '';
}

function limparBusca() {
    const inp = document.getElementById('busca');
    inp.value = '';
    filtrar('');
    inp.focus();
}

function toast(msg, type) {
    const el = document.getElementById('toast');
    el.textContent  = msg;
    el.style.opacity = '1';
    el.style.background = type === 'ok' ? '#14532d' : '#450a0a';
    el.style.border     = type === 'ok' ? '1px solid #16a34a' : '1px solid #b91c1c';
    el.style.color      = type === 'ok' ? '#4ade80' : '#fca5a5';
    el.classList.remove('hidden');
    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.classList.add('hidden'), 300);
    }, 3000);
}
</script>
</body>
</html>
