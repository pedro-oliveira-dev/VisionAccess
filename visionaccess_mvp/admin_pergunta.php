<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/admin_auth.php';
require_once 'includes/csrf.php';
include 'includes/header.php';

$idVersao = filter_input(INPUT_GET, 'versao', FILTER_VALIDATE_INT);
$idPergunta = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idVersao) {
    echo '<div class="alert alert-danger">Versão inválida.</div>';
    include 'includes/footer.php';
    exit();
}

$stmtVersao = $pdo->prepare("SELECT * FROM questionario_versao WHERE id_versao = ? AND status = 'rascunho'");
$stmtVersao->execute([$idVersao]);
$versao = $stmtVersao->fetch(PDO::FETCH_ASSOC);

if (!$versao) {
    echo '<div class="alert alert-danger">Somente versões em rascunho podem ser editadas.</div>';
    include 'includes/footer.php';
    exit();
}

$categorias = $pdo->query('SELECT id_categoria, nome FROM categoria_triagem ORDER BY id_categoria')->fetchAll(PDO::FETCH_ASSOC);

$pergunta = [
    'id_pergunta' => null,
    'id_categoria' => '',
    'enunciado' => '',
    'ordem' => 1,
    'ativo' => 1,
];
$opcoes = [
    ['texto' => 'Nunca', 'pontuacao' => 0, 'ordem' => 1, 'ativo' => 1],
    ['texto' => 'Raramente', 'pontuacao' => 1, 'ordem' => 2, 'ativo' => 1],
    ['texto' => 'Às vezes', 'pontuacao' => 2, 'ordem' => 3, 'ativo' => 1],
    ['texto' => 'Sempre', 'pontuacao' => 3, 'ordem' => 4, 'ativo' => 1],
];

if ($idPergunta) {
    $stmtPergunta = $pdo->prepare('SELECT * FROM pergunta WHERE id_pergunta = ? AND id_versao = ?');
    $stmtPergunta->execute([$idPergunta, $idVersao]);
    $perguntaEncontrada = $stmtPergunta->fetch(PDO::FETCH_ASSOC);

    if (!$perguntaEncontrada) {
        echo '<div class="alert alert-danger">Pergunta não encontrada neste rascunho.</div>';
        include 'includes/footer.php';
        exit();
    }

    $pergunta = $perguntaEncontrada;

    $stmtOpcoes = $pdo->prepare('SELECT texto, pontuacao, ordem, ativo FROM opcao_resposta WHERE id_pergunta = ? ORDER BY ordem, id_opcao');
    $stmtOpcoes->execute([$idPergunta]);
    $opcoes = $stmtOpcoes->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="display-6 mb-1"><?php echo $idPergunta ? 'Editar pergunta' : 'Nova pergunta'; ?></h1>
        <p class="text-muted mb-0">Questionário versão <?php echo (int) $versao['versao']; ?> - rascunho.</p>
    </div>
    <a href="admin_questionario.php" class="btn btn-outline-secondary">Voltar</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <form action="php/admin_questionario_action.php" method="POST" id="form-pergunta">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="action" value="salvar_pergunta">
            <input type="hidden" name="id_versao" value="<?php echo (int) $idVersao; ?>">
            <?php if ($idPergunta): ?>
                <input type="hidden" name="id_pergunta" value="<?php echo (int) $idPergunta; ?>">
            <?php endif; ?>

            <div class="row g-3 mb-3">
                <div class="col-md-8">
                    <label for="enunciado" class="form-label fw-bold">Enunciado</label>
                    <textarea class="form-control" id="enunciado" name="enunciado" rows="3" maxlength="255" required><?php echo htmlspecialchars($pergunta['enunciado']); ?></textarea>
                </div>
                <div class="col-md-4">
                    <label for="id_categoria" class="form-label fw-bold">Categoria</label>
                    <select class="form-select" id="id_categoria" name="id_categoria" required>
                        <option value="">Selecione</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?php echo (int) $categoria['id_categoria']; ?>" <?php echo (int) $pergunta['id_categoria'] === (int) $categoria['id_categoria'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($categoria['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="ordem" class="form-label fw-bold mt-3">Ordem</label>
                    <input class="form-control" type="number" id="ordem" name="ordem" min="1" max="999" value="<?php echo (int) $pergunta['ordem']; ?>" required>

                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" <?php echo (int) $pergunta['ativo'] === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ativo">Pergunta ativa</label>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h2 class="h5 mb-1">Opções de resposta</h2>
                    <small class="text-muted">A pontuação é usada pela regra interna. O valor máximo de cada pergunta é normalizado no cálculo.</small>
                </div>
                <button class="btn btn-sm btn-outline-primary" type="button" id="adicionar-opcao">+ Adicionar opção</button>
            </div>

            <div id="lista-opcoes">
                <?php foreach ($opcoes as $indice => $opcao): ?>
                    <div class="row g-2 align-items-end mb-2 opcao-row">
                        <div class="col-md-6">
                            <label class="form-label small">Texto</label>
                            <input class="form-control" type="text" name="opcao_texto[]" maxlength="100" value="<?php echo htmlspecialchars($opcao['texto']); ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Pontuação</label>
                            <input class="form-control" type="number" name="opcao_pontuacao[]" min="0" max="100" value="<?php echo (int) $opcao['pontuacao']; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Ordem</label>
                            <input class="form-control" type="number" name="opcao_ordem[]" min="1" max="999" value="<?php echo (int) $opcao['ordem']; ?>" required>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-danger w-100 remover-opcao" type="button">Remover</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="alert alert-light border mt-4">
                Recomenda-se manter uma escala crescente e coerente, por exemplo: Nunca = 0, Raramente = 1, Às vezes = 2 e Sempre = 3.
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="admin_questionario.php" class="btn btn-outline-secondary">Cancelar</a>
                <button class="btn btn-primary" type="submit">Salvar pergunta</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/admin.js"></script>
<?php include 'includes/footer.php'; ?>
