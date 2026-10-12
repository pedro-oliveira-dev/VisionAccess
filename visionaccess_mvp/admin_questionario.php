<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/admin_auth.php';
require_once 'includes/csrf.php';
include 'includes/header.php';

$mensagem = $_SESSION['admin_mensagem'] ?? null;
$erro = $_SESSION['admin_erro'] ?? null;
unset($_SESSION['admin_mensagem'], $_SESSION['admin_erro']);

$versaoAtiva = $pdo->query("SELECT * FROM questionario_versao WHERE status = 'ativo' ORDER BY versao DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$versaoRascunho = $pdo->query("SELECT * FROM questionario_versao WHERE status = 'rascunho' ORDER BY versao DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$versaoExibida = $versaoRascunho ?: $versaoAtiva;

$perguntas = [];
if ($versaoExibida) {
    $stmt = $pdo->prepare("
        SELECT
            p.id_pergunta,
            p.enunciado,
            p.ordem,
            p.ativo,
            c.nome AS categoria,
            COUNT(o.id_opcao) AS total_opcoes
        FROM pergunta p
        JOIN categoria_triagem c ON c.id_categoria = p.id_categoria
        LEFT JOIN opcao_resposta o ON o.id_pergunta = p.id_pergunta AND o.ativo = 1
        WHERE p.id_versao = ?
        GROUP BY p.id_pergunta, p.enunciado, p.ordem, p.ativo, c.nome
        ORDER BY p.ordem, p.id_pergunta
    ");
    $stmt->execute([(int) $versaoExibida['id_versao']]);
    $perguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$historicoVersoes = $pdo->query("SELECT versao, status, criado_em, publicado_em FROM questionario_versao ORDER BY versao DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="display-6 mb-1">Questionário da Triagem</h1>
        <p class="text-muted mb-0">Edite somente o rascunho. A versão ativa permanece imutável até uma nova publicação.</p>
    </div>
    <a href="admin.php" class="btn btn-outline-secondary">Voltar ao Admin</a>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div>
                        <h2 class="h4 mb-1">
                            <?php if ($versaoExibida): ?>
                                Versão <?php echo (int) $versaoExibida['versao']; ?>
                                <span class="badge <?php echo $versaoExibida['status'] === 'ativo' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo htmlspecialchars($versaoExibida['status']); ?>
                                </span>
                            <?php else: ?>
                                Nenhuma versão encontrada
                            <?php endif; ?>
                        </h2>
                        <?php if ($versaoRascunho): ?>
                            <small class="text-muted">Você está editando o rascunho. A versão ativa continua em produção.</small>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <?php if (!$versaoRascunho && $versaoAtiva): ?>
                            <form action="php/admin_questionario_action.php" method="POST">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="action" value="criar_rascunho">
                                <button class="btn btn-outline-primary" type="submit">Criar nova versão</button>
                            </form>
                        <?php endif; ?>

                        <?php if ($versaoRascunho): ?>
                            <a class="btn btn-primary" href="admin_pergunta.php?versao=<?php echo (int) $versaoRascunho['id_versao']; ?>">+ Nova pergunta</a>
                            <form action="php/admin_questionario_action.php" method="POST" onsubmit="return confirm('Publicar esta versão? Ela passará a ser usada nas novas triagens.');">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="action" value="publicar">
                                <input type="hidden" name="id_versao" value="<?php echo (int) $versaoRascunho['id_versao']; ?>">
                                <button class="btn btn-success" type="submit">Publicar versão</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!$versaoExibida): ?>
                    <div class="alert alert-warning">Execute a migração do banco antes de usar esta tela.</div>
                <?php elseif (empty($perguntas)): ?>
                    <div class="alert alert-warning">Esta versão ainda não possui perguntas.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:80px;">Ordem</th>
                                    <th>Pergunta</th>
                                    <th>Categoria</th>
                                    <th class="text-center">Opções</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($perguntas as $pergunta): ?>
                                    <tr>
                                        <td><?php echo (int) $pergunta['ordem']; ?></td>
                                        <td><?php echo htmlspecialchars($pergunta['enunciado']); ?></td>
                                        <td><?php echo htmlspecialchars($pergunta['categoria']); ?></td>
                                        <td class="text-center"><?php echo (int) $pergunta['total_opcoes']; ?></td>
                                        <td>
                                            <span class="badge <?php echo (int) $pergunta['ativo'] === 1 ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo (int) $pergunta['ativo'] === 1 ? 'Ativa' : 'Inativa'; ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($versaoRascunho): ?>
                                                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                    <a class="btn btn-sm btn-outline-primary" href="admin_pergunta.php?id=<?php echo (int) $pergunta['id_pergunta']; ?>&versao=<?php echo (int) $versaoRascunho['id_versao']; ?>">Editar</a>

                                                    <form action="php/admin_questionario_action.php" method="POST">
                                                        <?php echo csrfInput(); ?>
                                                        <input type="hidden" name="action" value="alternar_pergunta">
                                                        <input type="hidden" name="id_pergunta" value="<?php echo (int) $pergunta['id_pergunta']; ?>">
                                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                            <?php echo (int) $pergunta['ativo'] === 1 ? 'Desativar' : 'Ativar'; ?>
                                                        </button>
                                                    </form>

                                                    <form action="php/admin_questionario_action.php" method="POST" onsubmit="return confirm('Excluir esta pergunta do rascunho?');">
                                                        <?php echo csrfInput(); ?>
                                                        <input type="hidden" name="action" value="excluir_pergunta">
                                                        <input type="hidden" name="id_pergunta" value="<?php echo (int) $pergunta['id_pergunta']; ?>">
                                                        <button class="btn btn-sm btn-outline-danger" type="submit">Excluir</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">Somente leitura</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="h5">Histórico de versões</h2>
                <div class="list-group list-group-flush">
                    <?php foreach ($historicoVersoes as $versao): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between">
                                <strong>Versão <?php echo (int) $versao['versao']; ?></strong>
                                <span class="badge <?php echo $versao['status'] === 'ativo' ? 'bg-success' : ($versao['status'] === 'rascunho' ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                    <?php echo htmlspecialchars($versao['status']); ?>
                                </span>
                            </div>
                            <small class="text-muted">Criada em <?php echo date('d/m/Y H:i', strtotime($versao['criado_em'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-warning border-0 shadow-sm">
    <strong>Regra importante:</strong> ao publicar, o sistema valida se todas as categorias possuem pelo menos uma pergunta ativa e se cada pergunta ativa possui pelo menos duas opções de resposta.
</div>

<?php include 'includes/footer.php'; ?>
