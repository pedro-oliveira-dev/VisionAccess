<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

require_once 'includes/db_connect.php';
include 'includes/header.php';

// Buscar perguntas ativas e suas opções de resposta com pontuação.
// A pontuação não é exibida ao usuário; ela será usada apenas no processamento interno.
$stmt = $pdo->query("
    SELECT
        p.id_pergunta,
        p.enunciado,
        c.nome AS categoria,
        o.id_opcao,
        o.texto AS opcao_texto
    FROM pergunta p
    JOIN categoria_triagem c ON c.id_categoria = p.id_categoria
    JOIN opcao_resposta o ON o.id_pergunta = p.id_pergunta
    WHERE p.ativo = TRUE
    ORDER BY p.id_pergunta, o.id_opcao
");

$linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupa as opções por pergunta para facilitar a renderização do formulário.
$perguntas = [];
foreach ($linhas as $linha) {
    $idPergunta = $linha['id_pergunta'];

    if (!isset($perguntas[$idPergunta])) {
        $perguntas[$idPergunta] = [
            'id_pergunta' => $linha['id_pergunta'],
            'enunciado' => $linha['enunciado'],
            'categoria' => $linha['categoria'],
            'opcoes' => []
        ];
    }

    $perguntas[$idPergunta]['opcoes'][] = [
        'id_opcao' => $linha['id_opcao'],
        'texto' => $linha['opcao_texto']
    ];
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
                    <!-- Etapa 1: Descrição Livre enviada para a IA -->
                    <div class="mb-4">
                        <label for="descricao_texto" class="form-label fw-bold">Descreva suas dificuldades visuais em texto livre:</label>
                        <textarea class="form-control" id="descricao_texto" name="descricao_texto" rows="4" required placeholder="Ex: Sinto minha visão embaçada ao ler livros e tenho dores de cabeça frequentes no final do dia..."></textarea>
                    </div>

                    <hr class="my-4">

                    <!-- Etapa 2: Questionário Complementar pontuado por regras internas -->
                    <h5 class="mb-3 fw-bold">Questionário Complementar:</h5>

                    <?php if (empty($perguntas)): ?>
                        <div class="alert alert-warning">
                            Nenhuma pergunta ativa foi encontrada. Verifique o cadastro de perguntas e opções de resposta no banco de dados.
                        </div>
                    <?php endif; ?>

                    <?php foreach ($perguntas as $pergunta): ?>
                        <div class="mb-4">
                            <p class="mb-1 fw-semibold"><?php echo htmlspecialchars($pergunta['enunciado']); ?></p>
                            <small class="text-muted d-block mb-2">Categoria: <?php echo htmlspecialchars($pergunta['categoria']); ?></small>

                            <?php foreach ($pergunta['opcoes'] as $opcao): ?>
                                <?php
                                    $inputId = 'p' . $pergunta['id_pergunta'] . '_o' . $opcao['id_opcao'];
                                ?>
                                <div class="form-check form-check-inline">
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
                        </div>
                    <?php endforeach; ?>

                    <div class="d-grid mt-5">
                        <button type="submit" class="btn btn-primary btn-lg" <?php echo empty($perguntas) ? 'disabled' : ''; ?>>Enviar para Análise</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
