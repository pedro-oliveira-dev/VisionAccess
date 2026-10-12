<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require_once 'includes/db_connect.php';
require_once 'includes/csrf.php';
include 'includes/header.php';

$versaoQuestionario = $pdo->query("
    SELECT id_versao, versao
    FROM questionario_versao
    WHERE status = 'ativo'
    ORDER BY versao DESC
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

$perguntas = [];

if ($versaoQuestionario) {
    $stmt = $pdo->prepare("
        SELECT
            p.id_pergunta,
            p.enunciado,
            p.ordem AS pergunta_ordem,
            c.nome AS categoria,
            o.id_opcao,
            o.texto AS opcao_texto,
            o.ordem AS opcao_ordem
        FROM pergunta p
        JOIN categoria_triagem c ON c.id_categoria = p.id_categoria
        JOIN opcao_resposta o ON o.id_pergunta = p.id_pergunta
        WHERE p.id_versao = ?
          AND p.ativo = 1
          AND o.ativo = 1
        ORDER BY p.ordem, p.id_pergunta, o.ordem, o.id_opcao
    ");
    $stmt->execute([(int) $versaoQuestionario['id_versao']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $idPergunta = (int) $linha['id_pergunta'];

        if (!isset($perguntas[$idPergunta])) {
            $perguntas[$idPergunta] = [
                'id_pergunta' => $idPergunta,
                'enunciado' => $linha['enunciado'],
                'categoria' => $linha['categoria'],
                'opcoes' => [],
            ];
        }

        $perguntas[$idPergunta]['opcoes'][] = [
            'id_opcao' => (int) $linha['id_opcao'],
            'texto' => $linha['opcao_texto'],
        ];
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="text-center mb-4">Triagem Orientativa</h2>
                <p class="text-muted text-center mb-4">Responda com sinceridade para receber a melhor orientação possível.</p>

                <?php if (isset($_SESSION['erro_triagem'])): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($_SESSION['erro_triagem']); unset($_SESSION['erro_triagem']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['sucesso_triagem'])): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($_SESSION['sucesso_triagem']); unset($_SESSION['sucesso_triagem']); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$versaoQuestionario): ?>
                    <div class="alert alert-warning">
                        Nenhum questionário ativo está publicado. Entre em contato com a administração do sistema.
                    </div>
                <?php elseif (empty($perguntas)): ?>
                    <div class="alert alert-warning">
                        A versão ativa do questionário não possui perguntas disponíveis.
                    </div>
                <?php else: ?>
                    <form action="php/processar_triagem.php" method="POST" id="triagem-form">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="id_questionario_versao" value="<?php echo (int) $versaoQuestionario['id_versao']; ?>">

                        <div class="mb-4">
                            <label for="descricao_texto" class="form-label fw-bold">Descreva suas dificuldades visuais em texto livre:</label>
                            <textarea class="form-control" id="descricao_texto" name="descricao_texto" rows="4" maxlength="1000" required placeholder="Ex: Sinto minha visão embaçada ao ler livros e tenho dores de cabeça frequentes no final do dia..."></textarea>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 fw-bold">Questionário Complementar</h5>
                            <span class="badge bg-light text-dark border">Versão <?php echo (int) $versaoQuestionario['versao']; ?></span>
                        </div>

                        <?php foreach ($perguntas as $pergunta): ?>
                            <fieldset class="mb-4">
                                <legend class="h6 mb-1 fw-semibold"><?php echo htmlspecialchars($pergunta['enunciado']); ?></legend>
                                <small class="text-muted d-block mb-2">Categoria: <?php echo htmlspecialchars($pergunta['categoria']); ?></small>

                                <?php foreach ($pergunta['opcoes'] as $opcao): ?>
                                    <?php $inputId = 'p' . $pergunta['id_pergunta'] . '_o' . $opcao['id_opcao']; ?>
                                    <div class="form-check form-check-inline mb-2">
                                        <input
                                            class="form-check-input"
                                            type="radio"
                                            name="respostas[<?php echo (int) $pergunta['id_pergunta']; ?>]"
                                            id="<?php echo htmlspecialchars($inputId); ?>"
                                            value="<?php echo (int) $opcao['id_opcao']; ?>"
                                            required
                                        >
                                        <label class="form-check-label" for="<?php echo htmlspecialchars($inputId); ?>">
                                            <?php echo htmlspecialchars($opcao['texto']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-primary btn-lg">Enviar para Análise</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
