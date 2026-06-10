<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireSuperAdmin();
$user = currentUser();
$db   = getDB();

$users   = $db->query("SELECT u.*, f.nome AS filial_nome FROM users u LEFT JOIN filiais f ON f.id = u.filial_id ORDER BY u.role DESC, u.nome")->fetchAll();
$filiais = $db->query("SELECT id, nome FROM filiais ORDER BY nome")->fetchAll();
$total   = count($users);
$ativos  = count(array_filter($users, fn($u) => $u['ativo'] == 1));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Usuários – Gestor de Linhas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{colors:{jb:{950:'#02081a',900:'#050f1e',800:'#091627',700:'#0f2038'}}}}}</script>
    <style>
        body { background:#02081a; font-family:'Segoe UI',system-ui,sans-serif; color:#fff; }
        .hdr { background:#050f1e; border-bottom:1px solid #1a2e45; }
        .card { background:#091627; border:1px solid #1a2e45; border-radius:.75rem; }
        .tbl th { background:#050f1e; padding:.625rem 1rem; font-size:.75rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:#4b6280; }
        .tbl td { padding:.75rem 1rem; border-bottom:1px solid #0f1f30; font-size:.875rem; vertical-align:middle; }
        .tbl tr:last-child td { border-bottom:none; }
        .tbl tr:hover td { background:#0a1828; }
        .inp { background:#0f1f30; border:1px solid #1e3248; color:#fff; padding:.625rem .875rem; border-radius:.5rem; font-size:.875rem; width:100%; }
        .inp:focus { outline:none; border-color:#3b82f6; }
        .inp::placeholder { color:#3d5468; }
        .sel { background:#0f1f30; border:1px solid #1e3248; color:#fff; padding:.625rem .875rem; border-radius:.5rem; font-size:.875rem; width:100%; }
        .sel:focus { outline:none; border-color:#3b82f6; }
        .btn-p { background:#22c55e; color:#000; font-weight:700; padding:.6rem 1.25rem; border-radius:.5rem; font-size:.875rem; cursor:pointer; }
        .btn-p:hover { background:#16a34a; }
        .btn-s { background:transparent; border:1px solid #374151; color:#d1d5db; padding:.5rem 1rem; border-radius:.5rem; font-size:.875rem; cursor:pointer; }
        .btn-s:hover { background:#1f2937; }
        .btn-e { background:#1e3a6e; color:#93c5fd; border:1px solid #1d4ed8; padding:.3rem .7rem; border-radius:.375rem; font-size:.75rem; cursor:pointer; }
        .btn-e:hover { background:#1d4ed8; color:#fff; }
        .btn-d { background:#450a0a; color:#fca5a5; border:1px solid #7f1d1d; padding:.3rem .7rem; border-radius:.375rem; font-size:.75rem; cursor:pointer; }
        .btn-d:hover { background:#7f1d1d; }
        .badge-role-admin { background:#3b0764; border:1px solid #7c3aed; color:#c4b5fd; padding:.15rem .5rem; border-radius:.3rem; font-size:.7rem; }
        .badge-role-gestor { background:#0c1f38; border:1px solid #1d4ed8; color:#60a5fa; padding:.15rem .5rem; border-radius:.3rem; font-size:.7rem; }
        .badge-ativo { background:#14532d; border:1px solid #16a34a; color:#4ade80; padding:.15rem .5rem; border-radius:.3rem; font-size:.7rem; }
        .badge-inativo { background:#1c1917; border:1px solid #44403c; color:#78716c; padding:.15rem .5rem; border-radius:.3rem; font-size:.7rem; }
        .overlay { position:fixed; inset:0; background:rgba(0,0,0,.75); z-index:40; display:none; }
        .modal { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); width:90%; max-width:460px; z-index:50; background:#091627; border:1px solid #1a2e45; border-radius:1rem; padding:1.5rem; display:none; }
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
            <span class="text-white font-semibold">Gestor de Linhas</span>
            <span class="text-gray-600">/</span>
            <span class="text-purple-400 text-sm">Gestão de Usuários</span>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= APP_URL ?>/dashboard.php" class="text-xs text-blue-400 hover:text-blue-300 border border-blue-800 px-3 py-1.5 rounded-lg">
                ← Dashboard
            </a>
            <a href="<?= APP_URL ?>/api/logout.php" class="text-gray-400 hover:text-red-400" title="Sair">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
                </svg>
            </a>
        </div>
    </div>
</header>

<main class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-white">Usuários do Sistema</h1>
            <p class="text-sm text-gray-500 mt-0.5"><?= $total ?> usuários · <?= $ativos ?> ativos</p>
        </div>
        <button class="btn-p" onclick="openModal()">+ Novo Usuário</button>
    </div>

    <!-- Table -->
    <div class="card overflow-x-auto">
        <table class="tbl w-full">
            <thead>
                <tr>
                    <th class="text-left">Usuário</th>
                    <th class="text-left">Nome</th>
                    <th class="text-left">Filial</th>
                    <th class="text-left">Perfil</th>
                    <th class="text-left">Status</th>
                    <th class="text-left">Desde</th>
                    <th class="text-center">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td class="font-mono text-blue-300"><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['nome'] ?: '—') ?></td>
                    <td>
                        <?php if ($u['filial_nome']): ?>
                        <span style="background:#0f2038;border:1px solid #1e3a6e;color:#60a5fa;padding:.15rem .5rem;border-radius:.3rem;font-size:.7rem">
                            <?= htmlspecialchars($u['filial_nome']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-gray-600 text-xs">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="<?= $u['role'] === 'superadmin' ? 'badge-role-admin' : 'badge-role-gestor' ?>">
                            <?= $u['role'] === 'superadmin' ? 'SuperAdmin' : 'Gestor' ?>
                        </span>
                    </td>
                    <td>
                        <span class="<?= $u['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>">
                            <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="text-gray-500 text-xs"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                    <td class="text-center">
                        <div class="flex justify-center gap-2">
                            <button class="btn-e" onclick="editUser(<?= htmlspecialchars(json_encode($u), ENT_QUOTES) ?>)">Editar</button>
                            <?php if ($u['ativo'] && $u['username'] !== SUPERADMIN_USER): ?>
                            <button class="btn-d" onclick="desativar(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">Desativar</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- OVERLAY -->
<div class="overlay" id="overlay" onclick="closeModal()"></div>

<!-- MODAL -->
<div class="modal" id="modal">
    <h2 class="text-lg font-bold text-white mb-5" id="modal-title">Novo Usuário</h2>
    <div id="modal-err" class="hidden mb-4 p-3 rounded-lg text-sm" style="background:#2d0f0f;border:1px solid #7f1d1d;color:#fca5a5"></div>
    <input type="hidden" id="edit-id">
    <div class="space-y-3">
        <div>
            <label class="text-xs text-gray-400 mb-1 block">USERNAME *</label>
            <input type="text" id="m-user" class="inp" placeholder="ex: joao.silva">
        </div>
        <div>
            <label class="text-xs text-gray-400 mb-1 block">NOME COMPLETO</label>
            <input type="text" id="m-nome" class="inp" placeholder="Nome do gestor">
        </div>
        <div>
            <label class="text-xs text-gray-400 mb-1 block">SENHA <span id="pwd-label" class="text-gray-600">(obrigatória)</span></label>
            <input type="password" id="m-pwd" class="inp" placeholder="Senha de acesso">
        </div>
        <div>
            <label class="text-xs text-gray-400 mb-1 block">FILIAL</label>
            <select id="m-filial" class="sel">
                <option value="">— Sem filial (superadmin) —</option>
                <?php foreach ($filiais as $f): ?>
                <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="text-xs text-gray-400 mb-1 block">PERFIL</label>
            <select id="m-role" class="sel">
                <option value="gestor">Gestor</option>
                <option value="superadmin">SuperAdmin</option>
            </select>
        </div>
        <div id="ativo-row" class="hidden">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="m-ativo" class="accent-green-500 w-4 h-4" checked>
                <span class="text-sm text-gray-300">Usuário ativo</span>
            </label>
        </div>
    </div>
    <div class="flex gap-3 mt-6">
        <button class="btn-s flex-1" onclick="closeModal()">Cancelar</button>
        <button class="btn-p flex-1" onclick="saveUser()">Salvar</button>
    </div>
</div>

<div id="toast" class="fixed top-4 right-4 z-50 px-4 py-3 rounded-xl text-sm font-semibold shadow-xl hidden"></div>

<script>
let editingId = null;

function openModal() {
    editingId = null;
    document.getElementById('modal-title').textContent = 'Novo Usuário';
    document.getElementById('edit-id').value  = '';
    document.getElementById('m-user').value   = '';
    document.getElementById('m-nome').value   = '';
    document.getElementById('m-pwd').value    = '';
    document.getElementById('m-filial').value = '';
    document.getElementById('m-role').value   = 'gestor';
    document.getElementById('m-user').disabled = false;
    document.getElementById('pwd-label').textContent = '(obrigatória)';
    document.getElementById('ativo-row').classList.add('hidden');
    document.getElementById('modal-err').classList.add('hidden');
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('modal').style.display   = 'block';
}

function editUser(u) {
    editingId = u.id;
    document.getElementById('modal-title').textContent = 'Editar Usuário';
    document.getElementById('edit-id').value  = u.id;
    document.getElementById('m-user').value   = u.username;
    document.getElementById('m-nome').value   = u.nome || '';
    document.getElementById('m-pwd').value    = '';
    document.getElementById('m-filial').value = u.filial_id || '';
    document.getElementById('m-role').value   = u.role;
    document.getElementById('m-ativo').checked = u.ativo == 1;
    document.getElementById('m-user').disabled = true;
    document.getElementById('pwd-label').textContent = '(deixe em branco para manter)';
    document.getElementById('ativo-row').classList.remove('hidden');
    document.getElementById('modal-err').classList.add('hidden');
    document.getElementById('overlay').style.display = 'block';
    document.getElementById('modal').style.display   = 'block';
}

function closeModal() {
    document.getElementById('overlay').style.display = 'none';
    document.getElementById('modal').style.display   = 'none';
}

async function saveUser() {
    const id     = document.getElementById('edit-id').value;
    const user   = document.getElementById('m-user').value.trim();
    const nome   = document.getElementById('m-nome').value.trim();
    const pwd    = document.getElementById('m-pwd').value;
    const filial = document.getElementById('m-filial').value;
    const role   = document.getElementById('m-role').value;
    const ativo  = document.getElementById('m-ativo').checked;

    const errEl = document.getElementById('modal-err');
    errEl.classList.add('hidden');

    const body = {nome, filial_id: filial || null, role};
    let method, url = '<?= APP_URL ?>/api/users.php';

    if (id) {
        method = 'PUT'; body.id = parseInt(id); body.ativo = ativo;
        if (pwd) body.password = pwd;
    } else {
        if (!user || !pwd) { showModalErr('Username e senha são obrigatórios'); return; }
        method = 'POST'; body.username = user; body.password = pwd;
    }

    try {
        const r = await fetch(url, {method, headers:{'Content-Type':'application/json'}, body: JSON.stringify(body)});
        const d = await r.json();
        if (d.success) {
            closeModal(); toast('Salvo!', 'ok');
            setTimeout(() => location.reload(), 800);
        } else {
            showModalErr(d.error || 'Erro ao salvar');
        }
    } catch(e) { showModalErr('Erro de conexão'); }
}

async function desativar(id, uname) {
    if (!confirm(`Desativar usuário "${uname}"?`)) return;
    const r = await fetch('<?= APP_URL ?>/api/users.php', {
        method:'DELETE', headers:{'Content-Type':'application/json'}, body: JSON.stringify({id})
    });
    const d = await r.json();
    if (d.success) { toast('Usuário desativado', 'ok'); setTimeout(() => location.reload(), 800); }
    else toast(d.error || 'Erro', 'err');
}

function showModalErr(msg) {
    const el = document.getElementById('modal-err');
    el.textContent = msg; el.classList.remove('hidden');
}

function toast(msg, type) {
    const el = document.getElementById('toast');
    el.textContent  = msg;
    el.style.opacity = '1';
    el.style.background = type === 'ok' ? '#14532d' : '#450a0a';
    el.style.border     = type === 'ok' ? '1px solid #16a34a' : '1px solid #b91c1c';
    el.style.color      = type === 'ok' ? '#4ade80' : '#fca5a5';
    el.classList.remove('hidden');
    setTimeout(() => { el.style.opacity='0'; setTimeout(()=>el.classList.add('hidden'),300); }, 3000);
}
</script>
</body>
</html>
