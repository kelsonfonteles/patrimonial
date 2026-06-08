<?php
require_once dirname(__DIR__) . '/config.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
}

function requireLogin(): void {
    startSession();
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function requireSuperAdmin(): void {
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'superadmin') {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit;
    }
}

function isSuperAdmin(): bool {
    startSession();
    return ($_SESSION['role'] ?? '') === 'superadmin';
}

function currentUser(): array {
    startSession();
    return [
        'id'          => $_SESSION['user_id']     ?? null,
        'username'    => $_SESSION['username']    ?? null,
        'nome'        => $_SESSION['nome']        ?? null,
        'role'        => $_SESSION['role']        ?? null,
        'filial_id'   => $_SESSION['filial_id']   ?? null,
        'filial_nome' => $_SESSION['filial_nome'] ?? null,
    ];
}
