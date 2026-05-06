-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 06/05/2026 às 02:11
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `visionaccess`
--

DELIMITER $$
--
-- Procedimentos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `buscar_resultado_triagem` (IN `p_id_triagem` INT)   BEGIN
    SELECT 
        t.id_triagem,
        t.descricao_texto,
        t.data_triagem,

        r.classificacao_ia,
        r.confianca_ia,
        r.descricao_ia,

        r.classificacao_interna,
        r.pontuacao_interna,
        r.nivel_risco,

        r.resultado_final,
        r.descricao_resultado,
        r.data_resultado
    FROM triagem t
    LEFT JOIN resultado r ON t.id_triagem = r.id_triagem
    WHERE t.id_triagem = p_id_triagem;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `calcular_pontuacao_triagem` (IN `p_id_triagem` INT)   BEGIN
    DELETE FROM pontuacao_triagem
    WHERE id_triagem = p_id_triagem;

    INSERT INTO pontuacao_triagem (
        id_triagem,
        id_categoria,
        pontuacao_total
    )
    SELECT 
        r.id_triagem,
        p.id_categoria,
        SUM(o.pontuacao) AS pontuacao_total
    FROM resposta r
    JOIN pergunta p ON r.id_pergunta = p.id_pergunta
    JOIN opcao_resposta o ON r.id_opcao = o.id_opcao
    WHERE r.id_triagem = p_id_triagem
    GROUP BY r.id_triagem, p.id_categoria;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `inserir_resposta` (IN `p_id_triagem` INT, IN `p_id_pergunta` INT, IN `p_id_opcao` INT)   BEGIN
    INSERT INTO resposta (
        id_triagem,
        id_pergunta,
        id_opcao
    )
    VALUES (
        p_id_triagem,
        p_id_pergunta,
        p_id_opcao
    );
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `inserir_resultado` (IN `p_id_triagem` INT, IN `p_classificacao_ia` VARCHAR(100), IN `p_confianca_ia` DECIMAL(5,2), IN `p_descricao_ia` TEXT, IN `p_resposta_ia` JSON, IN `p_classificacao_interna` VARCHAR(100), IN `p_pontuacao_interna` INT, IN `p_nivel_risco` VARCHAR(50), IN `p_resultado_final` VARCHAR(150), IN `p_descricao_resultado` TEXT)   BEGIN
    INSERT INTO resultado (
        id_triagem,
        classificacao_ia,
        confianca_ia,
        descricao_ia,
        resposta_ia,
        classificacao_interna,
        pontuacao_interna,
        nivel_risco,
        resultado_final,
        descricao_resultado
    )
    VALUES (
        p_id_triagem,
        p_classificacao_ia,
        p_confianca_ia,
        p_descricao_ia,
        p_resposta_ia,
        p_classificacao_interna,
        p_pontuacao_interna,
        p_nivel_risco,
        p_resultado_final,
        p_descricao_resultado
    );
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `categoria_triagem`
--

CREATE TABLE `categoria_triagem` (
  `id_categoria` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `categoria_triagem`
--

INSERT INTO `categoria_triagem` (`id_categoria`, `nome`, `descricao`) VALUES
(1, 'Visão de longe', 'Dificuldades relacionadas a enxergar objetos distantes.'),
(2, 'Visão de perto', 'Dificuldades relacionadas à leitura e foco em objetos próximos.'),
(3, 'Astigmatismo', 'Sintomas relacionados à visão borrada, distorcida ou dupla.'),
(4, 'Condições oculares', 'Sinais como vermelhidão, dor, ardência, lacrimejamento ou sensibilidade.'),
(5, 'Sintomas gerais', 'Sintomas como dor de cabeça, cansaço visual e desconforto.');

-- --------------------------------------------------------

--
-- Estrutura para tabela `opcao_resposta`
--

CREATE TABLE `opcao_resposta` (
  `id_opcao` int(11) NOT NULL,
  `id_pergunta` int(11) NOT NULL,
  `texto` varchar(100) NOT NULL,
  `pontuacao` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `opcao_resposta`
--

INSERT INTO `opcao_resposta` (`id_opcao`, `id_pergunta`, `texto`, `pontuacao`) VALUES
(1, 1, 'Nunca', 0),
(2, 1, 'Raramente', 1),
(3, 1, 'Às vezes', 2),
(4, 1, 'Sempre', 3),
(5, 2, 'Nunca', 0),
(6, 2, 'Raramente', 1),
(7, 2, 'Às vezes', 2),
(8, 2, 'Sempre', 3),
(9, 3, 'Nunca', 0),
(10, 3, 'Raramente', 1),
(11, 3, 'Às vezes', 2),
(12, 3, 'Sempre', 3),
(13, 4, 'Nunca', 0),
(14, 4, 'Raramente', 1),
(15, 4, 'Às vezes', 2),
(16, 4, 'Sempre', 3),
(17, 5, 'Nunca', 0),
(18, 5, 'Raramente', 1),
(19, 5, 'Às vezes', 2),
(20, 5, 'Sempre', 3),
(21, 6, 'Nunca', 0),
(22, 6, 'Raramente', 1),
(23, 6, 'Às vezes', 2),
(24, 6, 'Sempre', 3),
(25, 7, 'Nunca', 0),
(26, 7, 'Raramente', 1),
(27, 7, 'Às vezes', 2),
(28, 7, 'Sempre', 3),
(29, 8, 'Nunca', 0),
(30, 8, 'Raramente', 1),
(31, 8, 'Às vezes', 2),
(32, 8, 'Sempre', 3),
(33, 9, 'Nunca', 0),
(34, 9, 'Raramente', 1),
(35, 9, 'Às vezes', 2),
(36, 9, 'Sempre', 3),
(37, 10, 'Nunca', 0),
(38, 10, 'Raramente', 1),
(39, 10, 'Às vezes', 2),
(40, 10, 'Sempre', 3),
(41, 11, 'Nunca', 0),
(42, 11, 'Raramente', 1),
(43, 11, 'Às vezes', 2),
(44, 11, 'Sempre', 3),
(45, 12, 'Nunca', 0),
(46, 12, 'Raramente', 1),
(47, 12, 'Às vezes', 2),
(48, 12, 'Sempre', 3),
(49, 13, 'Nunca', 0),
(50, 13, 'Raramente', 1),
(51, 13, 'Às vezes', 2),
(52, 13, 'Sempre', 3),
(53, 14, 'Nunca', 0),
(54, 14, 'Raramente', 1),
(55, 14, 'Às vezes', 2),
(56, 14, 'Sempre', 3),
(57, 15, 'Nunca', 0),
(58, 15, 'Raramente', 1),
(59, 15, 'Às vezes', 2),
(60, 15, 'Sempre', 3);

-- --------------------------------------------------------

--
-- Estrutura para tabela `pergunta`
--

CREATE TABLE `pergunta` (
  `id_pergunta` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `enunciado` varchar(255) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pergunta`
--

INSERT INTO `pergunta` (`id_pergunta`, `id_categoria`, `enunciado`, `ativo`) VALUES
(1, 1, 'Você tem dificuldade para enxergar placas, lousas ou objetos distantes?', 1),
(2, 2, 'Você tem dificuldade para ler de perto ou precisa afastar o celular?', 1),
(3, 3, 'Você percebe a visão borrada ou distorcida tanto de perto quanto de longe?', 1),
(4, 4, 'Você sente ardência, vermelhidão, dor ou lacrimejamento nos olhos?', 1),
(5, 5, 'Você sente dor de cabeça ou cansaço visual com frequência?', 1),
(6, 1, 'Você costuma apertar os olhos para tentar enxergar melhor objetos distantes?', 1),
(7, 2, 'Você sente dificuldade para focar textos pequenos em livros, celular ou computador?', 1),
(8, 3, 'Você percebe letras ou luzes distorcidas, duplicadas ou com sombras?', 1),
(9, 4, 'Você sente os olhos irritados, secos ou lacrimejando durante o dia?', 1),
(10, 5, 'Você sente cansaço visual após usar telas por muito tempo?', 1),
(11, 1, 'Você evita ler placas, letreiros ou informações distantes por sentir dificuldade para enxergar?', 1),
(12, 2, 'Você precisa aumentar o tamanho da fonte no celular ou computador para ler com conforto?', 1),
(13, 3, 'Você percebe que linhas retas, textos ou contornos parecem levemente tortos ou desfocados?', 1),
(14, 4, 'Você sente sensibilidade à luz ou desconforto em ambientes muito claros?', 1),
(15, 5, 'Você precisa fazer pausas frequentes ao estudar, ler ou usar computador por desconforto visual?', 1);

-- --------------------------------------------------------

--
-- Estrutura para tabela `pontuacao_triagem`
--

CREATE TABLE `pontuacao_triagem` (
  `id_pontuacao` int(11) NOT NULL,
  `id_triagem` int(11) NOT NULL,
  `id_categoria` int(11) NOT NULL,
  `pontuacao_total` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `pontuacao_triagem`
--

INSERT INTO `pontuacao_triagem` (`id_pontuacao`, `id_triagem`, `id_categoria`, `pontuacao_total`) VALUES
(1, 1, 1, 3),
(2, 1, 2, 3),
(3, 1, 3, 3),
(4, 1, 4, 1),
(5, 1, 5, 1),
(8, 2, 1, 0),
(9, 2, 2, 6),
(10, 2, 3, 0),
(11, 2, 4, 4),
(12, 2, 5, 5),
(15, 3, 1, 6),
(16, 3, 2, 6),
(17, 3, 3, 6),
(18, 3, 4, 6),
(19, 3, 5, 6),
(22, 4, 1, 9),
(23, 4, 2, 1),
(24, 4, 3, 5),
(25, 4, 4, 0),
(26, 4, 5, 5),
(29, 5, 1, 0),
(30, 5, 2, 0),
(31, 5, 3, 0),
(32, 5, 4, 0),
(33, 5, 5, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `resposta`
--

CREATE TABLE `resposta` (
  `id_resposta` int(11) NOT NULL,
  `id_triagem` int(11) NOT NULL,
  `id_pergunta` int(11) NOT NULL,
  `id_opcao` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `resposta`
--

INSERT INTO `resposta` (`id_resposta`, `id_triagem`, `id_pergunta`, `id_opcao`) VALUES
(1, 1, 1, 4),
(2, 1, 2, 8),
(3, 1, 3, 12),
(4, 1, 4, 14),
(5, 1, 5, 18),
(6, 2, 1, 1),
(7, 2, 2, 8),
(8, 2, 3, 9),
(9, 2, 4, 15),
(10, 2, 5, 19),
(11, 2, 6, 21),
(12, 2, 7, 28),
(13, 2, 8, 29),
(14, 2, 9, 35),
(15, 2, 10, 40),
(16, 3, 1, 4),
(17, 3, 2, 8),
(18, 3, 3, 12),
(19, 3, 4, 16),
(20, 3, 5, 20),
(21, 3, 6, 24),
(22, 3, 7, 28),
(23, 3, 8, 32),
(24, 3, 9, 36),
(25, 3, 10, 40),
(26, 4, 1, 4),
(27, 4, 2, 6),
(28, 4, 3, 11),
(29, 4, 4, 13),
(30, 4, 5, 18),
(31, 4, 6, 24),
(32, 4, 7, 25),
(33, 4, 8, 30),
(34, 4, 9, 33),
(35, 4, 10, 40),
(36, 4, 11, 44),
(37, 4, 12, 45),
(38, 4, 13, 51),
(39, 4, 14, 53),
(40, 4, 15, 58),
(41, 5, 1, 1),
(42, 5, 2, 5),
(43, 5, 3, 9),
(44, 5, 4, 13),
(45, 5, 5, 17),
(46, 5, 6, 21),
(47, 5, 7, 25),
(48, 5, 8, 29),
(49, 5, 9, 33),
(50, 5, 10, 37),
(51, 5, 11, 41),
(52, 5, 12, 45),
(53, 5, 13, 49),
(54, 5, 14, 53),
(55, 5, 15, 57);

-- --------------------------------------------------------

--
-- Estrutura para tabela `resultado`
--

CREATE TABLE `resultado` (
  `id_resultado` int(11) NOT NULL,
  `id_triagem` int(11) NOT NULL,
  `classificacao_ia` varchar(100) DEFAULT NULL,
  `confianca_ia` decimal(5,2) DEFAULT NULL,
  `descricao_ia` text DEFAULT NULL,
  `resposta_ia` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`resposta_ia`)),
  `classificacao_interna` varchar(100) DEFAULT NULL,
  `pontuacao_interna` int(11) DEFAULT NULL,
  `nivel_risco` varchar(50) DEFAULT NULL,
  `resultado_final` varchar(150) DEFAULT NULL,
  `descricao_resultado` text DEFAULT NULL,
  `data_resultado` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `resultado`
--

INSERT INTO `resultado` (`id_resultado`, `id_triagem`, `classificacao_ia`, `confianca_ia`, `descricao_ia`, `resposta_ia`, `classificacao_interna`, `pontuacao_interna`, `nivel_risco`, `resultado_final`, `descricao_resultado`, `data_resultado`) VALUES
(1, 1, 'Sem classificação clara', 0.00, 'Não foi possível interpretar automaticamente os sintomas informados nesta triagem. O sistema registrou sua solicitação, mas a análise automática não pôde ser concluída com segurança.', '{\"entrada_usuario\":\"Não estou enxergando de longe e nem de perto.\",\"saida_ia\":{\"classificacao\":\"Sem classificação clara\",\"confianca\":0,\"descricao_resultado\":\"Não foi possível interpretar automaticamente os sintomas informados nesta triagem. O sistema registrou sua solicitação, mas a análise automática não pôde ser concluída com segurança.\",\"alerta\":\"Resultado sugestivo. Não substitui avaliação profissional.\"}}', 'Astigmatismo', 3, 'Baixo', 'Astigmatismo', 'A IA não encontrou uma classificação clara a partir do relato informado. Pelas respostas objetivas, a maior pontuação foi em Astigmatismo, com 3 ponto(s), indicando nível Baixo.\n\nResultado sugestivo. Não substitui avaliação profissional.', '2026-05-05 23:24:57'),
(2, 2, 'Hipermetropia', 85.00, 'Dificuldade para ler de perto é um sintoma comum de hipermetropia.', '{\"entrada_usuario\":\"Dificuldade para ler de perto.\",\"saida_ia\":{\"classificacao\":\"Hipermetropia\",\"confianca\":85,\"descricao_resultado\":\"Dificuldade para ler de perto é um sintoma comum de hipermetropia.\",\"alerta\":\"Este resultado é apenas orientativo e não substitui uma avaliação com um oftalmologista.\"}}', 'Visão de perto', 6, 'Moderado', 'Hipermetropia / Visão de perto', 'A análise da IA indicou Hipermetropia. As respostas objetivas também apontaram maior pontuação em Visão de perto, com 6 ponto(s), indicando nível Moderado.\n\nDificuldade para ler de perto é um sintoma comum de hipermetropia.\n\nEste resultado é apenas orientativo e não substitui uma avaliação com um oftalmologista.', '2026-05-05 23:31:23'),
(3, 3, 'Sem classificação clara', 100.00, 'Texto insuficiente ou ambíguo para classificação.', '{\"entrada_usuario\":\"blobloblo\",\"saida_ia\":{\"classificacao\":\"Sem classificação clara\",\"confianca\":100,\"descricao_resultado\":\"Texto insuficiente ou ambíguo para classificação.\",\"alerta\":\"Este resultado é apenas orientativo e não substitui uma avaliação com um oftalmologista.\"}}', 'Astigmatismo', 6, 'Moderado', 'Astigmatismo', 'A IA não encontrou uma classificação clara a partir do relato informado. Pelas respostas objetivas, a maior pontuação foi em Astigmatismo, com 6 ponto(s), indicando nível Moderado.\n\nEste resultado é apenas orientativo e não substitui uma avaliação com um oftalmologista.', '2026-05-05 23:32:46'),
(4, 4, 'Miopia', 95.00, 'Dificuldade para enxergar objetos distantes.', '{\"entrada_usuario\":\"Difilculdade para enxergar de longe, como lousas em sala de aula\",\"saida_ia\":{\"classificacao\":\"Miopia\",\"confianca\":95,\"descricao_resultado\":\"Dificuldade para enxergar objetos distantes.\",\"alerta\":\"Este resultado é apenas orientativo e não substitui uma avaliação com um oftalmologista.\"}}', 'Visão de longe', 9, 'Alto', 'Miopia / Visão de longe', 'A análise da IA indicou Miopia. As respostas objetivas também apontaram maior pontuação em Visão de longe, com 9 ponto(s), indicando nível Alto.\n\nDificuldade para enxergar objetos distantes.\n\nEste resultado é apenas orientativo e não substitui uma avaliação com um oftalmologista.', '2026-05-06 00:02:48'),
(5, 5, 'Sem classificação clara', 100.00, 'Texto insuficiente para classificação.', '{\"entrada_usuario\":\"sinto nada\",\"saida_ia\":{\"classificacao\":\"Sem classificação clara\",\"confianca\":100,\"descricao_resultado\":\"Texto insuficiente para classificação.\",\"alerta\":\"Este resultado é apenas orientativo e não substitui uma avaliação com um oftalmologista.\"}}', 'Astigmatismo', 0, 'Sem indício relevante', 'Astigmatismo', 'A IA não encontrou uma classificação clara a partir do relato informado. Pelas respostas objetivas, a maior pontuação foi em Astigmatismo, com 0 ponto(s), indicando nível Sem indício relevante.\n\nEste resultado é apenas orientativo e não substitui uma avaliação com um oftalmologista.', '2026-05-06 00:03:47');

-- --------------------------------------------------------

--
-- Estrutura para tabela `triagem`
--

CREATE TABLE `triagem` (
  `id_triagem` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `descricao_texto` text NOT NULL,
  `data_triagem` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `triagem`
--

INSERT INTO `triagem` (`id_triagem`, `id_usuario`, `descricao_texto`, `data_triagem`) VALUES
(1, 1, 'Não estou enxergando de longe e nem de perto.', '2026-05-05 23:24:56'),
(2, 1, 'Dificuldade para ler de perto.', '2026-05-05 23:31:21'),
(3, 1, 'blobloblo', '2026-05-05 23:32:45'),
(4, 1, 'Difilculdade para enxergar de longe, como lousas em sala de aula', '2026-05-06 00:02:47'),
(5, 1, 'sinto nada', '2026-05-06 00:03:46');

-- --------------------------------------------------------

--
-- Estrutura para tabela `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `senha` varchar(255) NOT NULL,
  `data_cadastro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nome`, `email`, `senha`, `data_cadastro`) VALUES
(1, 'Pedro Augusto Moreira de Oliveira', 'teste@teste.com', '$2y$10$s1/JNNyni/OzSEl6v5kdM.dNFG9EJ4JTAOPNEd2691x6ZM8i6FR8W', '2026-05-05 23:17:54');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categoria_triagem`
--
ALTER TABLE `categoria_triagem`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices de tabela `opcao_resposta`
--
ALTER TABLE `opcao_resposta`
  ADD PRIMARY KEY (`id_opcao`),
  ADD KEY `idx_opcao_pergunta` (`id_pergunta`);

--
-- Índices de tabela `pergunta`
--
ALTER TABLE `pergunta`
  ADD PRIMARY KEY (`id_pergunta`),
  ADD KEY `idx_pergunta_categoria` (`id_categoria`);

--
-- Índices de tabela `pontuacao_triagem`
--
ALTER TABLE `pontuacao_triagem`
  ADD PRIMARY KEY (`id_pontuacao`),
  ADD UNIQUE KEY `uk_pontuacao_triagem_categoria` (`id_triagem`,`id_categoria`),
  ADD KEY `idx_pontuacao_triagem` (`id_triagem`),
  ADD KEY `idx_pontuacao_categoria` (`id_categoria`);

--
-- Índices de tabela `resposta`
--
ALTER TABLE `resposta`
  ADD PRIMARY KEY (`id_resposta`),
  ADD KEY `idx_resposta_triagem` (`id_triagem`),
  ADD KEY `idx_resposta_pergunta` (`id_pergunta`),
  ADD KEY `idx_resposta_opcao` (`id_opcao`);

--
-- Índices de tabela `resultado`
--
ALTER TABLE `resultado`
  ADD PRIMARY KEY (`id_resultado`),
  ADD UNIQUE KEY `id_triagem` (`id_triagem`);

--
-- Índices de tabela `triagem`
--
ALTER TABLE `triagem`
  ADD PRIMARY KEY (`id_triagem`),
  ADD KEY `idx_triagem_usuario` (`id_usuario`);

--
-- Índices de tabela `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categoria_triagem`
--
ALTER TABLE `categoria_triagem`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `opcao_resposta`
--
ALTER TABLE `opcao_resposta`
  MODIFY `id_opcao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT de tabela `pergunta`
--
ALTER TABLE `pergunta`
  MODIFY `id_pergunta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de tabela `pontuacao_triagem`
--
ALTER TABLE `pontuacao_triagem`
  MODIFY `id_pontuacao` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT de tabela `resposta`
--
ALTER TABLE `resposta`
  MODIFY `id_resposta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=56;

--
-- AUTO_INCREMENT de tabela `resultado`
--
ALTER TABLE `resultado`
  MODIFY `id_resultado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `triagem`
--
ALTER TABLE `triagem`
  MODIFY `id_triagem` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `opcao_resposta`
--
ALTER TABLE `opcao_resposta`
  ADD CONSTRAINT `fk_opcao_pergunta` FOREIGN KEY (`id_pergunta`) REFERENCES `pergunta` (`id_pergunta`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `pergunta`
--
ALTER TABLE `pergunta`
  ADD CONSTRAINT `fk_pergunta_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria_triagem` (`id_categoria`) ON UPDATE CASCADE;

--
-- Restrições para tabelas `pontuacao_triagem`
--
ALTER TABLE `pontuacao_triagem`
  ADD CONSTRAINT `fk_pontuacao_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria_triagem` (`id_categoria`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pontuacao_triagem` FOREIGN KEY (`id_triagem`) REFERENCES `triagem` (`id_triagem`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `resposta`
--
ALTER TABLE `resposta`
  ADD CONSTRAINT `fk_resposta_opcao` FOREIGN KEY (`id_opcao`) REFERENCES `opcao_resposta` (`id_opcao`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resposta_pergunta` FOREIGN KEY (`id_pergunta`) REFERENCES `pergunta` (`id_pergunta`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_resposta_triagem` FOREIGN KEY (`id_triagem`) REFERENCES `triagem` (`id_triagem`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `resultado`
--
ALTER TABLE `resultado`
  ADD CONSTRAINT `fk_resultado_triagem` FOREIGN KEY (`id_triagem`) REFERENCES `triagem` (`id_triagem`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Restrições para tabelas `triagem`
--
ALTER TABLE `triagem`
  ADD CONSTRAINT `fk_triagem_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
