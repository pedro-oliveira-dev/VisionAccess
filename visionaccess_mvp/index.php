<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Login</h2>
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        Credenciais inválidas. Tente novamente.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Cadastro realizado com sucesso! Faça login agora.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <form action="php/auth.php?action=login" method="POST">
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="exemplo@email.com">
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" required placeholder="Sua senha">
                    </div>
                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-primary btn-lg">Entrar</button>
                    </div>
                    <div class="text-center">
                        <!--Ainda não implementado!-->
                        <a href="forgot_password.php" class="text-decoration-none">Esqueceu a senha?</a>
                        <hr>
                        <p class="mb-0">Não tem uma conta? <a href="cadastro.php" class="text-decoration-none">Cadastre-se</a></p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
