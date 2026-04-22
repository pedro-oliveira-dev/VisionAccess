<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'includes/db_connect.php';
include 'includes/header.php';

$triagem_id = $_GET['id'] ?? 0;

// Buscar resultado da triagem
$stmt = $pdo->prepare("SELECT r.*, t.descricao_texto, t.data_triagem FROM resultado r JOIN triagem t ON r.id_triagem = t.id_triagem WHERE r.id_triagem = ? AND t.id_usuario = ?");
$stmt->execute([$triagem_id, $_SESSION['user_id']]);
$resultado = $stmt->fetch();

if (!$resultado) {
    echo "<div class='alert alert-warning'>Resultado não encontrado ou você não tem permissão para vê-lo.</div>";
    include 'includes/footer.php';
    exit();
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Resultado da Triagem</h2>
                <p class="text-muted text-center mb-4">Realizada em: <?php echo date('d/m/Y H:i', strtotime($resultado['data_triagem'])); ?></p>
                
                <div class="alert alert-primary border-0 shadow-sm mb-4">
                    <h4 class="alert-heading">Classificação Sugerida:</h4>
                    <p class="display-6 fw-bold mb-0"><?php echo htmlspecialchars($resultado['classificacao']); ?></p>
                    <small>Nível de Confiança da IA: <?php echo number_format($resultado['confianca'], 2); ?>%</small>
                </div>

                <div class="mb-4">
                    <h5 class="fw-bold">Análise Detalhada:</h5>
                    <p class="lead"><?php echo nl2br(htmlspecialchars($resultado['descricao_resultado'])); ?></p>
                </div>

                <div class="card bg-light border-0 mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold">Seu Relato:</h6>
                        <p class="mb-0 text-muted italic">"<?php echo htmlspecialchars($resultado['descricao_texto']); ?>"</p>
                    </div>
                </div>

                <div class="alert alert-warning border-0 shadow-sm">
                    <h5 class="alert-heading">⚠️ Aviso Importante:</h5>
                    <p class="mb-0">Este resultado é apenas uma triagem orientativa baseada em inteligência artificial e não substitui um diagnóstico médico profissional. Procure sempre um oftalmologista para exames clínicos.</p>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-5">
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-5">Voltar ao Painel</a>
                    <a href="historico.php" class="btn btn-primary btn-lg px-5">Ver Histórico</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
