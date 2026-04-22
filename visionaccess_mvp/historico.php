<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'includes/db_connect.php';
include 'includes/header.php';

// Buscar histórico de triagens do usuário
$stmt = $pdo->prepare("SELECT t.id_triagem, t.data_triagem, r.classificacao, r.confianca FROM triagem t LEFT JOIN resultado r ON t.id_triagem = r.id_triagem WHERE t.id_usuario = ? ORDER BY t.data_triagem DESC");
$stmt->execute([$_SESSION['user_id']]);
$triagens = $stmt->fetchAll();
?>

<div class="row justify-content-center">
    <div class="col-md-10">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Histórico de Triagens</h2>
                <p class="text-muted text-center mb-4">Acompanhe suas avaliações anteriores e resultados.</p>
                
                <?php if (empty($triagens)): ?>
                    <div class="alert alert-info text-center py-5">
                        <p class="lead mb-4">Você ainda não realizou nenhuma triagem.</p>
                        <a href="triagem.php" class="btn btn-primary btn-lg px-5">Realizar Minha Primeira Triagem</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th>Classificação</th>
                                    <th>Confiança</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($triagens as $triagem): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($triagem['data_triagem'])); ?></td>
                                        <td>
                                            <?php if ($triagem['classificacao']): ?>
                                                <span class="badge bg-primary px-3 py-2"><?php echo htmlspecialchars($triagem['classificacao']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-3 py-2">Pendente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($triagem['confianca']): ?>
                                                <?php echo number_format($triagem['confianca'], 2); ?>%
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="resultado.php?id=<?php echo $triagem['id_triagem']; ?>" class="btn btn-sm btn-outline-primary px-3">Ver Detalhes</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-5">
                    <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-5">Voltar ao Painel</a>
                    <a href="triagem.php" class="btn btn-primary btn-lg px-5">Nova Triagem</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
