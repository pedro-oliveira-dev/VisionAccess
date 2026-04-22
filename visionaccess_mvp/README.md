# VisionAccess - MVP

Este é o MVP (Minimum Viable Product) da plataforma **VisionAccess**, desenvolvida para realizar triagem orientativa de dificuldades visuais com apoio de Inteligência Artificial e acessibilidade automática.

## Tecnologias Utilizadas

*   **Front-end:** HTML5, CSS3, JavaScript (ES6+), Bootstrap 5.3.
*   **Back-end:** PHP 8.x (Arquitetura simples para MVP).
*   **Banco de Dados:** MySQL.
*   **Acessibilidade:** Recursos nativos de alto contraste e ajuste de fonte.

## Estrutura do Projeto

*   `index.php`: Página de login e entrada principal.
*   `cadastro.php`: Cadastro de novos usuários.
*   `dashboard.php`: Painel principal do usuário logado.
*   `triagem.php`: Fluxo de triagem (relato livre e questionário).
*   `resultado.php`: Exibição do resultado processado pela IA (Gemini).
*   `historico.php`: Histórico de triagens realizadas.
*   `includes/`: Componentes reutilizáveis (header, footer, db_connect, config).
*   `php/`: Lógica de processamento (autenticação, triagem).
*   `database.sql`: Script para criação das tabelas no MySQL.

## Como Instalar e Executar

1.  **Banco de Dados:**
    *   Importe o arquivo `database.sql` no seu servidor MySQL (ex: via phpMyAdmin).
    *   Certifique-se de que o banco de dados `visionaccess` foi criado com sucesso.

2.  **Configuração de Conexão:**
    *   Abra o arquivo `includes/db_connect.php`.
    *   Ajuste as variáveis `$host`, `$dbname`, `$username` e `$password` conforme as configurações do seu ambiente local (ex: XAMPP, WAMP).

3.  **Servidor Web:**
    *   Coloque a pasta `visionaccess` no diretório raiz do seu servidor web (ex: `htdocs` no XAMPP).
    *   Acesse via navegador: `http://localhost/visionaccess/`.

## Recursos de Acessibilidade

O sistema inclui botões no cabeçalho para:
*   **Alto Contraste:** Alterna entre o tema padrão e um tema de alto contraste (fundo preto, texto branco/amarelo).
*   **Aumento de Fonte:** Aumenta o tamanho base da fonte para facilitar a leitura.

## Aviso Legal

Este sistema é uma ferramenta de triagem orientativa e **não substitui o diagnóstico médico profissional**. Os resultados são baseados em algoritmos de IA e devem ser validados por um oftalmologista.
