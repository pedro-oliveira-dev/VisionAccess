<?php
session_start();
require_once '../includes/db_connect.php';

$action = $_GET['action'] ?? '';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM usuario WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($senha, $user['senha'])) {
        $_SESSION['user_id'] = $user['id_usuario'];
        $_SESSION['user_name'] = $user['nome'];
        header("Location: ../dashboard.php");
        exit();
    } else {
        header("Location: ../index.php?error=1");
        exit();
    }
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome'] ?? '';
    $email = $_POST['email'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $confirm_senha = $_POST['confirm_senha'] ?? '';

    if ($senha !== $confirm_senha) {
        header("Location: ../cadastro.php?error=passwords_dont_match");
        exit();
    }

    // Criptografia de senha (RNF03)
    $hashed_password = password_hash($senha, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuario (nome, email, senha) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $email, $hashed_password]);
        header("Location: ../index.php?registered=1");
        exit();
    } catch (PDOException $e) {
        header("Location: ../cadastro.php?error=email_exists");
        exit();
    }
}

if ($action === 'logout') {
    session_destroy();
    header("Location: ../index.php");
    exit();
}
?>
