<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
require_once 'includes/db_connect.php';
include 'includes/header.php';

// Buscar perguntas do banco de dados
$stmt = $pdo->query("SELECT * FROM pergunta");
$perguntas = $stmt->fetchAll();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Triagem Orientativa</h2>
                <p class="text-muted text-center mb-4">Responda com sinceridade para receber a melhor orientação possível.</p>
                
                <?php if (isset($_SESSION['erro_triagem'])): ?>
                    <div class="alert alert-danger">
                        <?php
                            echo htmlspecialchars($_SESSION['erro_triagem']);
                            unset($_SESSION['erro_triagem']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['sucesso_triagem'])): ?>
                    <div class="alert alert-success">
                        <?php
                            echo htmlspecialchars($_SESSION['sucesso_triagem']);
                            unset($_SESSION['sucesso_triagem']);
                        ?>
                    </div>
                <?php endif; ?>

                <form action="php/processar_triagem.php" method="POST" id="triagem-form">
                    <!-- Etapa 1: Descrição Livre (RF04) -->
                    <div class="mb-4">
                        <label for="descricao_texto" class="form-label fw-bold">Descreva suas dificuldades visuais em texto livre:</label>
                        <textarea class="form-control" id="descricao_texto" name="descricao_texto" rows="4" required placeholder="Ex: Sinto minha visão embaçada ao ler livros e tenho dores de cabeça frequentes no final do dia..."></textarea>
                    </div>

                    <hr class="my-4">

                    <!-- Etapa 2: Questionário Complementar (RF05) -->
                    <h5 class="mb-3 fw-bold">Questionário Complementar:</h5>
                    <?php foreach ($perguntas as $pergunta): ?>
                        <div class="mb-3">
                            <p class="mb-2"><?php echo htmlspecialchars($pergunta['enunciado']); ?></p>
                            <?php if ($pergunta['tipo'] === 'sim_nao'): ?>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="pergunta_<?php echo $pergunta['id_pergunta']; ?>" id="p<?php echo $pergunta['id_pergunta']; ?>_sim" value="Sim" required>
                                    <label class="form-check-label" for="p<?php echo $pergunta['id_pergunta']; ?>_sim">Sim</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="pergunta_<?php echo $pergunta['id_pergunta']; ?>" id="p<?php echo $pergunta['id_pergunta']; ?>_nao" value="Não" required>
                                    <label class="form-check-label" for="p<?php echo $pergunta['id_pergunta']; ?>_nao">Não</label>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="d-grid mt-5">
                        <button type="submit" class="btn btn-primary btn-lg">Enviar para Análise</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
