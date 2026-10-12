<?php
declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

require_once '../includes/db_connect.php';
require_once '../includes/config.php';
require_once '../includes/csrf.php';

const MODELO_GEMINI = 'gemini-2.5-flash';

function redirecionarComErro(string $mensagem): void
{
    $_SESSION['erro_triagem'] = $mensagem;
    header('Location: ../triagem.php');
    exit();
}

function extrairJsonDaRespostaIA(string $texto): array
{
    $texto = trim($texto);
    $texto = preg_replace('/^```json\s*/i', '', $texto);
    $texto = preg_replace('/^```\s*/', '', $texto);
    $texto = preg_replace('/\s*```$/', '', $texto);
    $texto = trim($texto);

    $dados = json_decode($texto, true);
    if (is_array($dados)) {
        return $dados;
    }

    $inicio = strpos($texto, '{');
    $fim = strrpos($texto, '}');
    if ($inicio !== false && $fim !== false && $fim > $inicio) {
        $dados = json_decode(substr($texto, $inicio, $fim - $inicio + 1), true);
        if (is_array($dados)) {
            return $dados;
        }
    }

    throw new Exception('A IA não retornou JSON válido.');
}

function buscarPromptAtivo(PDO $pdo): ?array
{
    $stmt = $pdo->query("
        SELECT id_prompt, nome, versao, conteudo
        FROM prompt_agente
        WHERE ativo = 1
        ORDER BY versao DESC
        LIMIT 1
    ");
    $prompt = $stmt->fetch(PDO::FETCH_ASSOC);

    return $prompt ?: null;
}

function validarVersaoQuestionario(PDO $pdo, int $idVersao): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM questionario_versao
        WHERE id_versao = ?
          AND status IN ('ativo', 'arquivado')
    ");
    $stmt->execute([$idVersao]);

    if ((int) $stmt->fetchColumn() !== 1) {
        throw new Exception('A versão do questionário enviada não é válida. Atualize a página e tente novamente.');
    }
}

function validarEPrepararRespostas(PDO $pdo, int $idVersao, array $respostas): array
{
    $stmt = $pdo->prepare("
        SELECT
            p.id_pergunta,
            p.enunciado,
            p.id_categoria,
            c.nome AS categoria,
            o.id_opcao,
            o.texto AS opcao_texto,
            o.pontuacao
        FROM pergunta p
        JOIN categoria_triagem c ON c.id_categoria = p.id_categoria
        JOIN opcao_resposta o ON o.id_pergunta = p.id_pergunta
        WHERE p.id_versao = ?
          AND p.ativo = 1
          AND o.ativo = 1
        ORDER BY p.ordem, p.id_pergunta, o.ordem, o.id_opcao
    ");
    $stmt->execute([$idVersao]);
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$linhas) {
        throw new Exception('Nenhuma pergunta ativa foi encontrada para esta versão do questionário.');
    }

    $mapa = [];
    foreach ($linhas as $linha) {
        $idPergunta = (int) $linha['id_pergunta'];
        if (!isset($mapa[$idPergunta])) {
            $mapa[$idPergunta] = [
                'id_pergunta' => $idPergunta,
                'enunciado' => $linha['enunciado'],
                'id_categoria' => (int) $linha['id_categoria'],
                'categoria' => $linha['categoria'],
                'opcoes' => [],
            ];
        }
        $mapa[$idPergunta]['opcoes'][(int) $linha['id_opcao']] = [
            'texto' => $linha['opcao_texto'],
            'pontuacao' => (int) $linha['pontuacao'],
        ];
    }

    $preparadas = [];
    foreach ($mapa as $idPergunta => $pergunta) {
        if (!array_key_exists($idPergunta, $respostas)) {
            throw new Exception('Todas as perguntas do questionário são obrigatórias.');
        }

        $idOpcao = filter_var($respostas[$idPergunta], FILTER_VALIDATE_INT);
        if (!$idOpcao || !isset($pergunta['opcoes'][(int) $idOpcao])) {
            throw new Exception('Uma das opções enviadas não pertence à pergunta correspondente.');
        }

        $opcao = $pergunta['opcoes'][(int) $idOpcao];
        $preparadas[] = [
            'id_pergunta' => $idPergunta,
            'id_opcao' => (int) $idOpcao,
            'pergunta' => $pergunta['enunciado'],
            'categoria' => $pergunta['categoria'],
            'resposta' => $opcao['texto'],
            'pontuacao' => $opcao['pontuacao'],
        ];
    }

    return $preparadas;
}

function analisarSintomasComGemini(array $promptAtivo, string $descricaoTexto, array $respostasEstruturadas): array
{
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey && defined('GEMINI_API_KEY')) {
        $apiKey = GEMINI_API_KEY;
    }
    if (!$apiKey) {
        throw new Exception('A chave da API Gemini não está configurada.');
    }

    $contrato = <<<'CONTRATO'

REGRAS OBRIGATÓRIAS DE SAÍDA DO SISTEMA:
- O resultado é uma triagem orientativa e não um diagnóstico médico.
- Escolha exatamente uma classificação entre: Miopia, Hipermetropia, Astigmatismo, Presbiopia, Condição ocular inespecífica, Sem classificação clara.
- Use Sem classificação clara quando os dados forem insuficientes, ambíguos ou fora do contexto visual.
- Retorne somente um objeto JSON válido, sem markdown e sem texto fora do JSON.
- A confiança deve ser um número de 0 a 100.
- descricao_resultado deve ser curta e escrita em linguagem simples.
- alerta deve informar que o resultado é orientativo e não substitui avaliação com oftalmologista.
Formato obrigatório:
{"classificacao":"...","confianca":0,"descricao_resultado":"...","alerta":"..."}
CONTRATO;

    $systemInstruction = trim($promptAtivo['conteudo']) . "\n" . $contrato;

    $dadosUsuario = [
        'relato' => $descricaoTexto,
        'questionario' => array_map(function (array $item): array {
            return [
                'pergunta' => $item['pergunta'],
                'categoria' => $item['categoria'],
                'resposta' => $item['resposta'],
                'pontuacao' => $item['pontuacao'],
            ];
        }, $respostasEstruturadas),
    ];

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . MODELO_GEMINI . ':generateContent';
    $payload = [
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction],
            ],
        ],
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => "Analise os dados estruturados abaixo:\n" . json_encode($dadosUsuario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ],
                ],
            ],
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 500,
            'responseMimeType' => 'application/json',
            'thinkingConfig' => [
                'thinkingBudget' => 0,
            ],
        ],
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $erroCurl = curl_error($ch);
        curl_close($ch);
        throw new Exception('Erro ao conectar com o Gemini: ' . $erroCurl);
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log('Gemini HTTP ' . $httpCode . ': ' . $response);
        throw new Exception('A API de IA retornou erro HTTP ' . $httpCode . '.');
    }

    $respostaCompleta = json_decode($response, true);
    if (!is_array($respostaCompleta)) {
        throw new Exception('Resposta inválida da API Gemini.');
    }

    $textoJson = $respostaCompleta['candidates'][0]['content']['parts'][0]['text'] ?? null;
    if (!$textoJson || trim((string) $textoJson) === '') {
        throw new Exception('O Gemini não retornou conteúdo no formato esperado.');
    }

    return extrairJsonDaRespostaIA((string) $textoJson);
}

function normalizarResultadoIA(array $resultadoIA): array
{
    $permitidas = [
        'Miopia',
        'Hipermetropia',
        'Astigmatismo',
        'Presbiopia',
        'Condição ocular inespecífica',
        'Sem classificação clara',
    ];

    $classificacao = $resultadoIA['classificacao'] ?? 'Sem classificação clara';
    if (!in_array($classificacao, $permitidas, true)) {
        $classificacao = 'Sem classificação clara';
    }

    $confianca = isset($resultadoIA['confianca']) ? (float) $resultadoIA['confianca'] : 0.0;
    $confianca = max(0, min(100, $confianca));

    $descricao = trim((string) ($resultadoIA['descricao_resultado'] ?? 'Não foi possível gerar uma descrição.'));
    if ($descricao === '') {
        $descricao = 'Não foi possível gerar uma descrição.';
    }

    $alerta = trim((string) ($resultadoIA['alerta'] ?? 'Este resultado é apenas orientativo e não substitui avaliação com oftalmologista.'));
    if ($alerta === '') {
        $alerta = 'Este resultado é apenas orientativo e não substitui avaliação com oftalmologista.';
    }

    return [
        'classificacao' => $classificacao,
        'confianca' => round($confianca, 2),
        'descricao_resultado' => $descricao,
        'alerta' => $alerta,
    ];
}

function gerarResultadoFallback(string $motivo = ''): array
{
    if ($motivo !== '') {
        error_log('Fallback da IA: ' . $motivo);
    }

    return [
        'classificacao' => 'Sem classificação clara',
        'confianca' => 0.00,
        'descricao_resultado' => 'A análise automática não pôde ser concluída com segurança. O questionário objetivo continuará sendo utilizado como apoio à triagem.',
        'alerta' => 'Este resultado é apenas orientativo e não substitui avaliação com oftalmologista.',
    ];
}

function inserirTriagem(PDO $pdo, int $idUsuario, int $idVersao, string $descricaoTexto): int
{
    $stmt = $pdo->prepare('INSERT INTO triagem (id_usuario, id_questionario_versao, descricao_texto) VALUES (?, ?, ?)');
    $stmt->execute([$idUsuario, $idVersao, $descricaoTexto]);
    return (int) $pdo->lastInsertId();
}

function inserirRespostasObjetivas(PDO $pdo, int $idTriagem, array $respostasEstruturadas): void
{
    $stmt = $pdo->prepare('INSERT INTO resposta (id_triagem, id_pergunta, id_opcao) VALUES (?, ?, ?)');
    foreach ($respostasEstruturadas as $item) {
        $stmt->execute([$idTriagem, (int) $item['id_pergunta'], (int) $item['id_opcao']]);
    }
}

function calcularPontuacaoTriagem(PDO $pdo, int $idTriagem): void
{
    $pdo->prepare('DELETE FROM pontuacao_triagem WHERE id_triagem = ?')->execute([$idTriagem]);

    $sql = "
        INSERT INTO pontuacao_triagem (
            id_triagem,
            id_categoria,
            pontuacao_total,
            pontuacao_maxima,
            percentual
        )
        SELECT
            r.id_triagem,
            p.id_categoria,
            SUM(o.pontuacao) AS pontuacao_total,
            SUM(mx.max_pontuacao) AS pontuacao_maxima,
            CASE
                WHEN SUM(mx.max_pontuacao) > 0
                    THEN ROUND((SUM(o.pontuacao) / SUM(mx.max_pontuacao)) * 100, 2)
                ELSE 0.00
            END AS percentual
        FROM resposta r
        JOIN pergunta p ON p.id_pergunta = r.id_pergunta
        JOIN opcao_resposta o ON o.id_opcao = r.id_opcao
        JOIN (
            SELECT id_pergunta, MAX(pontuacao) AS max_pontuacao
            FROM opcao_resposta
            WHERE ativo = 1
            GROUP BY id_pergunta
        ) mx ON mx.id_pergunta = r.id_pergunta
        WHERE r.id_triagem = ?
        GROUP BY r.id_triagem, p.id_categoria
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idTriagem]);
}

function buscarClassificacaoInterna(PDO $pdo, int $idTriagem): array
{
    $stmt = $pdo->prepare("
        SELECT
            c.nome AS classificacao_interna,
            pt.pontuacao_total,
            pt.pontuacao_maxima,
            pt.percentual
        FROM pontuacao_triagem pt
        JOIN categoria_triagem c ON c.id_categoria = pt.id_categoria
        WHERE pt.id_triagem = ?
        ORDER BY pt.percentual DESC, pt.pontuacao_total DESC, c.nome ASC
        LIMIT 1
    ");
    $stmt->execute([$idTriagem]);
    $linha = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$linha) {
        return [
            'classificacao_interna' => 'Sem indício interno claro',
            'pontuacao_interna' => 0,
            'pontuacao_maxima' => 0,
            'percentual_interno' => 0.00,
            'nivel_risco' => 'Sem indício relevante',
        ];
    }

    $percentual = (float) $linha['percentual'];
    if ($percentual <= 0) {
        $nivel = 'Sem indício relevante';
    } elseif ($percentual <= 33.33) {
        $nivel = 'Baixo';
    } elseif ($percentual <= 66.66) {
        $nivel = 'Moderado';
    } else {
        $nivel = 'Alto';
    }

    return [
        'classificacao_interna' => (string) $linha['classificacao_interna'],
        'pontuacao_interna' => (int) $linha['pontuacao_total'],
        'pontuacao_maxima' => (int) $linha['pontuacao_maxima'],
        'percentual_interno' => round($percentual, 2),
        'nivel_risco' => $nivel,
    ];
}

function categoriaEsperadaPelaIA(string $classificacaoIA): ?string
{
    switch ($classificacaoIA) {
        case 'Miopia':
            return 'Visão de longe';
        case 'Hipermetropia':
        case 'Presbiopia':
            return 'Visão de perto';
        case 'Astigmatismo':
            return 'Astigmatismo';
        case 'Condição ocular inespecífica':
            return 'Condições oculares';
        default:
            return null;
    }
}

function montarResultadoFinal(array $resultadoIA, array $resultadoInterno): array
{
    $classificacaoIA = $resultadoIA['classificacao'];
    $classificacaoInterna = $resultadoInterno['classificacao_interna'];
    $pontuacao = (int) $resultadoInterno['pontuacao_interna'];
    $maxima = (int) $resultadoInterno['pontuacao_maxima'];
    $percentual = number_format((float) $resultadoInterno['percentual_interno'], 2, ',', '.');
    $nivel = $resultadoInterno['nivel_risco'];
    $categoriaEsperada = categoriaEsperadaPelaIA($classificacaoIA);

    $resumoInterno = "{$classificacaoInterna}, com {$pontuacao} de {$maxima} ponto(s) ({$percentual}%), nível {$nivel}";

    if ($classificacaoIA === 'Sem classificação clara') {
        return [
            'resultado_final' => $classificacaoInterna,
            'descricao_resultado' => "A IA não encontrou uma classificação clara. Pela análise objetiva, o maior indicativo foi {$resumoInterno}.\n\n{$resultadoIA['alerta']}",
        ];
    }

    if ($categoriaEsperada !== null && $categoriaEsperada === $classificacaoInterna) {
        return [
            'resultado_final' => $classificacaoIA . ' / ' . $classificacaoInterna,
            'descricao_resultado' => "A análise da IA indicou {$classificacaoIA}. O questionário apresentou o mesmo grupo predominante: {$resumoInterno}.\n\n{$resultadoIA['descricao_resultado']}\n\n{$resultadoIA['alerta']}",
        ];
    }

    return [
        'resultado_final' => $classificacaoIA . ' com indicativo interno de ' . $classificacaoInterna,
        'descricao_resultado' => "A análise da IA indicou {$classificacaoIA}. Pela análise objetiva, o maior indicativo foi {$resumoInterno}. O sistema apresenta os dois resultados como informações complementares de uma triagem orientativa.\n\n{$resultadoIA['descricao_resultado']}\n\n{$resultadoIA['alerta']}",
    ];
}

function inserirResultadoFinal(
    PDO $pdo,
    int $idTriagem,
    ?array $promptAtivo,
    string $descricaoTexto,
    array $respostasEstruturadas,
    array $resultadoIA,
    array $resultadoInterno,
    array $resultadoCombinado
): void {
    $respostaIaCompleta = [
        'entrada_usuario' => [
            'relato' => $descricaoTexto,
            'questionario' => $respostasEstruturadas,
        ],
        'saida_ia' => $resultadoIA,
    ];

    $sql = "
        INSERT INTO resultado (
            id_triagem,
            id_prompt,
            classificacao_ia,
            confianca_ia,
            descricao_ia,
            resposta_ia,
            prompt_snapshot,
            modelo_ia,
            classificacao_interna,
            pontuacao_interna,
            percentual_interno,
            nivel_risco,
            resultado_final,
            descricao_resultado
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $idTriagem,
        $promptAtivo ? (int) $promptAtivo['id_prompt'] : null,
        $resultadoIA['classificacao'],
        $resultadoIA['confianca'],
        $resultadoIA['descricao_resultado'],
        json_encode($respostaIaCompleta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $promptAtivo['conteudo'] ?? null,
        MODELO_GEMINI,
        $resultadoInterno['classificacao_interna'],
        $resultadoInterno['pontuacao_interna'],
        $resultadoInterno['percentual_interno'],
        $resultadoInterno['nivel_risco'],
        $resultadoCombinado['resultado_final'],
        $resultadoCombinado['descricao_resultado'],
    ]);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirecionarComErro('Método de envio inválido.');
    }

    if (!validarCsrf($_POST['csrf_token'] ?? null)) {
        redirecionarComErro('O formulário expirou. Atualize a página e tente novamente.');
    }

    if (isset($_SESSION['ultima_triagem']) && (time() - (int) $_SESSION['ultima_triagem']) < 10) {
        redirecionarComErro('Aguarde alguns segundos antes de enviar outra triagem.');
    }

    $idUsuario = (int) $_SESSION['user_id'];
    $idVersao = filter_var($_POST['id_questionario_versao'] ?? null, FILTER_VALIDATE_INT);
    $descricaoTexto = trim($_POST['descricao_texto'] ?? '');
    $respostas = $_POST['respostas'] ?? [];

    if (!$idVersao) {
        redirecionarComErro('Versão do questionário inválida.');
    }
    if ($descricaoTexto === '') {
        redirecionarComErro('A descrição dos sintomas é obrigatória.');
    }
    if (mb_strlen($descricaoTexto) > 1000) {
        redirecionarComErro('A descrição deve ter no máximo 1000 caracteres.');
    }
    if (!is_array($respostas)) {
        redirecionarComErro('Formato inválido das respostas objetivas.');
    }

    validarVersaoQuestionario($pdo, (int) $idVersao);
    $respostasEstruturadas = validarEPrepararRespostas($pdo, (int) $idVersao, $respostas);
    $promptAtivo = buscarPromptAtivo($pdo);

    $_SESSION['ultima_triagem'] = time();

    if ($promptAtivo) {
        try {
            $resultadoBrutoIA = analisarSintomasComGemini($promptAtivo, $descricaoTexto, $respostasEstruturadas);
            $resultadoFinalIA = normalizarResultadoIA($resultadoBrutoIA);
        } catch (Exception $e) {
            $resultadoFinalIA = gerarResultadoFallback($e->getMessage());
        }
    } else {
        $resultadoFinalIA = gerarResultadoFallback('Nenhum prompt ativo configurado.');
    }

    $pdo->beginTransaction();

    $idTriagem = inserirTriagem($pdo, $idUsuario, (int) $idVersao, $descricaoTexto);
    inserirRespostasObjetivas($pdo, $idTriagem, $respostasEstruturadas);
    calcularPontuacaoTriagem($pdo, $idTriagem);
    $resultadoInterno = buscarClassificacaoInterna($pdo, $idTriagem);
    $resultadoCombinado = montarResultadoFinal($resultadoFinalIA, $resultadoInterno);
    inserirResultadoFinal($pdo, $idTriagem, $promptAtivo, $descricaoTexto, $respostasEstruturadas, $resultadoFinalIA, $resultadoInterno, $resultadoCombinado);

    $pdo->commit();

    $_SESSION['sucesso_triagem'] = 'Triagem enviada e processada com sucesso.';
    $_SESSION['id_triagem'] = $idTriagem;
    header('Location: ../resultado.php?id=' . $idTriagem);
    exit();
} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro em processar_triagem.php: ' . $e->getMessage());
    $_SESSION['erro_triagem'] = 'Não foi possível concluir a triagem. ' . $e->getMessage();
    header('Location: ../triagem.php');
    exit();
}
