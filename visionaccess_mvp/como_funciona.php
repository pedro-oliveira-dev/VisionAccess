<?php
session_start();
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-5">
                <h1 class="text-center mb-5 text-primary">Como funciona a triagem?</h1>

                <p class="lead mb-5">A triagem do VisionAccess combina um relato livre do usuário com um questionário complementar estruturado. O objetivo é oferecer uma orientação inicial, sem substituir avaliação profissional.</p>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3"><span class="text-primary me-2">01.</span> Relato da dificuldade visual</h2>
                    <p>Na primeira etapa, o usuário descreve com suas próprias palavras os sintomas, desconfortos ou dificuldades visuais que está enfrentando.</p>
                </section>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3"><span class="text-primary me-2">02.</span> Questionário complementar</h2>
                    <p>O usuário responde perguntas objetivas relacionadas a cinco grupos utilizados pela regra interna:</p>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li>👁️ Visão de longe</li>
                                <li>📖 Visão de perto</li>
                                <li>🌀 Astigmatismo</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-group list-group-flush">
                                <li>🏥 Condições oculares</li>
                                <li>🧠 Sintomas gerais</li>
                            </ul>
                        </div>
                    </div>
                    <p>As alternativas e suas pontuações são definidas no questionário publicado. A configuração padrão utiliza:</p>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered text-center align-middle">
                            <thead class="table-light"><tr><th>Resposta padrão</th><th>Pontuação</th></tr></thead>
                            <tbody>
                                <tr><td>Nunca</td><td>0</td></tr>
                                <tr><td>Raramente</td><td>1</td></tr>
                                <tr><td>Às vezes</td><td>2</td></tr>
                                <tr><td>Sempre</td><td>3</td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3"><span class="text-primary me-2">03.</span> Processamento das respostas</h2>
                    <p>O sistema soma os pontos de cada categoria e também calcula qual seria a pontuação máxima possível para as perguntas respondidas naquela categoria.</p>
                    <p>Com isso, a comparação é feita por <strong>percentual normalizado</strong>, evitando que uma categoria ganhe vantagem apenas por possuir mais perguntas que outra.</p>
                </section>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3"><span class="text-primary me-2">04.</span> Classificação de intensidade</h2>
                    <p>O nível da regra interna é baseado no percentual obtido na categoria predominante:</p>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered text-center align-middle">
                            <thead class="table-light"><tr><th>Percentual</th><th>Nível</th></tr></thead>
                            <tbody>
                                <tr><td>0%</td><td>Sem indício relevante</td></tr>
                                <tr class="table-success"><td>Acima de 0% até 33,33%</td><td>Baixo</td></tr>
                                <tr class="table-warning"><td>Acima de 33,33% até 66,66%</td><td>Moderado</td></tr>
                                <tr class="table-danger"><td>Acima de 66,66%</td><td>Alto</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted small">Esse nível representa somente a frequência/intensidade informada pelo usuário dentro das regras da aplicação e não constitui diagnóstico médico.</p>
                </section>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3"><span class="text-primary me-2">05.</span> Análise por Inteligência Artificial</h2>
                    <p>O agente recebe separadamente o relato textual e as respostas estruturadas do questionário. As instruções de análise são controladas por uma versão de prompt administrada pelo sistema, enquanto o formato de retorno permanece validado pelo backend.</p>
                    <div class="alert alert-light border-start border-primary border-4">
                        A IA é usada como apoio orientativo. Se o serviço estiver indisponível ou retornar um conteúdo inválido, o VisionAccess utiliza o resultado da regra interna como fallback.
                    </div>
                </section>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3"><span class="text-primary me-2">06.</span> Resultado da triagem</h2>
                    <p>O resultado final apresenta de forma transparente os dois componentes:</p>
                    <p>1. Análise orientativa produzida pela IA.</p>
                    <p>2. Categoria predominante e nível calculados pela regra interna.</p>
                </section>

                <div class="alert alert-warning border-0 shadow-sm mt-5">
                    <h5 class="alert-heading fw-bold">⚠️ Importante</h5>
                    <p class="mb-0">O VisionAccess tem finalidade exclusivamente orientativa e educativa. Os resultados apresentados não representam diagnóstico médico e não substituem avaliação realizada por oftalmologista ou outro profissional de saúde qualificado.</p>
                </div>

                <div class="text-center mt-5">
                    <a href="triagem.php" class="btn btn-primary btn-lg px-5">Iniciar Triagem</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
