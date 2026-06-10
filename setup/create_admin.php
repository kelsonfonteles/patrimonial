<?php
/**
 * RECUPERAÇÃO DE ACESSO — cria superadmin no banco.
 * APAGUE este arquivo após executar.
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/db.php';

$done  = false;
$error = '';
$log   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $nome     = trim($_POST['nome'] ?? '');

    if (!$username || !$password) {
        $error = 'Username e senha são obrigatórios.';
    } else {
        try {
            $db   = getDB();
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $db->prepare("
                INSERT INTO users (username, nome, password, role, ativo)
                VALUES (?, ?, ?, 'superadmin', 1)
                ON DUPLICATE KEY UPDATE
                    password = VALUES(password),
                    nome     = VALUES(nome),
                    role     = 'superadmin',
                    ativo    = 1
            ");
            $stmt->execute([$username, $nome ?: $username, $hash]);

            $log[] = "Usuário <strong>$username</strong> criado/atualizado como SuperAdmin.";
            $log[] = "Senha: <strong>$password</strong>";
            $log[] = "<span style='color:#fbbf24'>⚠ APAGUE este arquivo agora: <code>setup/create_admin.php</code></span>";
            $done  = true;

        } catch (PDOException $e) {
            $error = 'Erro no banco: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Admin — Gestor de Linhas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background:#02081a; color:#fff; font-family:'Segoe UI',system-ui,sans-serif; }
        .card { background:#091627; border:1px solid #1a2e45; border-radius:.75rem; }
        .inp { background:#0f1f30; border:1px solid #1e3248; color:#fff; padding:.625rem .875rem; border-radius:.5rem; font-size:.875rem; width:100%; }
        .inp:focus { outline:none; border-color:#3b82f6; }
        .inp::placeholder { color:#3d5468; }
        .btn { background:#22c55e; color:#000; font-weight:700; padding:.75rem 2rem; border-radius:.5rem; cursor:pointer; font-size:.9rem; width:100%; }
        .btn:hover { background:#16a34a; }
        .log-line { padding:.4rem 0; border-bottom:1px solid #0f1f30; font-size:.875rem; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="card p-8 w-full max-w-md">
        <h1 class="text-xl font-bold mb-1">Criar SuperAdmin</h1>
        <p class="text-sm text-gray-500 mb-6">Gestor de Linhas — recuperação de acesso</p>

        <?php if ($error): ?>
        <div class="mb-5 p-4 rounded-lg text-sm" style="background:#2d0f0f;border:1px solid #7f1d1d;color:#fca5a5">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($done): ?>
        <div class="space-y-0 mb-6">
            <?php foreach ($log as $line): ?>
            <div class="log-line"><?= $line ?></div>
            <?php endforeach; ?>
        </div>
        <a href="../index.php" class="btn block text-center" style="display:block;text-align:center;text-decoration:none;padding:.75rem 2rem;">
            Ir para o Login →
        </a>

        <?php else: ?>
        <div class="mb-5 p-3 rounded-lg text-xs text-yellow-400" style="background:#1a1200;border:1px solid #713f12">
            ⚠ Execute apenas uma vez. Apague este arquivo após usar.
        </div>
        <form method="post" class="space-y-4">
            <div>
                <label class="text-xs text-gray-400 mb-1 block">USERNAME *</label>
                <input type="text" name="username" class="inp" placeholder="ex: kelsonfonteles"
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">NOME COMPLETO</label>
                <input type="text" name="nome" class="inp" placeholder="Nome completo (opcional)"
                       value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>">
            </div>
            <div>
                <label class="text-xs text-gray-400 mb-1 block">SENHA *</label>
                <input type="text" name="password" class="inp" placeholder="Senha de acesso" required>
            </div>
            <button type="submit" class="btn">Criar / Resetar Usuário</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
