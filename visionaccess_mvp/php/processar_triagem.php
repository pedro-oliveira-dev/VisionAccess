<?php
declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/db_connect.php';
require_once '../includes/config.php';

function redirecionarComErro(string $mensagem): void
{
    $_SESSION['erro_triagem'] = $mensagem;
    header("Location: ../triagem.php");
    exit();
}

function extrairJsonDaRespostaIA(string $texto): array
{
    $texto = trim($texto);

    // Remove possíveis blocos markdown do tipo ```json ... ```
    $texto = preg_replace('/^```json\s*/i', '', $texto);
    $texto = preg_replace('/^```\s*/', '', $texto);
    $texto = preg_replace('/\s*```$/', '', $texto);

    $texto = trim($texto);

    // Primeira tentativa: decodificar diretamente
    $dados = json_decode($texto, true);
    if (is_array($dados)) {
        return $dados;
    }

    // Segunda tentativa: extrair apenas o trecho entre { e }
    $inicio = strpos($texto, '{');
    $fim = strrpos($texto, '}');

    if ($inicio !== false && $fim !== false && $fim > $inicio) {
        $possivelJson = substr($texto, $inicio, $fim - $inicio + 1);
        $dados = json_decode($possivelJson, true);

        if (is_array($dados)) {
            return $dados;
        }
    }

    error_log('Resposta bruta que falhou no parse: ' . $texto);
    error_log('Erro json_decode: ' . json_last_error_msg());

    throw new Exception('O conteúdo retornado pela IA não é um JSON válido.');
}

function analisarSintomasComGemini(string $descricaoTexto): array
{
    $apiKey = getenv('GEMINI_API_KEY');

    if (!$apiKey && defined('GEMINI_API_KEY')) {
        $apiKey = GEMINI_API_KEY;
    }

    if (!$apiKey) {
        throw new Exception('A chave da API Gemini não está configurada.');
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    $prompt = <<<PROMPT
Analise o relato de sintomas visuais do usuário e retorne apenas um JSON válido.

Escolha exatamente uma classificação entre:
- Miopia
- Hipermetropia
- Astigmatismo
- Presbiopia
- Condição ocular inespecífica
- Sem classificação clara

Regras:
- Miopia: dificuldade de enxergar de longe.
- Hipermetropia: dificuldade de enxergar de perto.
- Astigmatismo: visão borrada ou distorcida de perto e de longe.
- Presbiopia: dificuldade de perto associada à idade.
- Condição ocular inespecífica: ardor, vermelhidão, irritação, coceira, lacrimejamento, cansaço visual ou problemas de visão gerais.
- Use Condição ocular inespecífica também quando houver múltiplos sintomas mistos ou quando os sinais sugerirem uma doença ocular mais complexa ou não refrativa.
- Sem classificação clara: texto insuficiente, ambíguo ou fora do contexto visual.
- Não escreva nada além do JSON.
- Não use markdown.
- A confiança deve ser um número de 0 a 100.
- descricao_resultado deve ser curta.
- alerta deve dizer que o resultado é apenas orientativo e não substitui avaliação com oftalmologista.

Formato exato:
{"classificacao":"...","confianca":0,"descricao_resultado":"...","alerta":"..."}

Texto do usuário:
"{$descricaoTexto}"
PROMPT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 500,
            'thinkingConfig' => [
                'thinkingBudget' => 0
            ]
        ]
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'x-goog-api-key: ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $erroCurl = curl_error($ch);
        curl_close($ch);
        throw new Exception('Erro ao conectar com o Gemini: ' . $erroCurl);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log('Resposta completa da API Gemini: ' . $response);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new Exception("Erro da API Gemini. HTTP {$httpCode}. Resposta: {$response}");
    }

    $respostaCompleta = json_decode($response, true);

    if (!is_array($respostaCompleta)) {
        throw new Exception('Resposta inválida da API Gemini.');
    }

    $finishReason = $respostaCompleta['candidates'][0]['finishReason'] ?? '';

    if ($finishReason === 'MAX_TOKENS') {
        error_log('Resposta interrompida por MAX_TOKENS: ' . $response);
        throw new Exception('A resposta da IA foi interrompida por limite de tokens.');
    }

    $textoJson = $respostaCompleta['candidates'][0]['content']['parts'][0]['text'] ?? null;

    if (!$textoJson || trim($textoJson) === '') {
        error_log('Resposta decodificada sem texto esperado: ' . json_encode($respostaCompleta, JSON_UNESCAPED_UNICODE));
        throw new Exception('O Gemini não retornou conteúdo no formato esperado.');
    }

    error_log('Resposta bruta do Gemini: ' . $textoJson);

    return extrairJsonDaRespostaIA($textoJson);
}

function normalizarResultadoIA(array $resultadoIA): array
{
    $classificacoesPermitidas = [
        'Miopia',
        'Hipermetropia',
        'Astigmatismo',
        'Presbiopia',
        'Condição ocular inespecífica',
        'Sem classificação clara'
    ];

    $classificacao = $resultadoIA['classificacao'] ?? 'Sem classificação clara';
    if (!in_array($classificacao, $classificacoesPermitidas, true)) {
        $classificacao = 'Sem classificação clara';
    }

    $confianca = isset($resultadoIA['confianca']) ? (float) $resultadoIA['confianca'] : 0.00;
    $confianca = max(0, min(100, $confianca));

    $descricaoResultado = trim((string) ($resultadoIA['descricao_resultado'] ?? 'Não foi possível gerar uma descrição.'));
    if ($descricaoResultado === '') {
        $descricaoResultado = 'Não foi possível gerar uma descrição.';
    }

    $alerta = trim((string) ($resultadoIA['alerta'] ?? 'Resultado sugestivo. Não substitui avaliação profissional.'));
    if ($alerta === '') {
        $alerta = 'Resultado sugestivo. Não substitui avaliação profissional.';
    }

    return [
        'classificacao' => $classificacao,
        'confianca' => round($confianca, 2),
        'descricao_resultado' => $descricaoResultado,
        'alerta' => $alerta
    ];
}

function gerarResultadoFallback(string $motivo = ''): array
{
    $descricao = 'Não foi possível interpretar automaticamente os sintomas informados nesta triagem. O sistema registrou sua solicitação, mas a análise automática não pôde ser concluída com segurança.';

    if ($motivo !== '') {
        error_log('Fallback da IA acionado. Motivo: ' . $motivo);
    }

    return [
        'classificacao' => 'Sem classificação clara',
        'confianca' => 0.00,
        'descricao_resultado' => $descricao,
        'alerta' => 'Resultado sugestivo. Não substitui avaliação profissional.'
    ];
}

function inserirTriagem(PDO $pdo, int $idUsuario, string $descricaoTexto): int
{
    $sql = "
        INSERT INTO triagem (id_usuario, descricao_texto)
        VALUES (:id_usuario, :descricao_texto)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_usuario' => $idUsuario,
        ':descricao_texto' => $descricaoTexto
    ]);

    return (int) $pdo->lastInsertId();
}

function buscarPerguntas(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT id_pergunta, tipo FROM pergunta");
    return $stmt->fetchAll();
}

function inserirRespostasObjetivas(PDO $pdo, int $idTriagem, array $perguntas, array $postData): void
{
    $sql = "
        INSERT INTO resposta (id_triagem, id_pergunta, resposta_usuario)
        VALUES (:id_triagem, :id_pergunta, :resposta_usuario)
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($perguntas as $pergunta) {
        $idPergunta = (int) $pergunta['id_pergunta'];
        $campo = 'pergunta_' . $idPergunta;

        if (!isset($postData[$campo])) {
            throw new Exception("A pergunta {$idPergunta} não foi respondida.");
        }

        $respostaUsuario = trim((string) $postData[$campo]);

        if ($respostaUsuario === '') {
            throw new Exception("A resposta da pergunta {$idPergunta} está vazia.");
        }

        $stmt->execute([
            ':id_triagem' => $idTriagem,
            ':id_pergunta' => $idPergunta,
            ':resposta_usuario' => $respostaUsuario
        ]);
    }
}

function inserirResultadoComProcedure(PDO $pdo, int $idTriagem, string $descricaoTexto, array $resultadoIA): void
{
    $respostaIaCompleta = [
        'entrada_usuario' => $descricaoTexto,
        'saida_ia' => $resultadoIA
    ];

    $descricaoFinal = $resultadoIA['descricao_resultado'] . "\n\n" . $resultadoIA['alerta'];

    $sql = "CALL inserir_resultado(:p_id_triagem, :p_classificacao, :p_confianca, :p_descricao, :p_resposta_ia)";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':p_id_triagem', $idTriagem, PDO::PARAM_INT);
    $stmt->bindValue(':p_classificacao', $resultadoIA['classificacao'], PDO::PARAM_STR);
    $stmt->bindValue(':p_confianca', $resultadoIA['confianca']);
    $stmt->bindValue(':p_descricao', $descricaoFinal, PDO::PARAM_STR);
    $stmt->bindValue(':p_resposta_ia', json_encode($respostaIaCompleta, JSON_UNESCAPED_UNICODE), PDO::PARAM_STR);
    $stmt->execute();
    $stmt->closeCursor();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirecionarComErro('Método de envio inválido.');
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Falha na conexão com o banco.');
    }

    $idUsuario = (int) $_SESSION['user_id'];
    $descricaoTexto = trim($_POST['descricao_texto'] ?? '');

    if ($descricaoTexto === '') {
        redirecionarComErro('A descrição dos sintomas é obrigatória.');
    }

    $perguntas = buscarPerguntas($pdo);

    if (!$perguntas) {
        throw new Exception('Nenhuma pergunta cadastrada foi encontrada.');
    }

    $pdo->beginTransaction();

    $idTriagem = inserirTriagem($pdo, $idUsuario, $descricaoTexto);
    inserirRespostasObjetivas($pdo, $idTriagem, $perguntas, $_POST);

    try {
        $resultadoBrutoIA = analisarSintomasComGemini($descricaoTexto);
        $resultadoFinalIA = normalizarResultadoIA($resultadoBrutoIA);
    } catch (Exception $e) {
        error_log('Falha na análise do Gemini para a triagem ' . $idTriagem . ': ' . $e->getMessage());
        $resultadoFinalIA = gerarResultadoFallback($e->getMessage());
    }

    inserirResultadoComProcedure($pdo, $idTriagem, $descricaoTexto, $resultadoFinalIA);

    $pdo->commit();

    $_SESSION['sucesso_triagem'] = 'Triagem enviada e processada com sucesso.';
    $_SESSION['id_triagem'] = $idTriagem;

    header("Location: ../resultado.php?id=" . $idTriagem);
    exit();

} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro em processar_triagem.php: ' . $e->getMessage());

    $_SESSION['erro_triagem'] = $e->getMessage();
    header("Location: ../triagem.php");
    exit();
}