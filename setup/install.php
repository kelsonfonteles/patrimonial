<?php
/**
 * INSTALADOR â€“ Gestor de Linhas
 * Acesse uma Ãºnica vez para criar o banco, tabelas e importar dados.
 * APAGUE ou RENOMEIE este arquivo apÃ³s a instalaÃ§Ã£o.
 */

require_once dirname(__DIR__) . '/config.php';

$step    = $_POST['step'] ?? '';
$log     = [];
$success = false;
$error   = '';

if ($step === 'install') {
    try {
        // Connect WITHOUT database to create it
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $log[] = "âœ“ Conectado ao MySQL";

        // Create DB
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `" . DB_NAME . "`");
        $log[] = "âœ“ Banco de dados '" . DB_NAME . "' criado/verificado";

        // Tables
        $pdo->exec("CREATE TABLE IF NOT EXISTS filiais (
            id         INT PRIMARY KEY AUTO_INCREMENT,
            nome       VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");
        $log[] = "âœ“ Tabela 'filiais' criada";

        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id         INT PRIMARY KEY AUTO_INCREMENT,
            username   VARCHAR(100) NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            nome       VARCHAR(200),
            filial_id  INT NULL,
            role       ENUM('superadmin','gestor') NOT NULL DEFAULT 'gestor',
            ativo      TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        $log[] = "âœ“ Tabela 'users' criada";

        $pdo->exec("CREATE TABLE IF NOT EXISTS linhas (
            id               INT PRIMARY KEY AUTO_INCREMENT,
            telefone         VARCHAR(20) NOT NULL,
            parametro        VARCHAR(100),
            filial_id        INT NOT NULL,
            setor_usuario    VARCHAR(200) DEFAULT NULL,
            tem_chip_fisico  TINYINT(1) DEFAULT NULL,
            identificado_por INT DEFAULT NULL,
            identificado_em  DATETIME DEFAULT NULL,
            created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (filial_id) REFERENCES filiais(id),
            FOREIGN KEY (identificado_por) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB");
        $log[] = "âœ“ Tabela 'linhas' criada";

        // Additional integration columns (future use)
        // ALTER TABLE linhas ADD COLUMN patrimonio_id INT NULL;
        // ALTER TABLE linhas ADD COLUMN hardware_id   INT NULL;

        // Insert filiais
        $filialMap = [];
        $filialNomes = ['TRANSCLEBER', 'BM_PI', 'JB_MA', 'JB_PI'];
        $stmtF = $pdo->prepare("INSERT IGNORE INTO filiais (nome) VALUES (?)");
        foreach ($filialNomes as $fn) {
            $stmtF->execute([$fn]);
        }
        $rows = $pdo->query("SELECT id, nome FROM filiais")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) $filialMap[$r['nome']] = $r['id'];
        $log[] = "âœ“ Filiais inseridas: " . implode(', ', $filialNomes);

        // Import CSV
        $csvPath = dirname(__DIR__) . '/GRUPO TRANSCLEBER.csv';
        if (!file_exists($csvPath)) {
            $log[] = "âš  Arquivo CSV nÃ£o encontrado em: $csvPath â€“ dados nÃ£o importados";
        } else {
            $content = file_get_contents($csvPath);
            // Detect and fix encoding
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            }
            $lines   = preg_split('/\r?\n/', trim($content));
            array_shift($lines); // skip header

            $stmtL   = $pdo->prepare("INSERT IGNORE INTO linhas (telefone, parametro, filial_id) VALUES (?,?,?)");
            $imported = 0;
            $skipped  = 0;
            foreach ($lines as $line) {
                if (trim($line) === '') continue;
                $parts = explode(';', $line);
                if (count($parts) < 3) continue;
                $telefone  = trim($parts[0]);
                $parametro = trim($parts[1]);
                $filialNm  = trim($parts[2]);
                if (!$telefone || !isset($filialMap[$filialNm])) { $skipped++; continue; }
                $stmtL->execute([$telefone, $parametro, $filialMap[$filialNm]]);
                $imported++;
            }
            $log[] = "âœ“ Linhas importadas: $imported (ignoradas/duplicadas: $skipped)";
        }

        // Create superadmin
        $adminPwd  = 'admin@2026';
        $stmtU = $pdo->prepare("INSERT IGNORE INTO users (username, nome, password, role) VALUES (?,?,?,?)");
        $stmtU->execute([
            SUPERADMIN_USER,
            'Kelson Fonteles',
            password_hash($adminPwd, PASSWORD_DEFAULT),
            'superadmin'
        ]);
        $log[] = "âœ“ SuperAdmin '" . SUPERADMIN_USER . "' criado (senha: <strong>$adminPwd</strong>)";

        // Create gestores with default password
        $gestorPwd = 'gestor@123';
        $stmtG = $pdo->prepare("INSERT IGNORE INTO users (username, nome, password, filial_id, role) VALUES (?,?,?,?,'gestor')");
        foreach ($filialMap as $nome => $fid) {
            $uname = 'gestor.' . strtolower(str_replace('_', '', $nome));
            $stmtG->execute([$uname, 'Gestor ' . $nome, password_hash($gestorPwd, PASSWORD_DEFAULT), $fid]);
            $log[] = "âœ“ Gestor criado: <strong>$uname</strong> (senha: $gestorPwd) â†’ Filial: $nome";
        }

        $success = true;
        $log[] = "<br><strong style='color:#4ade80'>âœ“ InstalaÃ§Ã£o concluÃ­da com sucesso!</strong>";
        $log[] = "<span style='color:#fbbf24'>âš  IMPORTANTE: Apague ou renomeie este arquivo (setup/install.php) apÃ³s a instalaÃ§Ã£o.</span>";

    } catch (PDOException $e) {
        $error = 'Erro: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstalaÃ§Ã£o â€“ Gestor de Linhas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background:#02081a; color:#fff; font-family:'Segoe UI',system-ui,sans-serif; }
        .card { background:#091627; border:1px solid #1a2e45; border-radius:.75rem; }
        .btn { background:#22c55e; color:#000; font-weight:700; padding:.75rem 2rem; border-radius:.5rem; cursor:pointer; font-size:.9rem; }
        .btn:hover { background:#16a34a; }
        .log-line { padding:.3rem 0; border-bottom:1px solid #0f1f30; font-size:.85rem; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4 py-10">
    <div class="card p-8 w-full max-w-xl">
        <div class="flex items-center gap-3 mb-6">
            <svg width="44" height="32" viewBox="0 0 44 32">
                <ellipse cx="22" cy="16" rx="20" ry="14" fill="none" stroke="#c8a026" stroke-width="2"/>
                <text x="22" y="22" text-anchor="middle" fill="#fff" font-size="13" font-weight="800" font-family="Arial">JB</text>
            </svg>
            <div>
                <h1 class="text-xl font-bold">Gestor de Linhas</h1>
                <p class="text-sm text-gray-500">InstalaÃ§Ã£o do sistema</p>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="mb-5 p-4 rounded-lg" style="background:#2d0f0f;border:1px solid #7f1d1d;color:#fca5a5">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!$step || $error): ?>
        <div class="mb-5 p-4 rounded-lg" style="background:#0f2038;border:1px solid #1e3a6e">
            <h2 class="font-semibold text-blue-300 mb-2">O que serÃ¡ feito:</h2>
            <ul class="text-sm text-gray-300 space-y-1 list-disc list-inside">
                <li>Criar banco de dados <strong><?= DB_NAME ?></strong></li>
                <li>Criar tabelas: filiais, users, linhas</li>
                <li>Importar dados do arquivo <code>GRUPO TRANSCLEBER.csv</code></li>
                <li>Criar usuÃ¡rio superadmin: <strong><?= SUPERADMIN_USER ?></strong></li>
                <li>Criar usuÃ¡rios gestores para cada filial</li>
            </ul>
            <p class="text-xs text-yellow-500 mt-3">âš  Execute apenas uma vez. Apague este arquivo apÃ³s instalar.</p>
        </div>
        <form method="post">
            <input type="hidden" name="step" value="install">
            <button type="submit" class="btn w-full">Instalar Agora</button>
        </form>
        <?php else: ?>
        <div class="space-y-0 mb-5">
            <?php foreach ($log as $line): ?>
            <div class="log-line"><?= $line ?></div>
            <?php endforeach; ?>
        </div>
        <?php if ($success): ?>
        <a href="../index.php" class="btn block text-center">Ir para o Login â†’</a>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

