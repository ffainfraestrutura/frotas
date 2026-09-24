<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
header('Content-Type: text/html; charset=utf-8');

function gerarConteudoPdfRelatorio(int $idVistoria): array
{
    $getOriginal = $_GET;
    $_GET['id'] = $idVistoria;
    $_GET['formato'] = 'pdf';
    $nivelBuffer = ob_get_level();
    ob_start();

    try {
        include __DIR__ . '/../verrelatorio.php';
        $conteudo = (string) ob_get_clean();
    } catch (Throwable $erro) {
        while (ob_get_level() > $nivelBuffer) {
            ob_end_clean();
        }
        $_GET = $getOriginal;
        error_log('Falha ao gerar PDF da vistoria ' . $idVistoria . ': ' . $erro->getMessage());
        return ['ok' => false, 'conteudo' => '', 'erro' => 'Não foi possível gerar o PDF completo da vistoria.'];
    }

    $_GET = $getOriginal;
    if (strncmp($conteudo, '%PDF-', 5) !== 0) {
        return ['ok' => false, 'conteudo' => '', 'erro' => 'O gerador não retornou um PDF válido.'];
    }

    return ['ok' => true, 'conteudo' => $conteudo, 'erro' => ''];
}

function criarRelatorioTemporario(int $idVistoria): array
{
    $relatorio = gerarConteudoPdfRelatorio($idVistoria);
    if (($relatorio['ok'] ?? false) !== true) {
        return ['ok' => false, 'arquivo' => null, 'erro' => (string) ($relatorio['erro'] ?? 'Falha ao gerar relatório.')];
    }
    $conteudo = (string) ($relatorio['conteudo'] ?? '');

    $tmpBase = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    $tmpDir = $tmpBase . DIRECTORY_SEPARATOR . 'frotas_docs';
    if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não foi possível criar pasta temporária para o PDF.'];
    }

    $arquivoDestino = $tmpDir . DIRECTORY_SEPARATOR . 'vistoria_' . $idVistoria . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $bytesEscritos = @file_put_contents($arquivoDestino, $conteudo);
    if ($bytesEscritos === false || $bytesEscritos < 1) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não foi possível salvar o PDF temporário.'];
    }

    $assinaturaPdf = @file_get_contents($arquivoDestino, false, null, 0, 5);
    if ($assinaturaPdf !== '%PDF-') {
        @unlink($arquivoDestino);
        return ['ok' => false, 'arquivo' => null, 'erro' => 'O relatório não foi gerado como PDF válido.'];
    }

    return ['ok' => true, 'arquivo' => $arquivoDestino, 'erro' => ''];
}

function buscarConfiguracaoAssinatura(mysqli $con, string $databaseName): array
{
    if (preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
        return ['ok' => false, 'erro' => 'Banco de dados da configuração inválido.'];
    }

    $sql = "SELECT endpoint_base, api_key, grupo_id
              FROM `{$databaseName}`.`tbintegracao_api`
             WHERE servico = 'assinatura_documentos' AND ativo = 1
             LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt || !mysqli_stmt_execute($stmt)) {
        return ['ok' => false, 'erro' => 'Não foi possível consultar a configuração da API de assinatura.'];
    }

    $configuracao = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
    $endpointBase = rtrim(trim((string) ($configuracao['endpoint_base'] ?? '')), '/');
    $apiKey = trim((string) ($configuracao['api_key'] ?? ''));
    $grupoId = (int) ($configuracao['grupo_id'] ?? 0);
    if ($endpointBase === '' || !filter_var($endpointBase, FILTER_VALIDATE_URL) || $apiKey === '' || $grupoId < 1) {
        return ['ok' => false, 'erro' => 'Configuração da API de assinatura ausente ou incompleta.'];
    }

    return [
        'ok' => true,
        'endpoint_base' => $endpointBase,
        'api_key' => $apiKey,
        'grupo_id' => $grupoId,
        'erro' => '',
    ];
}

function enviarRelatorioParaAssinatura(mysqli $con, string $databaseName, string $matricula, string $placa, int $idVistoria): array
{
    $matricula = trim($matricula);
    if ($matricula === '') {
        return [
            'ok' => false,
            'erro' => 'Matrícula do funcionário ausente.',
            'http_status' => 0,
            'resposta' => '',
        ];
    }

    $configuracao = buscarConfiguracaoAssinatura($con, $databaseName);
    if (($configuracao['ok'] ?? false) !== true) {
        return [
            'ok' => false,
            'erro' => (string) ($configuracao['erro'] ?? 'Configuração da API de assinatura indisponível.'),
            'http_status' => 0,
            'resposta' => '',
        ];
    }

    $endpoint = (string) $configuracao['endpoint_base'] . '/internal/'
        . rawurlencode($matricula)
        . '/documentos/individual';
    $apiKey = (string) $configuracao['api_key'];
    $identificacao = trim($placa) !== '' ? ' - ' . strtoupper(trim($placa)) : '';

    $download = criarRelatorioTemporario($idVistoria);
    if (($download['ok'] ?? false) !== true || empty($download['arquivo'])) {
        return [
            'ok' => false,
            'erro' => (string) ($download['erro'] ?? 'Falha ao preparar arquivo do relatório.'),
            'http_status' => 0,
            'resposta' => '',
        ];
    }

    $arquivoPdf = (string) $download['arquivo'];
    if (!function_exists('curl_init')) {
        @unlink($arquivoPdf);
        return [
            'ok' => false,
            'erro' => 'Extensão cURL não disponível para enviar arquivo multipart.',
            'http_status' => 0,
            'resposta' => '',
        ];
    }

    $curl = curl_init($endpoint);
    if ($curl === false) {
        @unlink($arquivoPdf);
        return [
            'ok' => false,
            'erro' => 'Não foi possível inicializar cURL.',
            'http_status' => 0,
            'resposta' => '',
        ];
    }

    $dadosPost = [
        'titulo' => 'Vistoria de veículo' . $identificacao,
        'descricao' => 'Vistoria feita para a placa ' . (trim($placa) !== '' ? strtoupper(trim($placa)) : 'não informada') . ' assinar.',
        'grupo_id' => (string) $configuracao['grupo_id'],
        'documento' => new CURLFile($arquivoPdf, 'application/pdf', basename($arquivoPdf)),
        'assinatura_funcionario' => '1',
    ];

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_POSTFIELDS => $dadosPost,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-INTERNAL-API-KEY: ' . $apiKey,
        ],
    ]);

    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($curl);
    curl_close($curl);
    @unlink($arquivoPdf);

    $respostaTexto = is_string($resposta) ? trim($resposta) : '';
    $respostaCurta = mb_substr($respostaTexto, 0, 280);

    if ($resposta === false) {
        return [
            'ok' => false,
            'erro' => 'Falha de comunicação com API: ' . ($erroCurl !== '' ? $erroCurl : 'erro desconhecido de cURL'),
            'http_status' => $status,
            'resposta' => $respostaCurta,
        ];
    }

    if ($status < 200 || $status >= 300) {
        error_log(sprintf(
            'Falha ao enviar vistoria %s para assinatura (HTTP %d). Resposta: %s',
            (string) $idVistoria,
            $status,
            $respostaCurta
        ));

        return [
            'ok' => false,
            'erro' => 'API retornou HTTP ' . $status . '.',
            'http_status' => $status,
            'resposta' => $respostaCurta,
        ];
    }

    return [
        'ok' => true,
        'erro' => '',
        'http_status' => $status,
        'resposta' => $respostaCurta,
    ];
}

$id = (int) ($_POST['idinserido'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id < 1 || !($con instanceof mysqli)) {
    http_response_code(400);
    exit('Vistoria inválida.');
}

$stmt = mysqli_prepare($con, "UPDATE `{$databaseName}`.`tbvistoria` SET statusreg = 1 WHERE idtbvistoria = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) < 1) {
    http_response_code(404);
    exit('Não foi possível localizar ou finalizar a vistoria.');
}

$envioAssinatura = ['ok' => false, 'erro' => 'Envio não executado.', 'http_status' => 0, 'resposta' => ''];
$stmtDados = mysqli_prepare($con, "SELECT matricula, matrvistoriador, placa FROM `{$databaseName}`.`tbvistoria` WHERE idtbvistoria = ? LIMIT 1");
if ($stmtDados) {
    mysqli_stmt_bind_param($stmtDados, 'i', $id);
    mysqli_stmt_execute($stmtDados);
    $vistoria = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtDados)) ?: [];
    $stmtLog = mysqli_prepare($con, 'INSERT INTO tblog (data_e_hora, acao, matricula, mat_autor, tipo, placa) VALUES (?, ?, ?, ?, ?, ?)');
    if ($stmtLog) {
        $dataHora = date('Y-m-d H:i:s');
        $acao = 'Concluiu vistoria/checklist';
        $tipoLog = 'checklist';
        $matricula = (string) ($vistoria['matricula'] ?? '');
        $matAutor = (string) ($vistoria['matrvistoriador'] ?? ($autofrota['matricula'] ?? ''));
        $placa = (string) ($vistoria['placa'] ?? '');
        mysqli_stmt_bind_param($stmtLog, 'ssssss', $dataHora, $acao, $matricula, $matAutor, $tipoLog, $placa);
        mysqli_stmt_execute($stmtLog);
    }

    $envioAssinatura = enviarRelatorioParaAssinatura(
        $con,
        $databaseName,
        (string) ($vistoria['matricula'] ?? ''),
        (string) ($vistoria['placa'] ?? ''),
        $id
    );
}

$assinaturaStatus = (($envioAssinatura['ok'] ?? false) === true) ? 'enviada' : 'erro';
$erroAssinatura = trim((string) ($envioAssinatura['erro'] ?? ''));
$httpStatus = (int) ($envioAssinatura['http_status'] ?? 0);
$respostaApi = trim((string) ($envioAssinatura['resposta'] ?? ''));

$params = ['id=' . $id, 'assinatura=' . $assinaturaStatus];
if ($assinaturaStatus === 'erro') {
    if ($erroAssinatura !== '') {
        $params[] = 'erro_detalhe=' . rawurlencode($erroAssinatura);
    }
    if ($httpStatus > 0) {
        $params[] = 'erro_http=' . $httpStatus;
    }
    if ($respostaApi !== '') {
        $params[] = 'erro_api=' . rawurlencode($respostaApi);
    }
}

header('Location: ../checklistp3.php?' . implode('&', $params));
exit;