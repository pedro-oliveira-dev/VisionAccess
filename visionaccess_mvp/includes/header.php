<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VisionAccess - Triagem Orientativa e Acessibilidade Visual</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-custom">
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand" href="index.php">VisionAccess</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Abrir navegação">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item"><a class="nav-link" href="dashboard.php">Painel</a></li>
                        <li class="nav-item"><a class="nav-link" href="triagem.php">Nova Triagem</a></li>
                        <li class="nav-item"><a class="nav-link" href="historico.php">Histórico</a></li>
                        <li class="nav-item"><a class="nav-link" href="sobre_nos.php">Sobre Nós</a></li>
                        <li class="nav-item"><a class="nav-link" href="como_funciona.php">Como Funciona?</a></li>
                        <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link fw-bold text-primary" href="admin.php">Admin</a></li>
                        <?php endif; ?>
                        <li class="nav-item"><a class="nav-link btn btn-outline-danger btn-sm ms-lg-2" href="php/auth.php?action=logout">Sair</a></li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="sobre_nos.php">Sobre Nós</a></li>
                        <li class="nav-item"><a class="nav-link" href="como_funciona.php">Como Funciona?</a></li>
                        <li class="nav-item"><a class="nav-link" href="index.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="cadastro.php">Cadastrar</a></li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
                        <button id="toggle-contrast" class="btn btn-sm btn-dark" title="Alto Contraste" aria-label="Alternar alto contraste">🌓</button>
                        <button id="increase-font" class="btn btn-sm btn-secondary" title="Aumentar Fonte" aria-label="Alternar tamanho da fonte">A+</button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <main class="container">
