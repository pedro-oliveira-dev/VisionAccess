<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/admin_auth.php';
include 'includes/header.php';

$totais = [
    'usuarios' => (int) $pdo->query("SELECT COUNT(*) FROM usuario")->fetchColumn(),
    'triagens' => (int) $pdo->query("SELECT COUNT(*) FROM triagem")->fetchColumn(),
    'perguntas' => (int) $pdo->query("SELECT COUNT(*) FROM pergunta p JOIN questionario_versao qv ON qv.id_versao = p.id_versao WHERE qv.status = 'ativo' AND p.ativo = 1")->fetchColumn(),
];

$versaoAtiva = $pdo->query("SELECT id_versao, versao, publicado_em FROM questionario_versao WHERE status = 'ativo' ORDER BY versao DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$promptAtivo = $pdo->query("SELECT id_prompt, nome, versao, criado_em FROM prompt_agente WHERE ativo = 1 ORDER BY versao DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="display-6 mb-1">Painel Administrativo</h1>
        <p class="text-muted mb-0">Gerencie o questionário utilizado na triagem e o prompt de análise do agente.</p>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <small class="text-muted">Usuários cadastrados</small>
                <div class="display-6 fw-bold"><?php echo $totais['usuarios']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <small class="text-muted">Triagens realizadas</small>
                <div class="display-6 fw-bold"><?php echo $totais['triagens']; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <small class="text-muted">Perguntas ativas</small>
                <div class="display-6 fw-bold"><?php echo $totais['perguntas']; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-4">
                <h2 class="h4">Questionário</h2>
                <?php if ($versaoAtiva): ?>
                    <p class="mb-1"><strong>Versão ativa:</strong> <?php echo (int) $versaoAtiva['versao']; ?></p>
                    <p class="text-muted small">Uma nova edição é feita em rascunho e só afeta usuários depois da publicação.</p>
                <?php else: ?>
                    <div class="alert alert-warning">Nenhuma versão ativa do questionário foi encontrada.</div>
                <?php endif; ?>
                <a href="admin_questionario.php" class="btn btn-primary">Gerenciar questionário</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-4">
                <h2 class="h4">Agente de IA</h2>
                <?php if ($promptAtivo): ?>
                    <p class="mb-1"><strong>Prompt:</strong> <?php echo htmlspecialchars($promptAtivo['nome']); ?></p>
                    <p class="mb-1"><strong>Versão:</strong> <?php echo (int) $promptAtivo['versao']; ?></p>
                    <p class="text-muted small">Novas versões são registradas sem sobrescrever o histórico.</p>
                <?php else: ?>
                    <div class="alert alert-warning">Nenhum prompt ativo foi encontrado.</div>
                <?php endif; ?>
                <a href="admin_prompt.php" class="btn btn-primary">Gerenciar prompt</a>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info border-0 shadow-sm mt-4">
    <strong>Rastreabilidade:</strong> versões publicadas do questionário e do prompt não são editadas diretamente. Isso preserva o contexto usado nas novas triagens.
</div>

<?php include 'includes/footer.php'; ?>
