<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/admin_auth.php';
require_once 'includes/csrf.php';
include 'includes/header.php';

$mensagem = $_SESSION['admin_mensagem'] ?? null;
$erro = $_SESSION['admin_erro'] ?? null;
unset($_SESSION['admin_mensagem'], $_SESSION['admin_erro']);

$promptAtivo = $pdo->query("SELECT p.*, u.nome AS criado_por_nome FROM prompt_agente p LEFT JOIN usuario u ON u.id_usuario = p.criado_por WHERE p.ativo = 1 ORDER BY p.versao DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$historico = $pdo->query("SELECT p.*, u.nome AS criado_por_nome FROM prompt_agente p LEFT JOIN usuario u ON u.id_usuario = p.criado_por ORDER BY p.versao DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h1 class="display-6 mb-1">Prompt do Agente</h1>
        <p class="text-muted mb-0">Cada salvamento cria uma nova versão e preserva as versões anteriores.</p>
    </div>
    <a href="admin.php" class="btn btn-outline-secondary">Voltar ao Admin</a>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="h4">Criar nova versão</h2>
                <p class="text-muted small">
                    O texto abaixo controla as instruções de análise. O backend continua impondo o contrato JSON e a lista de classificações aceitas para evitar quebrar o processamento.
                </p>

                <form action="php/admin_prompt_action.php" method="POST">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="action" value="salvar">

                    <div class="mb-3">
                        <label for="nome" class="form-label fw-bold">Nome</label>
                        <input class="form-control" type="text" id="nome" name="nome" maxlength="120" required value="<?php echo htmlspecialchars($promptAtivo['nome'] ?? 'Análise orientativa visual'); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="conteudo" class="form-label fw-bold">Instruções do agente</label>
                        <textarea class="form-control font-monospace" id="conteudo" name="conteudo" rows="16" maxlength="12000" required><?php echo htmlspecialchars($promptAtivo['conteudo'] ?? ''); ?></textarea>
                    </div>

                    <div class="alert alert-light border">
                        <strong>Dados enviados separadamente ao agente:</strong>
                        <div class="small mt-2">
                            relato textual do usuário + respostas objetivas com pergunta, categoria, opção selecionada e pontuação.
                        </div>
                    </div>

                    <div class="alert alert-info border-0">
                        <strong>Contrato fixo mantido pelo sistema:</strong>
                        <ul class="mb-0 mt-2">
                            <li>retorno exclusivamente em JSON;</li>
                            <li>classificação entre Miopia, Hipermetropia, Astigmatismo, Presbiopia, Condição ocular inespecífica ou Sem classificação clara;</li>
                            <li>confiança de 0 a 100;</li>
                            <li>descrição curta e alerta de caráter orientativo.</li>
                        </ul>
                    </div>

                    <button class="btn btn-primary" type="submit">Salvar e ativar nova versão</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h2 class="h5">Versão ativa</h2>
                <?php if ($promptAtivo): ?>
                    <p class="mb-1"><strong><?php echo htmlspecialchars($promptAtivo['nome']); ?></strong></p>
                    <p class="mb-1">Versão <?php echo (int) $promptAtivo['versao']; ?></p>
                    <small class="text-muted">Criada em <?php echo date('d/m/Y H:i', strtotime($promptAtivo['criado_em'])); ?></small>
                <?php else: ?>
                    <div class="alert alert-warning mb-0">Nenhum prompt ativo.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h2 class="h5">Histórico</h2>
                <div class="list-group list-group-flush prompt-history">
                    <?php foreach ($historico as $item): ?>
                        <details class="list-group-item px-0">
                            <summary class="d-flex justify-content-between align-items-center gap-2">
                                <span>v<?php echo (int) $item['versao']; ?> - <?php echo htmlspecialchars($item['nome']); ?></span>
                                <?php if ((int) $item['ativo'] === 1): ?>
                                    <span class="badge bg-success">ativa</span>
                                <?php endif; ?>
                            </summary>
                            <div class="small text-muted mt-2 mb-2">
                                <?php echo date('d/m/Y H:i', strtotime($item['criado_em'])); ?>
                                <?php if (!empty($item['criado_por_nome'])): ?>
                                    · <?php echo htmlspecialchars($item['criado_por_nome']); ?>
                                <?php endif; ?>
                            </div>
                            <pre class="admin-prompt-preview mb-0"><?php echo htmlspecialchars($item['conteudo']); ?></pre>
                        </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
