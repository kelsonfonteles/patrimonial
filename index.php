<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';
startSession();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Linhas – Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: { extend: { colors: {
            jb: { 950:'#02081a', 900:'#050f1e', 800:'#091627', 700:'#0f2038' }
        }}}
    }
    </script>
    <style>
        body { background-color:#02081a; font-family:'Segoe UI',system-ui,sans-serif; }
        .card { background:#091627; border:1px solid #1a2e45; }
        .inp {
            background:#0f1f30; border:1px solid #1e3248; color:#fff;
            width:100%; padding:.75rem 1rem; border-radius:.5rem; font-size:.875rem;
            transition:border-color .2s;
        }
        .inp:focus { outline:none; border-color:#3b82f6; }
        .inp::placeholder { color:#4b6070; }
        .btn-g { background:#22c55e; color:#000; font-weight:700; }
        .btn-g:hover { background:#16a34a; }
        .btn-o { background:transparent; border:1px solid #374151; color:#d1d5db; }
        .btn-o:hover { background:#1f2937; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- header -->
    <header style="background:#050f1e;border-bottom:1px solid #1a2e45" class="px-4 py-3">
        <div class="flex items-center justify-between max-w-7xl mx-auto">
            <img src="<?= APP_URL ?>/img/logo.webp" alt="JB" height="56"
                 style="max-height:56px;width:auto;object-fit:contain"
                 onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='block'">
            <svg width="80" height="56" viewBox="0 0 80 56" style="display:none">
                <ellipse cx="26" cy="19" rx="24" ry="17" fill="none" stroke="#c8a026" stroke-width="2"/>
                <ellipse cx="26" cy="19" rx="19" ry="12" fill="none" stroke="#c8a026" stroke-width="1" opacity=".4"/>
                <text x="26" y="25" text-anchor="middle" fill="#fff" font-size="16" font-weight="800" font-family="Arial,sans-serif">JB</text>
            </svg>
            <div></div>
        </div>
    </header>

    <!-- main -->
    <main class="flex-1 flex items-center justify-center px-4 py-10">
        <div class="card rounded-2xl p-8 w-full max-w-sm shadow-2xl">
            <div class="text-center mb-7">
                <h1 class="text-2xl font-bold text-white">Gestor de Linhas</h1>
                <p class="text-gray-400 text-sm mt-1">Bem-vindo!</p>
            </div>

            <div id="err" class="hidden mb-4 p-3 rounded-lg text-sm"
                 style="background:#2d0f0f;border:1px solid #7f1d1d;color:#fca5a5"></div>

            <div class="space-y-4">
                <input type="text"  id="u" placeholder="Usuário" class="inp" autocomplete="username">
                <input type="password" id="p" placeholder="Senha" class="inp" autocomplete="current-password">
                <div>
                    <a href="#" class="text-blue-400 text-sm hover:text-blue-300">Esqueceu sua senha?</a>
                </div>
                <div class="flex gap-3 pt-1">
                    <button onclick="limpar()" class="btn-o flex-1 py-3 rounded-xl text-sm font-medium transition-colors cursor-pointer">
                        Limpar
                    </button>
                    <button onclick="entrar()" id="btn" class="btn-g flex-1 py-3 rounded-xl text-sm transition-colors cursor-pointer">
                        Entrar
                    </button>
                </div>
            </div>
        </div>
    </main>

    <!-- footer -->
    <footer class="py-4 text-center" style="color:#2a3f55;font-size:.75rem">
        <p>© Grupo JB, Transporte e Logística</p>
        <p>© 2026 – Todos os direitos reservados</p>
    </footer>

    <script>
    document.addEventListener('keydown', e => { if (e.key === 'Enter') entrar(); });

    function limpar() {
        document.getElementById('u').value = '';
        document.getElementById('p').value = '';
        document.getElementById('err').classList.add('hidden');
    }

    async function entrar() {
        const btn = document.getElementById('btn');
        const err = document.getElementById('err');
        const u   = document.getElementById('u').value.trim();
        const p   = document.getElementById('p').value;

        err.classList.add('hidden');
        if (!u || !p) { showErr('Informe usuário e senha'); return; }

        btn.disabled    = true;
        btn.textContent = 'Entrando...';

        try {
            const r = await fetch('<?= APP_URL ?>/api/login.php', {
                method : 'POST',
                headers: {'Content-Type':'application/json'},
                body   : JSON.stringify({username:u, password:p})
            });
            const d = await r.json();
            if (d.success) {
                window.location.href = d.redirect;
            } else {
                showErr(d.error || 'Erro ao fazer login');
                btn.disabled = false; btn.textContent = 'Entrar';
            }
        } catch(e) {
            showErr('Erro de conexão. Tente novamente.');
            btn.disabled = false; btn.textContent = 'Entrar';
        }
    }

    function showErr(msg) {
        const el = document.getElementById('err');
        el.textContent = msg;
        el.classList.remove('hidden');
    }
    </script>
</body>
</html>
