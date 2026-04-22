<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'includes/header.php';
?>

<div class="row">
    <div class="col-12 mb-4">
        <h1 class="display-5">Olá, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
        <p class="lead">Bem-vindo ao VisionAccess. Como podemos ajudar você hoje?</p>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <span class="display-1 text-primary">👁️</span>
                </div>
                <h3 class="card-title">Nova Triagem</h3>
                <p class="card-text text-muted">Inicie uma nova avaliação para identificar possíveis dificuldades visuais e receber orientações.</p>
                <a href="triagem.php" class="btn btn-primary btn-lg px-5">Começar Agora</a>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100 shadow-sm border-0">
            <div class="card-body p-4 text-center">
                <div class="mb-3">
                    <span class="display-1 text-secondary">📜</span>
                </div>
                <h3 class="card-title">Histórico</h3>
                <p class="card-text text-muted">Veja os resultados de suas triagens anteriores e acompanhe sua saúde visual.</p>
                <a href="historico.php" class="btn btn-outline-secondary btn-lg px-5">Ver Histórico</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-5">
    <div class="col-12">
        <div class="alert alert-info border-0 shadow-sm">
            <h4 class="alert-heading">Dica de Acessibilidade</h4>
            <p class="mb-0">Você pode ajustar o contraste e o tamanho da fonte a qualquer momento usando os botões no topo da página.</p>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
