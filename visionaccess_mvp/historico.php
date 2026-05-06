<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'includes/db_connect.php';
include 'includes/header.php';

// Buscar histórico de triagens do usuário considerando o novo banco de dados.
// Agora a tabela resultado possui campos separados para IA, regra interna e resultado final.
$stmt = $pdo->prepare("
    SELECT 
        t.id_triagem,
        t.data_triagem,
        r.resultado_final,
        r.classificacao_ia,
        r.confianca_ia,
        r.classificacao_interna,
        r.pontuacao_interna,
        r.nivel_risco
    FROM triagem t
    LEFT JOIN resultado r ON t.id_triagem = r.id_triagem
    WHERE t.id_usuario = ?
    ORDER BY t.data_triagem DESC
");
$stmt->execute([$_SESSION['user_id']]);
$triagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row justify-content-center">
    <div class="col-md-11">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Histórico de Triagens</h2>
                <p class="text-muted text-center mb-4">
                    Acompanhe suas triagens anteriores, incluindo o resultado da IA e a análise interna por pontuação.
                </p>

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
                                    <th>Resultado Final</th>
                                    <th>IA</th>
                                    <th>Confiança IA</th>
                                    <th>Regra Interna</th>
                                    <th>Nível</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($triagens as $triagem): ?>
                                    <tr>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($triagem['data_triagem'])); ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($triagem['resultado_final'])): ?>
                                                <span class="badge bg-primary px-3 py-2">
                                                    <?php echo htmlspecialchars($triagem['resultado_final']); ?>
                                                </span>
                                            <?php elseif (!empty($triagem['classificacao_ia']) || !empty($triagem['classificacao_interna'])): ?>
                                                <span class="badge bg-secondary px-3 py-2">
                                                    <?php 
                                                        echo htmlspecialchars(
                                                            $triagem['classificacao_ia'] 
                                                            ?? $triagem['classificacao_interna'] 
                                                            ?? 'Resultado parcial'
                                                        ); 
                                                    ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-3 py-2">Pendente</span>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($triagem['classificacao_ia'])): ?>
                                                <?php echo htmlspecialchars($triagem['classificacao_ia']); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if ($triagem['confianca_ia'] !== null && $triagem['confianca_ia'] !== ''): ?>
                                                <?php echo number_format((float)$triagem['confianca_ia'], 2, ',', '.'); ?>%
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($triagem['classificacao_interna'])): ?>
                                                <?php echo htmlspecialchars($triagem['classificacao_interna']); ?>
                                                <?php if ($triagem['pontuacao_interna'] !== null && $triagem['pontuacao_interna'] !== ''): ?>
                                                    <small class="text-muted">
                                                        (<?php echo (int)$triagem['pontuacao_interna']; ?> pts)
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <?php if (!empty($triagem['nivel_risco'])): ?>
                                                <?php
                                                    $classeNivel = 'bg-secondary';
                                                    $nivel = mb_strtolower($triagem['nivel_risco'], 'UTF-8');

                                                    if ($nivel === 'alto') {
                                                        $classeNivel = 'bg-danger';
                                                    } elseif ($nivel === 'moderado') {
                                                        $classeNivel = 'bg-warning text-dark';
                                                    } elseif ($nivel === 'baixo') {
                                                        $classeNivel = 'bg-success';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $classeNivel; ?> px-3 py-2">
                                                    <?php echo htmlspecialchars($triagem['nivel_risco']); ?>
                                                </span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-end">
                                            <a href="resultado.php?id=<?php echo (int)$triagem['id_triagem']; ?>" class="btn btn-sm btn-outline-primary px-3">
                                                Ver Detalhes
                                            </a>
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
