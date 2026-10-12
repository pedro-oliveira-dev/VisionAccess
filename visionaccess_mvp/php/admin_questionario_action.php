<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

function voltarQuestionario(string $mensagem = '', string $erro = ''): void
{
    if ($mensagem !== '') {
        $_SESSION['admin_mensagem'] = $mensagem;
    }
    if ($erro !== '') {
        $_SESSION['admin_erro'] = $erro;
    }
    header('Location: ../admin_questionario.php');
    exit();
}

function exigirAdmin(PDO $pdo): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ../index.php');
        exit();
    }

    $stmt = $pdo->prepare('SELECT perfil FROM usuario WHERE id_usuario = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    if ($stmt->fetchColumn() !== 'admin') {
        http_response_code(403);
        exit('Acesso negado.');
    }
}

function exigirRascunho(PDO $pdo, int $idVersao): array
{
    $stmt = $pdo->prepare("SELECT * FROM questionario_versao WHERE id_versao = ? AND status = 'rascunho'");
    $stmt->execute([$idVersao]);
    $versao = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$versao) {
        throw new Exception('A versão informada não é um rascunho editável.');
    }

    return $versao;
}

exigirAdmin($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarQuestionario('', 'Método inválido.');
}

if (!validarCsrf($_POST['csrf_token'] ?? null)) {
    voltarQuestionario('', 'A sessão do formulário expirou. Atualize a página e tente novamente.');
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'criar_rascunho') {
        $pdo->beginTransaction();

        $existeRascunho = (int) $pdo->query("SELECT COUNT(*) FROM questionario_versao WHERE status = 'rascunho'")->fetchColumn();
        if ($existeRascunho > 0) {
            throw new Exception('Já existe uma versão em rascunho.');
        }

        $versaoAtiva = $pdo->query("SELECT * FROM questionario_versao WHERE status = 'ativo' ORDER BY versao DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$versaoAtiva) {
            throw new Exception('Nenhuma versão ativa foi encontrada para clonagem.');
        }

        $proximaVersao = (int) $pdo->query('SELECT COALESCE(MAX(versao), 0) + 1 FROM questionario_versao')->fetchColumn();
        $stmtVersao = $pdo->prepare("INSERT INTO questionario_versao (versao, status, criado_por) VALUES (?, 'rascunho', ?)");
        $stmtVersao->execute([$proximaVersao, (int) $_SESSION['user_id']]);
        $novoIdVersao = (int) $pdo->lastInsertId();

        $stmtPerguntas = $pdo->prepare('SELECT * FROM pergunta WHERE id_versao = ? ORDER BY ordem, id_pergunta');
        $stmtPerguntas->execute([(int) $versaoAtiva['id_versao']]);
        $perguntasOriginais = $stmtPerguntas->fetchAll(PDO::FETCH_ASSOC);

        $insertPergunta = $pdo->prepare('INSERT INTO pergunta (id_versao, id_categoria, enunciado, ordem, ativo) VALUES (?, ?, ?, ?, ?)');
        $buscarOpcoes = $pdo->prepare('SELECT * FROM opcao_resposta WHERE id_pergunta = ? ORDER BY ordem, id_opcao');
        $insertOpcao = $pdo->prepare('INSERT INTO opcao_resposta (id_pergunta, texto, pontuacao, ordem, ativo) VALUES (?, ?, ?, ?, ?)');

        foreach ($perguntasOriginais as $pergunta) {
            $insertPergunta->execute([
                $novoIdVersao,
                (int) $pergunta['id_categoria'],
                $pergunta['enunciado'],
                (int) $pergunta['ordem'],
                (int) $pergunta['ativo'],
            ]);
            $novoIdPergunta = (int) $pdo->lastInsertId();

            $buscarOpcoes->execute([(int) $pergunta['id_pergunta']]);
            foreach ($buscarOpcoes->fetchAll(PDO::FETCH_ASSOC) as $opcao) {
                $insertOpcao->execute([
                    $novoIdPergunta,
                    $opcao['texto'],
                    (int) $opcao['pontuacao'],
                    (int) $opcao['ordem'],
                    (int) $opcao['ativo'],
                ]);
            }
        }

        $pdo->commit();
        voltarQuestionario('Rascunho da versão ' . $proximaVersao . ' criado com sucesso.');
    }

    if ($action === 'salvar_pergunta') {
        $idVersao = filter_var($_POST['id_versao'] ?? null, FILTER_VALIDATE_INT);
        $idPergunta = filter_var($_POST['id_pergunta'] ?? null, FILTER_VALIDATE_INT);
        $idCategoria = filter_var($_POST['id_categoria'] ?? null, FILTER_VALIDATE_INT);
        $ordem = filter_var($_POST['ordem'] ?? null, FILTER_VALIDATE_INT);
        $enunciado = trim($_POST['enunciado'] ?? '');
        $ativo = isset($_POST['ativo']) ? 1 : 0;

        if (!$idVersao || !$idCategoria || !$ordem || $enunciado === '') {
            throw new Exception('Preencha corretamente os dados da pergunta.');
        }
        if (mb_strlen($enunciado) > 255) {
            throw new Exception('O enunciado ultrapassa 255 caracteres.');
        }

        exigirRascunho($pdo, (int) $idVersao);

        $textos = $_POST['opcao_texto'] ?? [];
        $pontuacoes = $_POST['opcao_pontuacao'] ?? [];
        $ordens = $_POST['opcao_ordem'] ?? [];

        if (!is_array($textos) || !is_array($pontuacoes) || !is_array($ordens)) {
            throw new Exception('Formato de opções inválido.');
        }

        $opcoes = [];
        $quantidade = max(count($textos), count($pontuacoes), count($ordens));
        for ($i = 0; $i < $quantidade; $i++) {
            $texto = trim((string) ($textos[$i] ?? ''));
            if ($texto === '') {
                continue;
            }

            $pontuacao = filter_var($pontuacoes[$i] ?? null, FILTER_VALIDATE_INT);
            $ordemOpcao = filter_var($ordens[$i] ?? null, FILTER_VALIDATE_INT);
            if ($pontuacao === false || $pontuacao < 0 || $pontuacao > 100 || !$ordemOpcao) {
                throw new Exception('Pontuação ou ordem de uma opção é inválida.');
            }
            if (mb_strlen($texto) > 100) {
                throw new Exception('O texto de uma opção ultrapassa 100 caracteres.');
            }

            $opcoes[] = [
                'texto' => $texto,
                'pontuacao' => (int) $pontuacao,
                'ordem' => (int) $ordemOpcao,
            ];
        }

        if (count($opcoes) < 2) {
            throw new Exception('Cada pergunta precisa ter pelo menos duas opções.');
        }

        $pdo->beginTransaction();

        if ($idPergunta) {
            $stmtExiste = $pdo->prepare('SELECT COUNT(*) FROM pergunta WHERE id_pergunta = ? AND id_versao = ?');
            $stmtExiste->execute([(int) $idPergunta, (int) $idVersao]);
            if ((int) $stmtExiste->fetchColumn() !== 1) {
                throw new Exception('Pergunta não encontrada no rascunho.');
            }

            $stmt = $pdo->prepare('UPDATE pergunta SET id_categoria = ?, enunciado = ?, ordem = ?, ativo = ? WHERE id_pergunta = ? AND id_versao = ?');
            $stmt->execute([(int) $idCategoria, $enunciado, (int) $ordem, $ativo, (int) $idPergunta, (int) $idVersao]);
            $pdo->prepare('DELETE FROM opcao_resposta WHERE id_pergunta = ?')->execute([(int) $idPergunta]);
            $idPerguntaFinal = (int) $idPergunta;
        } else {
            $stmt = $pdo->prepare('INSERT INTO pergunta (id_versao, id_categoria, enunciado, ordem, ativo) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([(int) $idVersao, (int) $idCategoria, $enunciado, (int) $ordem, $ativo]);
            $idPerguntaFinal = (int) $pdo->lastInsertId();
        }

        $stmtOpcao = $pdo->prepare('INSERT INTO opcao_resposta (id_pergunta, texto, pontuacao, ordem, ativo) VALUES (?, ?, ?, ?, 1)');
        foreach ($opcoes as $opcao) {
            $stmtOpcao->execute([$idPerguntaFinal, $opcao['texto'], $opcao['pontuacao'], $opcao['ordem']]);
        }

        $pdo->commit();
        voltarQuestionario('Pergunta salva no rascunho.');
    }

    if ($action === 'alternar_pergunta') {
        $idPergunta = filter_var($_POST['id_pergunta'] ?? null, FILTER_VALIDATE_INT);
        if (!$idPergunta) {
            throw new Exception('Pergunta inválida.');
        }

        $stmt = $pdo->prepare("
            UPDATE pergunta p
            JOIN questionario_versao qv ON qv.id_versao = p.id_versao
            SET p.ativo = IF(p.ativo = 1, 0, 1)
            WHERE p.id_pergunta = ? AND qv.status = 'rascunho'
        ");
        $stmt->execute([(int) $idPergunta]);
        if ($stmt->rowCount() < 1) {
            throw new Exception('A pergunta não pertence a um rascunho editável.');
        }
        voltarQuestionario('Status da pergunta atualizado.');
    }

    if ($action === 'excluir_pergunta') {
        $idPergunta = filter_var($_POST['id_pergunta'] ?? null, FILTER_VALIDATE_INT);
        if (!$idPergunta) {
            throw new Exception('Pergunta inválida.');
        }

        $stmt = $pdo->prepare("
            DELETE p FROM pergunta p
            JOIN questionario_versao qv ON qv.id_versao = p.id_versao
            WHERE p.id_pergunta = ? AND qv.status = 'rascunho'
        ");
        $stmt->execute([(int) $idPergunta]);
        if ($stmt->rowCount() < 1) {
            throw new Exception('A pergunta não pertence a um rascunho editável.');
        }
        voltarQuestionario('Pergunta removida do rascunho.');
    }

    if ($action === 'publicar') {
        $idVersao = filter_var($_POST['id_versao'] ?? null, FILTER_VALIDATE_INT);
        if (!$idVersao) {
            throw new Exception('Versão inválida.');
        }
        $versao = exigirRascunho($pdo, (int) $idVersao);

        $stmtCategorias = $pdo->prepare("
            SELECT c.id_categoria, c.nome, COUNT(p.id_pergunta) AS total
            FROM categoria_triagem c
            LEFT JOIN pergunta p
              ON p.id_categoria = c.id_categoria
             AND p.id_versao = ?
             AND p.ativo = 1
            GROUP BY c.id_categoria, c.nome
        ");
        $stmtCategorias->execute([(int) $idVersao]);
        foreach ($stmtCategorias->fetchAll(PDO::FETCH_ASSOC) as $categoria) {
            if ((int) $categoria['total'] < 1) {
                throw new Exception('A categoria "' . $categoria['nome'] . '" precisa ter pelo menos uma pergunta ativa.');
            }
        }

        $stmtProblemas = $pdo->prepare("
            SELECT
                p.id_pergunta,
                p.enunciado,
                COUNT(o.id_opcao) AS total_opcoes,
                MIN(o.pontuacao) AS menor_pontuacao,
                MAX(o.pontuacao) AS maior_pontuacao
            FROM pergunta p
            LEFT JOIN opcao_resposta o
              ON o.id_pergunta = p.id_pergunta
             AND o.ativo = 1
            WHERE p.id_versao = ? AND p.ativo = 1
            GROUP BY p.id_pergunta, p.enunciado
            HAVING total_opcoes < 2 OR menor_pontuacao <> 0 OR maior_pontuacao <= 0
        ");
        $stmtProblemas->execute([(int) $idVersao]);
        $problema = $stmtProblemas->fetch(PDO::FETCH_ASSOC);
        if ($problema) {
            throw new Exception('Revise a pergunta "' . $problema['enunciado'] . '": ela precisa de pelo menos duas opções, pontuação mínima 0 e máxima maior que 0.');
        }

        $pdo->beginTransaction();
        $pdo->exec("UPDATE questionario_versao SET status = 'arquivado' WHERE status = 'ativo'");
        $stmtPublicar = $pdo->prepare("UPDATE questionario_versao SET status = 'ativo', publicado_em = CURRENT_TIMESTAMP WHERE id_versao = ? AND status = 'rascunho'");
        $stmtPublicar->execute([(int) $idVersao]);
        if ($stmtPublicar->rowCount() !== 1) {
            throw new Exception('Não foi possível publicar a versão.');
        }
        $pdo->commit();

        voltarQuestionario('Versão ' . (int) $versao['versao'] . ' publicada com sucesso.');
    }

    voltarQuestionario('', 'Ação administrativa inválida.');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    voltarQuestionario('', $e->getMessage());
}
