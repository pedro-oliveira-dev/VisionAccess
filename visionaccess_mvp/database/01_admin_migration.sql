-- VisionAccess - Migração do módulo administrativo
-- Compatível com MariaDB/MySQL sem Stored Procedures/Triggers.
-- Execute UMA vez no banco atual, depois de realizar backup.

SET NAMES utf8mb4;
START TRANSACTION;

-- =====================================================
-- 1. PERFIL DE ACESSO DO USUÁRIO
-- =====================================================
ALTER TABLE usuario
    ADD COLUMN perfil VARCHAR(20) NOT NULL DEFAULT 'usuario' AFTER senha;

-- Após aplicar a migração, promova manualmente o usuário que será administrador:
-- UPDATE usuario SET perfil = 'admin' WHERE id_usuario = SEU_ID;

-- =====================================================
-- 2. VERSIONAMENTO DO QUESTIONÁRIO
-- =====================================================
CREATE TABLE questionario_versao (
    id_versao INT AUTO_INCREMENT PRIMARY KEY,
    versao INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'rascunho',
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    publicado_em TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uk_questionario_versao (versao),
    KEY idx_questionario_status (status),
    CONSTRAINT fk_questionario_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuario(id_usuario)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- A versão 1 representa o questionário que está ativo no momento da migração.
INSERT INTO questionario_versao (versao, status, criado_por, publicado_em)
VALUES (1, 'ativo', NULL, CURRENT_TIMESTAMP);

SET @id_versao_inicial := LAST_INSERT_ID();

ALTER TABLE pergunta
    ADD COLUMN id_versao INT NULL AFTER id_pergunta,
    ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER enunciado;

UPDATE pergunta
SET id_versao = @id_versao_inicial,
    ordem = id_pergunta
WHERE id_versao IS NULL;

ALTER TABLE pergunta
    MODIFY COLUMN id_versao INT NOT NULL,
    ADD KEY idx_pergunta_versao (id_versao),
    ADD KEY idx_pergunta_versao_ativo_ordem (id_versao, ativo, ordem),
    ADD CONSTRAINT fk_pergunta_versao
        FOREIGN KEY (id_versao)
        REFERENCES questionario_versao(id_versao)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

ALTER TABLE opcao_resposta
    ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER pontuacao,
    ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER ordem;

-- Preserva a ordem já existente das opções.
UPDATE opcao_resposta
SET ordem = id_opcao
WHERE ordem = 0;

ALTER TABLE opcao_resposta
    ADD KEY idx_opcao_pergunta_ativo_ordem (id_pergunta, ativo, ordem);

-- Triagens antigas permanecem sem versão para não atribuir retroativamente
-- uma versão que não existia quando foram realizadas.
ALTER TABLE triagem
    ADD COLUMN id_questionario_versao INT NULL AFTER id_usuario,
    ADD KEY idx_triagem_questionario_versao (id_questionario_versao),
    ADD CONSTRAINT fk_triagem_questionario_versao
        FOREIGN KEY (id_questionario_versao)
        REFERENCES questionario_versao(id_versao)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

-- =====================================================
-- 3. PROMPT VERSIONADO DO AGENTE
-- =====================================================
CREATE TABLE prompt_agente (
    id_prompt INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    versao INT NOT NULL,
    conteudo LONGTEXT NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 0,
    criado_por INT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prompt_versao (versao),
    KEY idx_prompt_ativo (ativo),
    CONSTRAINT fk_prompt_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuario(id_usuario)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Prompt inicial baseado no comportamento atual do processar_triagem.php.
-- O contrato JSON obrigatório continuará sendo imposto pelo backend.
INSERT INTO prompt_agente (nome, versao, conteudo, ativo, criado_por)
VALUES (
    'Análise orientativa visual',
    1,
    'Analise o relato e as respostas do questionário como uma triagem visual exclusivamente orientativa. Considere os dois conjuntos de dados em conjunto, sem produzir diagnóstico médico. Escolha a classificação que melhor representa os sinais informados entre as categorias permitidas pelo sistema. Quando os dados forem insuficientes, ambíguos, mistos ou não permitirem uma associação clara, use Sem classificação clara. Explique de forma curta e acessível a razão da classificação e mantenha linguagem prudente.',
    1,
    NULL
);

-- Registra, daqui em diante, qual prompt/modelo foi utilizado.
-- Resultados antigos permanecem com id_prompt NULL porque não é possível
-- reconstruir com segurança qual versão de prompt foi usada em cada execução.
ALTER TABLE resultado
    ADD COLUMN id_prompt INT NULL AFTER id_triagem,
    ADD COLUMN prompt_snapshot LONGTEXT NULL AFTER resposta_ia,
    ADD COLUMN modelo_ia VARCHAR(100) NULL AFTER prompt_snapshot,
    ADD COLUMN percentual_interno DECIMAL(5,2) NULL AFTER pontuacao_interna,
    ADD KEY idx_resultado_prompt (id_prompt),
    ADD CONSTRAINT fk_resultado_prompt
        FOREIGN KEY (id_prompt)
        REFERENCES prompt_agente(id_prompt)
        ON DELETE RESTRICT
        ON UPDATE CASCADE;

-- =====================================================
-- 4. PONTUAÇÃO NORMALIZADA POR CATEGORIA
-- =====================================================
ALTER TABLE pontuacao_triagem
    ADD COLUMN pontuacao_maxima INT NOT NULL DEFAULT 0 AFTER pontuacao_total,
    ADD COLUMN percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER pontuacao_maxima;

-- Recalcula os dados históricos com base nas opções atualmente cadastradas.
-- Isso não altera resultado/nivel_risco já armazenados nas triagens antigas.
UPDATE pontuacao_triagem pt
JOIN (
    SELECT
        r.id_triagem,
        p.id_categoria,
        SUM(o.pontuacao) AS total,
        SUM(mx.max_pontuacao) AS maxima
    FROM resposta r
    JOIN pergunta p ON p.id_pergunta = r.id_pergunta
    JOIN opcao_resposta o ON o.id_opcao = r.id_opcao
    JOIN (
        SELECT id_pergunta, MAX(pontuacao) AS max_pontuacao
        FROM opcao_resposta
        GROUP BY id_pergunta
    ) mx ON mx.id_pergunta = r.id_pergunta
    GROUP BY r.id_triagem, p.id_categoria
) calc
    ON calc.id_triagem = pt.id_triagem
   AND calc.id_categoria = pt.id_categoria
SET
    pt.pontuacao_maxima = calc.maxima,
    pt.percentual = CASE
        WHEN calc.maxima > 0 THEN ROUND((calc.total / calc.maxima) * 100, 2)
        ELSE 0.00
    END;

COMMIT;
