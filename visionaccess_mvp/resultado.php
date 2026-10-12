<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'includes/db_connect.php';
include 'includes/header.php';

$triagem_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$triagem_id) {
    echo "<div class='alert alert-warning'>Triagem inválida.</div>";
    include 'includes/footer.php';
    exit();
}

$stmt = $pdo->prepare("
    SELECT
        r.*,
        t.descricao_texto,
        t.data_triagem,
        qv.versao AS questionario_versao,
        pa.versao AS prompt_versao
    FROM resultado r
    JOIN triagem t ON r.id_triagem = t.id_triagem
    LEFT JOIN questionario_versao qv ON qv.id_versao = t.id_questionario_versao
    LEFT JOIN prompt_agente pa ON pa.id_prompt = r.id_prompt
    WHERE r.id_triagem = ?
      AND t.id_usuario = ?
");
$stmt->execute([$triagem_id, $_SESSION['user_id']]);
$resultado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resultado) {
    echo "<div class='alert alert-warning'>Resultado não encontrado ou você não tem permissão para vê-lo.</div>";
    include 'includes/footer.php';
    exit();
}

$stmtPontuacao = $pdo->prepare("
    SELECT
        c.nome AS categoria,
        pt.pontuacao_total,
        pt.pontuacao_maxima,
        pt.percentual
    FROM pontuacao_triagem pt
    JOIN categoria_triagem c ON c.id_categoria = pt.id_categoria
    WHERE pt.id_triagem = ?
    ORDER BY pt.percentual DESC, pt.pontuacao_total DESC, c.nome ASC
");
$stmtPontuacao->execute([$triagem_id]);
$pontuacoes = $stmtPontuacao->fetchAll(PDO::FETCH_ASSOC);

$classificacaoIA = $resultado['classificacao_ia'] ?? 'Não informado';
$confiancaIA = $resultado['confianca_ia'] ?? null;
$descricaoIA = $resultado['descricao_ia'] ?? null;
$resultadoFinal = $resultado['resultado_final'] ?? $classificacaoIA;
$descricaoResultado = $resultado['descricao_resultado'] ?? '';
$classificacaoInterna = $resultado['classificacao_interna'] ?? null;
$pontuacaoInterna = $resultado['pontuacao_interna'] ?? null;
$percentualInterno = $resultado['percentual_interno'] ?? null;
$nivelRisco = $resultado['nivel_risco'] ?? null;
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Resultado da Triagem</h2>
                <p class="text-muted text-center mb-4">
                    Realizada em: <?php echo date('d/m/Y H:i', strtotime($resultado['data_triagem'])); ?>
                    <?php if (!empty($resultado['questionario_versao'])): ?>
                        · Questionário v<?php echo (int) $resultado['questionario_versao']; ?>
                    <?php endif; ?>
                </p>

                <div class="alert alert-primary border-0 shadow-sm mb-4">
                    <h4 class="alert-heading">Resultado Orientativo:</h4>
                    <p class="display-6 fw-bold mb-1"><?php echo htmlspecialchars($resultadoFinal); ?></p>
                    <?php if ($confiancaIA !== null): ?>
                        <small>Nível de confiança informado pela IA: <?php echo number_format((float) $confiancaIA, 2, ',', '.'); ?>%</small>
                    <?php endif; ?>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-light">
                            <div class="card-body">
                                <h5 class="fw-bold">Análise da IA</h5>
                                <p class="mb-1"><strong>Classificação:</strong> <?php echo htmlspecialchars($classificacaoIA); ?></p>
                                <?php if ($confiancaIA !== null): ?>
                                    <p class="mb-1"><strong>Confiança:</strong> <?php echo number_format((float) $confiancaIA, 2, ',', '.'); ?>%</p>
                                <?php endif; ?>
                                <?php if (!empty($descricaoIA)): ?>
                                    <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($descricaoIA)); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 border-0 bg-light">
                            <div class="card-body">
                                <h5 class="fw-bold">Regra Interna</h5>
                                <?php if ($classificacaoInterna || $pontuacaoInterna !== null || $nivelRisco): ?>
                                    <?php if ($classificacaoInterna): ?>
                                        <p class="mb-1"><strong>Categoria principal:</strong> <?php echo htmlspecialchars($classificacaoInterna); ?></p>
                                    <?php endif; ?>
                                    <?php if ($pontuacaoInterna !== null): ?>
                                        <p class="mb-1"><strong>Pontuação:</strong> <?php echo (int) $pontuacaoInterna; ?> ponto(s)</p>
                                    <?php endif; ?>
                                    <?php if ($percentualInterno !== null): ?>
                                        <p class="mb-1"><strong>Intensidade normalizada:</strong> <?php echo number_format((float) $percentualInterno, 2, ',', '.'); ?>%</p>
                                    <?php endif; ?>
                                    <?php if ($nivelRisco): ?>
                                        <p class="mb-0"><strong>Nível:</strong> <?php echo htmlspecialchars($nivelRisco); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="mb-0 text-muted">A pontuação interna ainda não foi registrada para esta triagem.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <h5 class="fw-bold">Análise Detalhada:</h5>
                    <p class="lead"><?php echo nl2br(htmlspecialchars($descricaoResultado)); ?></p>
                </div>

                <?php if (!empty($pontuacoes)): ?>
                    <div class="mb-4">
                        <h5 class="fw-bold">Pontuação por Categoria:</h5>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Categoria</th>
                                        <th class="text-center">Pontuação</th>
                                        <th class="text-center">Percentual</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pontuacoes as $pontuacao): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($pontuacao['categoria']); ?></td>
                                            <td class="text-center fw-bold">
                                                <?php echo (int) $pontuacao['pontuacao_total']; ?> / <?php echo (int) $pontuacao['pontuacao_maxima']; ?>
                                            </td>
                                            <td class="text-center"><?php echo number_format((float) $pontuacao['percentual'], 2, ',', '.'); ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card bg-light border-0 mb-4">
                    <div class="card-body">
                        <h6 class="fw-bold">Seu Relato:</h6>
                        <p class="mb-0 text-muted fst-italic">&quot;<?php echo htmlspecialchars($resultado['descricao_texto']); ?>&quot;</p>
                    </div>
                </div>

                <div class="alert alert-warning border-0 shadow-sm">
                    <h5 class="alert-heading">Aviso Importante:</h5>
                    <p class="mb-0">Este resultado é apenas uma triagem orientativa baseada em inteligência artificial e regras internas da aplicação. Ele não substitui um diagnóstico médico profissional. Procure um oftalmologista para avaliação clínica.</p>
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
