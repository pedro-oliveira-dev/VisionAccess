<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$stmtAdmin = $pdo->prepare('SELECT perfil FROM usuario WHERE id_usuario = ? LIMIT 1');
$stmtAdmin->execute([(int) $_SESSION['user_id']]);
$perfilAtual = $stmtAdmin->fetchColumn();

if ($perfilAtual !== 'admin') {
    http_response_code(403);
    echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><title>Acesso negado</title></head><body>';
    echo '<h1>Acesso negado</h1><p>Esta área é exclusiva para administradores.</p><p><a href="dashboard.php">Voltar ao painel</a></p>';
    echo '</body></html>';
    exit();
}

$_SESSION['user_role'] = 'admin';
