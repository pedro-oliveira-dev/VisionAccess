-- =========================================
-- CRIAÇÃO DO BANCO
-- =========================================
CREATE DATABASE visionaccess;
USE visionaccess;

-- =========================================
-- TABELA USUARIO
-- =========================================
CREATE TABLE usuario (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =========================================
-- TABELA TRIAGEM
-- =========================================
CREATE TABLE triagem (
    id_triagem INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    descricao_texto TEXT NOT NULL,
    data_triagem TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_triagem_usuario
        FOREIGN KEY (id_usuario)
        REFERENCES usuario(id_usuario)
        ON DELETE CASCADE
);

-- =========================================
-- TABELA PERGUNTA
-- =========================================
CREATE TABLE pergunta (
    id_pergunta INT AUTO_INCREMENT PRIMARY KEY,
    enunciado VARCHAR(255) NOT NULL,
    tipo ENUM('sim_nao', 'multipla_escolha') NOT NULL
);

-- =========================================
-- TABELA RESPOSTA
-- =========================================
CREATE TABLE resposta (
    id_resposta INT AUTO_INCREMENT PRIMARY KEY,
    id_triagem INT NOT NULL,
    id_pergunta INT NOT NULL,
    resposta_usuario VARCHAR(255) NOT NULL,

    CONSTRAINT fk_resposta_triagem
        FOREIGN KEY (id_triagem)
        REFERENCES triagem(id_triagem)
        ON DELETE CASCADE,

    CONSTRAINT fk_resposta_pergunta
        FOREIGN KEY (id_pergunta)
        REFERENCES pergunta(id_pergunta)
        ON DELETE CASCADE
);

-- =========================================
-- TABELA RESULTADO
-- =========================================
CREATE TABLE resultado (
    id_resultado INT AUTO_INCREMENT PRIMARY KEY,
    id_triagem INT NOT NULL UNIQUE,
    classificacao VARCHAR(100) NOT NULL,
    confianca DECIMAL(5,2), -- Ex: 85.50 (%)
    descricao_resultado TEXT,
    resposta_ia JSON,
    data_resultado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_resultado_triagem
        FOREIGN KEY (id_triagem)
        REFERENCES triagem(id_triagem)
        ON DELETE CASCADE
);

-- =========================================
-- ÍNDICES 
-- =========================================
CREATE INDEX idx_triagem_usuario ON triagem(id_usuario);
CREATE INDEX idx_resposta_triagem ON resposta(id_triagem);
CREATE INDEX idx_resposta_pergunta ON resposta(id_pergunta);

-- =========================================
-- TRIGGER 
-- Garante que cada triagem tenha no máximo 1 resultado
-- =========================================

DELIMITER $$

CREATE TRIGGER before_insert_resultado
BEFORE INSERT ON resultado
FOR EACH ROW
BEGIN
    DECLARE existe INT;

    SELECT COUNT(*) INTO existe
    FROM resultado
    WHERE id_triagem = NEW.id_triagem;

    IF existe > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Já existe um resultado para essa triagem.';
    END IF;
END$$

DELIMITER ;

-- =========================================
-- STORED PROCEDURE 1
-- Criar nova triagem
-- =========================================

DELIMITER $$

CREATE PROCEDURE criar_triagem (
    IN p_id_usuario INT,
    IN p_descricao TEXT
)
BEGIN
    INSERT INTO triagem (id_usuario, descricao_texto)
    VALUES (p_id_usuario, p_descricao);
END$$

DELIMITER ;

-- =========================================
-- STORED PROCEDURE 2
-- Inserir resposta
-- =========================================

DELIMITER $$

CREATE PROCEDURE inserir_resposta (
    IN p_id_triagem INT,
    IN p_id_pergunta INT,
    IN p_resposta VARCHAR(255)
)
BEGIN
    INSERT INTO resposta (id_triagem, id_pergunta, resposta_usuario)
    VALUES (p_id_triagem, p_id_pergunta, p_resposta);
END$$

DELIMITER ;

-- =========================================
-- STORED PROCEDURE 3
-- Inserir resultado da IA
-- =========================================

DELIMITER $$

CREATE PROCEDURE inserir_resultado (
    IN p_id_triagem INT,
    IN p_classificacao VARCHAR(100),
    IN p_confianca DECIMAL(5,2),
    IN p_descricao TEXT,
    IN p_resposta_ia JSON
)
BEGIN
    INSERT INTO resultado (
        id_triagem,
        classificacao,
        confianca,
        descricao_resultado,
        resposta_ia
    )
    VALUES (
        p_id_triagem,
        p_classificacao,
        p_confianca,
        p_descricao,
        p_resposta_ia
    );
END$$

DELIMITER ;

-- =========================================
-- STORED PROCEDURE 4
-- Buscar resultado completo de uma triagem
-- =========================================

DELIMITER $$

CREATE PROCEDURE buscar_resultado_triagem (
    IN p_id_triagem INT
)
BEGIN
    SELECT 
        t.id_triagem,
        t.descricao_texto,
        r.classificacao,
        r.confianca,
        r.descricao_resultado,
        r.data_resultado
    FROM triagem t
    LEFT JOIN resultado r ON t.id_triagem = r.id_triagem
    WHERE t.id_triagem = p_id_triagem;
END$$

DELIMITER ;

-- Inserção de Perguntas Iniciais (Exemplo)
INSERT INTO pergunta (enunciado, tipo) VALUES 
('Você sente dificuldade para enxergar objetos de longe?', 'sim_nao'),
('Você sente dificuldade para ler textos de perto?', 'sim_nao'),
('Sua visão costuma ficar embaçada com frequência?', 'sim_nao'),
('Você sente dores de cabeça após esforço visual prolongado?', 'sim_nao');
