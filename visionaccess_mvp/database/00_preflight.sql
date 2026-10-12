-- Execute estas consultas ANTES da migração e confira o resultado.
-- Elas não alteram o banco.

SHOW COLUMNS FROM usuario;
SHOW COLUMNS FROM pergunta;
SHOW COLUMNS FROM opcao_resposta;
SHOW COLUMNS FROM triagem;
SHOW COLUMNS FROM pontuacao_triagem;
SHOW COLUMNS FROM resultado;

SHOW INDEX FROM resultado;
SHOW CREATE TABLE resultado;
