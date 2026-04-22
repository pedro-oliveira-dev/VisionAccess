<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Criar Conta</h2>
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Erro ao cadastrar. Verifique os dados e tente novamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <form action="php/auth.php?action=register" method="POST">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" id="nome" name="nome" required placeholder="Seu nome completo">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="exemplo@email.com">
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" required placeholder="Crie uma senha forte">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_senha" class="form-label">Confirmar Senha</label>
                        <input type="password" class="form-control" id="confirm_senha" name="confirm_senha" required placeholder="Repita a senha">
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">Cadastrar</button>
                    </div>
                    <div class="text-center">
                        <p class="mb-0">Já tem uma conta? <a href="index.php" class="text-decoration-none">Faça login</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
