<?php
session_start();
require_once '../includes/db_connect.php';

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $action !== 'logout') {
    header('Location: ../index.php');
    exit();
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare('SELECT id_usuario, nome, senha, perfil FROM usuario WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($senha, $user['senha'])) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id_usuario'];
        $_SESSION['user_name'] = $user['nome'];
        $_SESSION['user_role'] = $user['perfil'] ?? 'usuario';

        if ($_SESSION['user_role'] === 'admin') {
            header('Location: ../admin.php');
        } else {
            header('Location: ../dashboard.php');
        }
        exit();
    }

    header('Location: ../index.php?error=1');
    exit();
}

if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirm_senha = $_POST['confirm_senha'] ?? '';

    if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        header('Location: ../cadastro.php?error=invalid_data');
        exit();
    }

    if ($senha !== $confirm_senha) {
        header('Location: ../cadastro.php?error=passwords_dont_match');
        exit();
    }

    $hashed_password = password_hash($senha, PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO usuario (nome, email, senha, perfil) VALUES (?, ?, ?, 'usuario')");
        $stmt->execute([$nome, $email, $hashed_password]);
        header('Location: ../index.php?registered=1');
        exit();
    } catch (PDOException $e) {
        header('Location: ../cadastro.php?error=email_exists');
        exit();
    }
}

if ($action === 'logout') {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
    header('Location: ../index.php');
    exit();
}
