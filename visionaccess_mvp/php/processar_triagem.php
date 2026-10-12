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

function buscarPerguntasAtivas(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT id_pergunta
        FROM pergunta
        WHERE ativo = TRUE
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function opcaoPertencePergunta(PDO $pdo, int $idPergunta, int $idOpcao): bool
{
    $sql = "
        SELECT COUNT(*)
        FROM opcao_resposta
        WHERE id_opcao = :id_opcao
          AND id_pergunta = :id_pergunta
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_opcao' => $idOpcao,
        ':id_pergunta' => $idPergunta
    ]);

    return (int) $stmt->fetchColumn() > 0;
}

function inserirRespostasObjetivas(PDO $pdo, int $idTriagem, array $perguntasAtivas, array $respostas): void
{
    if (empty($respostas)) {
        throw new Exception('Nenhuma resposta objetiva foi enviada.');
    }

    $sql = "
        INSERT INTO resposta (id_triagem, id_pergunta, id_opcao)
        VALUES (:id_triagem, :id_pergunta, :id_opcao)
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($perguntasAtivas as $idPergunta) {
        $idPergunta = (int) $idPergunta;

        if (!isset($respostas[$idPergunta])) {
            throw new Exception("A pergunta {$idPergunta} não foi respondida.");
        }

        $idOpcao = filter_var($respostas[$idPergunta], FILTER_VALIDATE_INT);
        if (!$idOpcao) {
            throw new Exception("A opção selecionada para a pergunta {$idPergunta} é inválida.");
        }

        if (!opcaoPertencePergunta($pdo, $idPergunta, (int) $idOpcao)) {
            throw new Exception("A opção selecionada não pertence à pergunta {$idPergunta}.");
        }

        $stmt->execute([
            ':id_triagem' => $idTriagem,
            ':id_pergunta' => $idPergunta,
            ':id_opcao' => (int) $idOpcao
        ]);
    }
}

function calcularPontuacaoTriagem(PDO $pdo, int $idTriagem): void
{
    $pdo->prepare("DELETE FROM pontuacao_triagem WHERE id_triagem = :id_triagem")
        ->execute([':id_triagem' => $idTriagem]);

    $sql = "
        INSERT INTO pontuacao_triagem (id_triagem, id_categoria, pontuacao_total)
        SELECT
            r.id_triagem,
            p.id_categoria,
            SUM(o.pontuacao) AS pontuacao_total
        FROM resposta r
        JOIN pergunta p ON p.id_pergunta = r.id_pergunta
        JOIN opcao_resposta o ON o.id_opcao = r.id_opcao
        WHERE r.id_triagem = :id_triagem
        GROUP BY r.id_triagem, p.id_categoria
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_triagem' => $idTriagem]);
}

function buscarClassificacaoInterna(PDO $pdo, int $idTriagem): array
{
    $sql = "
        SELECT
            c.nome AS classificacao_interna,
            pt.pontuacao_total
        FROM pontuacao_triagem pt
        JOIN categoria_triagem c ON c.id_categoria = pt.id_categoria
        WHERE pt.id_triagem = :id_triagem
        ORDER BY pt.pontuacao_total DESC, c.nome ASC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_triagem' => $idTriagem]);
    $linha = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$linha) {
        return [
            'classificacao_interna' => 'Sem indício interno claro',
            'pontuacao_interna' => 0,
            'nivel_risco' => 'Baixo'
        ];
    }

    $pontuacao = (int) $linha['pontuacao_total'];

    if ($pontuacao >= 7) {
        $nivelRisco = 'Alto';
    } elseif ($pontuacao >= 4) {
        $nivelRisco = 'Moderado';
    } elseif ($pontuacao >= 1) {
        $nivelRisco = 'Baixo';
    } else {
        $nivelRisco = 'Sem indício relevante';
    }

    return [
        'classificacao_interna' => (string) $linha['classificacao_interna'],
        'pontuacao_interna' => $pontuacao,
        'nivel_risco' => $nivelRisco
    ];
}

function categoriaEsperadaPelaIA(string $classificacaoIA): ?string
{
    return match ($classificacaoIA) {
        'Miopia' => 'Visão de longe',
        'Hipermetropia', 'Presbiopia' => 'Visão de perto',
        'Astigmatismo' => 'Astigmatismo',
        'Condição ocular inespecífica' => 'Condições oculares',
        default => null
    };
}

function montarResultadoFinal(array $resultadoIA, array $resultadoInterno): array
{
    $classificacaoIA = $resultadoIA['classificacao'];
    $classificacaoInterna = $resultadoInterno['classificacao_interna'];
    $pontuacaoInterna = (int) $resultadoInterno['pontuacao_interna'];
    $nivelRisco = $resultadoInterno['nivel_risco'];

    $categoriaEsperada = categoriaEsperadaPelaIA($classificacaoIA);
    $descricaoIA = $resultadoIA['descricao_resultado'];
    $alerta = $resultadoIA['alerta'];

    if ($classificacaoIA === 'Sem classificação clara') {
        $resultadoFinal = $classificacaoInterna;
        $descricaoFinal = "A IA não encontrou uma classificação clara a partir do relato informado. Pelas respostas objetivas, a maior pontuação foi em {$classificacaoInterna}, com {$pontuacaoInterna} ponto(s), indicando nível {$nivelRisco}.\n\n{$alerta}";
    } elseif ($categoriaEsperada !== null && $categoriaEsperada === $classificacaoInterna) {
        $resultadoFinal = $classificacaoIA . ' / ' . $classificacaoInterna;
        $descricaoFinal = "A análise da IA indicou {$classificacaoIA}. As respostas objetivas também apontaram maior pontuação em {$classificacaoInterna}, com {$pontuacaoInterna} ponto(s), indicando nível {$nivelRisco}.\n\n{$descricaoIA}\n\n{$alerta}";
    } else {
        $resultadoFinal = $classificacaoIA . ' com indicativo interno de ' . $classificacaoInterna;
        $descricaoFinal = "A análise da IA indicou {$classificacaoIA}. Porém, pelas respostas objetivas, a maior pontuação foi em {$classificacaoInterna}, com {$pontuacaoInterna} ponto(s), indicando nível {$nivelRisco}. O sistema apresenta os dois indicativos como triagem orientativa.\n\n{$descricaoIA}\n\n{$alerta}";
    }

    return [
        'resultado_final' => $resultadoFinal,
        'descricao_resultado' => $descricaoFinal
    ];
}

function inserirResultadoFinal(PDO $pdo, int $idTriagem, string $descricaoTexto, array $resultadoIA, array $resultadoInterno, array $resultadoCombinado): void
{
    $respostaIaCompleta = [
        'entrada_usuario' => $descricaoTexto,
        'saida_ia' => $resultadoIA
    ];

    $sql = "
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
        ) VALUES (
            :id_triagem,
            :classificacao_ia,
            :confianca_ia,
            :descricao_ia,
            :resposta_ia,
            :classificacao_interna,
            :pontuacao_interna,
            :nivel_risco,
            :resultado_final,
            :descricao_resultado
        )
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_triagem' => $idTriagem,
        ':classificacao_ia' => $resultadoIA['classificacao'],
        ':confianca_ia' => $resultadoIA['confianca'],
        ':descricao_ia' => $resultadoIA['descricao_resultado'],
        ':resposta_ia' => json_encode($respostaIaCompleta, JSON_UNESCAPED_UNICODE),
        ':classificacao_interna' => $resultadoInterno['classificacao_interna'],
        ':pontuacao_interna' => $resultadoInterno['pontuacao_interna'],
        ':nivel_risco' => $resultadoInterno['nivel_risco'],
        ':resultado_final' => $resultadoCombinado['resultado_final'],
        ':descricao_resultado' => $resultadoCombinado['descricao_resultado']
    ]);
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
    $respostas = $_POST['respostas'] ?? [];

    if ($descricaoTexto === '') {
        redirecionarComErro('A descrição dos sintomas é obrigatória.');
    }

    if (!is_array($respostas)) {
        redirecionarComErro('Formato inválido das respostas objetivas.');
    }

    $perguntasAtivas = buscarPerguntasAtivas($pdo);

    if (!$perguntasAtivas) {
        throw new Exception('Nenhuma pergunta cadastrada foi encontrada.');
    }

    $pdo->beginTransaction();

    $idTriagem = inserirTriagem($pdo, $idUsuario, $descricaoTexto);
    inserirRespostasObjetivas($pdo, $idTriagem, $perguntasAtivas, $respostas);
    calcularPontuacaoTriagem($pdo, $idTriagem);
    $resultadoInterno = buscarClassificacaoInterna($pdo, $idTriagem);

    try {
        $resultadoBrutoIA = analisarSintomasComGemini($descricaoTexto);
        $resultadoFinalIA = normalizarResultadoIA($resultadoBrutoIA);
    } catch (Exception $e) {
        error_log('Falha na análise do Gemini para a triagem ' . $idTriagem . ': ' . $e->getMessage());
        $resultadoFinalIA = gerarResultadoFallback($e->getMessage());
    }

    $resultadoCombinado = montarResultadoFinal($resultadoFinalIA, $resultadoInterno);
    inserirResultadoFinal($pdo, $idTriagem, $descricaoTexto, $resultadoFinalIA, $resultadoInterno, $resultadoCombinado);

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
