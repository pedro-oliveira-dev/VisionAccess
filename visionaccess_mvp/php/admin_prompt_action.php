<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

function voltarPrompt(string $mensagem = '', string $erro = ''): void
{
    if ($mensagem !== '') {
        $_SESSION['admin_mensagem'] = $mensagem;
    }
    if ($erro !== '') {
        $_SESSION['admin_erro'] = $erro;
    }
    header('Location: ../admin_prompt.php');
    exit();
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$stmtAdmin = $pdo->prepare('SELECT perfil FROM usuario WHERE id_usuario = ? LIMIT 1');
$stmtAdmin->execute([(int) $_SESSION['user_id']]);
if ($stmtAdmin->fetchColumn() !== 'admin') {
    http_response_code(403);
    exit('Acesso negado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarCsrf($_POST['csrf_token'] ?? null)) {
    voltarPrompt('', 'Requisição inválida ou formulário expirado.');
}

if (($_POST['action'] ?? '') !== 'salvar') {
    voltarPrompt('', 'Ação inválida.');
}

$nome = trim($_POST['nome'] ?? '');
$conteudo = trim($_POST['conteudo'] ?? '');

if ($nome === '' || $conteudo === '') {
    voltarPrompt('', 'Nome e conteúdo do prompt são obrigatórios.');
}
if (mb_strlen($nome) > 120 || mb_strlen($conteudo) > 12000) {
    voltarPrompt('', 'O prompt excede o limite permitido.');
}

try {
    $pdo->beginTransaction();

    $proximaVersao = (int) $pdo->query('SELECT COALESCE(MAX(versao), 0) + 1 FROM prompt_agente')->fetchColumn();

    $pdo->exec('UPDATE prompt_agente SET ativo = 0 WHERE ativo = 1');

    $stmt = $pdo->prepare('INSERT INTO prompt_agente (nome, versao, conteudo, ativo, criado_por) VALUES (?, ?, ?, 1, ?)');
    $stmt->execute([$nome, $proximaVersao, $conteudo, (int) $_SESSION['user_id']]);

    $pdo->commit();
    voltarPrompt('Prompt versão ' . $proximaVersao . ' salvo e ativado.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    voltarPrompt('', 'Não foi possível salvar o prompt: ' . $e->getMessage());
}
