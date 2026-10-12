<?php
session_start();
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-10 col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-5">
                <h1 class="text-center mb-5 text-primary">Sobre o VisionAccess</h1>
                
                <p class="mb-3">O VisionAccess é uma plataforma web de triagem orientativa e acessibilidade visual desenvolvida por <strong>Pedro Augusto</strong> e <strong>Pedro Marchezine</strong> como parte da disciplina de Laboratório de Engenharia de Software e também como Trabalho de Graduação do curso de Análise e Desenvolvimento de Sistemas.</p>

                <p class="mb-5">O projeto surgiu a partir da percepção de que muitas pessoas convivem com dificuldades visuais sem compreender completamente os sintomas que apresentam ou sem buscar orientação especializada nos estágios iniciais. Além disso, a crescente utilização de recursos digitais cria oportunidades para tornar informações de saúde mais acessíveis e inclusivas.</p>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3 text-primary">Nosso propósito</h2>
                    <p>O principal objetivo do VisionAccess é oferecer uma ferramenta de apoio capaz de auxiliar usuários na identificação inicial de possíveis dificuldades visuais por meio de uma triagem orientativa. A plataforma busca transformar informações técnicas em uma experiência simples, acessível e compreensível para qualquer pessoa.</p>
                    <p>Além da triagem, o projeto tem como propósito promover conscientização sobre saúde visual e demonstrar como tecnologias como Inteligência Artificial podem ser utilizadas de forma responsável para ampliar o acesso à informação.</p>
                </section>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3 text-primary">Para quem o VisionAccess foi desenvolvido</h2>
                    <p>A plataforma é destinada a qualquer pessoa que deseje obter uma avaliação inicial sobre sintomas visuais percebidos no dia a dia. O sistema foi pensado especialmente para usuários que:</p>
                    <ul class="list-group list-group-flush mb-4">
                        <li>Apresentam desconfortos ou dificuldades visuais frequentes;</li>
                        <li>Desejam compreender melhor seus sintomas antes de procurar atendimento especializado;</li>
                        <li>Buscam uma ferramenta simples e acessível para orientação inicial;</li>
                        <li>Necessitam de recursos de acessibilidade durante a navegação.</li>
                    </ul>
                    <div class="alert alert-info border-0 shadow-sm">
                        É importante destacar que o VisionAccess possui finalidade exclusivamente orientativa e não substitui a avaliação realizada por profissionais da área da saúde.
                    </div>
                </section>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3 text-primary">Como funciona</h2>
                    <p>A análise realizada pelo sistema combina duas abordagens complementares:</p>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="card h-100 bg-light border-0">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Relato Livre</h5>
                                    <p class="card-text small">Analisado por Inteligência Artificial para identificar padrões relacionados a possíveis dificuldades visuais.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100 bg-light border-0">
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Questionário Estruturado</h5>
                                    <p class="card-text small">Respostas processadas por regras internas de pontuação desenvolvidas especificamente para o projeto.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p>A combinação dessas duas análises permite gerar um resultado mais consistente e transparente para o usuário.</p>
                </section>

                <section class="mb-5">
                    <h2 class="h4 fw-bold mb-3 text-primary">Evolução do projeto</h2>
                    <p>O VisionAccess foi concebido de forma modular para permitir futuras expansões e melhorias. Entre as possibilidades de evolução estão:</p>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">✅ Ampliação do questionário;</li>
                                <li class="mb-2">✅ Mais recursos de acessibilidade;</li>
                                <li class="mb-2">✅ Aprimoramento da IA;</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">✅ Dashboards estatísticos;</li>
                                <li class="mb-2">✅ Aplicativos móveis;</li>
                                <li class="mb-2">✅ Conteúdos educativos.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <div class="text-center mt-5">
                    <a href="index.php" class="btn btn-outline-primary btn-lg px-5 me-2">Início</a>
                    <a href="como_funciona.php" class="btn btn-primary btn-lg px-5">Como Funciona</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
