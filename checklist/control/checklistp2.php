<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
header('Content-Type: text/html; charset=utf-8');

function urlPublicaRelatorioVistoria(int $id): string
{
    $urlConfigurada = rtrim((string) getenv('AUTOFROTA_PUBLIC_URL'), '/');
    if ($urlConfigurada !== '') {
        return $urlConfigurada . '/checklist/verrelatorio.php?id=' . $id;
    }

    $httpsAtivo = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    $protocolo = $httpsAtivo ? 'https' : 'http';
    $host = preg_replace('/[^a-zA-Z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $diretorioChecklist = dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));

    if ($host === '') {
        return '';
    }

    return $protocolo . '://' . $host . $diretorioChecklist . '/verrelatorio.php?id=' . $id;
}

function gerarTextoRelatorioVistoria(array $vistoria, int $idVistoria): string
{
    $placa = strtoupper(trim((string) ($vistoria['placa'] ?? '')));
    $dataVistoriaBr = date('d/m/Y');
    $dataVistoriaRaw = trim((string) ($vistoria['datavistoria'] ?? ''));
    if ($dataVistoriaRaw !== '') {
        $timestamp = strtotime($dataVistoriaRaw);
        if ($timestamp !== false) {
            $dataVistoriaBr = date('d/m/Y', $timestamp);
        }
    }

    $assinante = trim((string) ($vistoria['nome'] ?? ''));
    if ($assinante === '') {
        $assinante = trim((string) ($vistoria['vistoriador'] ?? ''));
    }
    if ($assinante === '') {
        $assinante = 'Nao informado';
    }

    if ($placa === '') {
        return 'Vistoria feita em ' . $dataVistoriaBr . ' assinado por ' . $assinante;
    }

    return 'Vistoria feita em ' . $dataVistoriaBr . ' da ' . $placa . ' assinado por ' . $assinante;
}

function gerarPdfBasicoPorTexto(string $texto, string $arquivoDestino, int $idVistoria): bool
{
    $normalizar = static function (string $valor): string {
        $valor = preg_replace('/\s+/u', ' ', trim($valor)) ?? '';
        $convertido = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $valor);
        $base = $convertido !== false ? $convertido : $valor;
        $base = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $base) ?? '';
        return $base;
    };

    $escaparPdf = static function (string $valor): string {
        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\\(', '\\)'],
            $valor
        );
    };

    $conteudo = $normalizar($texto);
    if ($conteudo === '') {
        $conteudo = 'Vistoria do dia assinar';
    }

    $linhaUnica = mb_substr($conteudo, 0, 120);

    $comandos = [];
    $comandos[] = 'BT';
    $comandos[] = '/F1 11 Tf';
    $comandos[] = '1 0 0 1 40 780 Tm';
    $comandos[] = '(' . $escaparPdf($linhaUnica) . ') Tj';
    $comandos[] = 'ET';
    $stream = implode("\n", $comandos) . "\n";

    $objetos = [];
    $objetos[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
    $objetos[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
    $objetos[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
    $objetos[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
    $objetos[] = "5 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream\nendobj\n";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objetos as $objeto) {
        $offsets[] = strlen($pdf);
        $pdf .= $objeto;
    }

    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= 5; $i++) {
        $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xrefPos . "\n%%EOF";

    $escrito = @file_put_contents($arquivoDestino, $pdf);
    if ($escrito === false || $escrito < 1) {
        return false;
    }

    $assinatura = @file_get_contents($arquivoDestino, false, null, 0, 5);
    return $assinatura === '%PDF-';
}

function baixarRelatorioTemporario(string $urlRelatorio, int $idVistoria, string $textoFallback = ''): array
{
    if ($urlRelatorio === '' || !filter_var($urlRelatorio, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'URL do relatório inválida.'];
    }

    $tmpBase = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR);
    $tmpDir = $tmpBase . DIRECTORY_SEPARATOR . 'frotas_docs';
    if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não foi possível criar pasta temporária para o PDF.'];
    }

    $arquivoDestino = $tmpDir . DIRECTORY_SEPARATOR . 'vistoria_' . $idVistoria . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $contextoDownload = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);

    $conteudo = @file_get_contents($urlRelatorio, false, $contextoDownload);
    if ($conteudo === false || $conteudo === '') {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não foi possível baixar o PDF do relatório.'];
    }

    $bytesEscritos = @file_put_contents($arquivoDestino, $conteudo);
    if ($bytesEscritos === false || $bytesEscritos < 1) {
        return ['ok' => false, 'arquivo' => null, 'erro' => 'Não foi possível salvar o PDF temporário.'];
    }

    $assinaturaPdf = @file_get_contents($arquivoDestino, false, null, 0, 5);
    if ($assinaturaPdf !== '%PDF-') {
        $textoBruto = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $conteudo)));
        if ($textoFallback !== '') {
            $textoBruto = $textoFallback;
        }
        $gerou = gerarPdfBasicoPorTexto($textoBruto, $arquivoDestino, $idVistoria);
        if (!$gerou) {
            @unlink($arquivoDestino);
            return ['ok' => false, 'arquivo' => null, 'erro' => 'Falha ao converter o relatório HTML para PDF válido.'];
        }
    }

    return ['ok' => true, 'arquivo' => $arquivoDestino, 'erro' => ''];
}

function enviarRelatorioParaAssinatura(string $matricula, string $placa, string $urlRelatorio, int $idVistoria, string $textoFallback = ''): array
{
    $matricula = trim($matricula);
    if ($matricula === '' || $urlRelatorio === '') {
        return [
            'ok' => false,
            'erro' => 'Matrícula do funcionário ou URL do relatório ausente.',
            'http_status' => 0,
            'resposta' => '',
        ];
    }

    $endpoint = 'https://documentos.api.painel-telecom.com/public/api/assinatura-documentos/internal/'
        . rawurlencode($matricula)
        . '/documentos/individual';
    $apiKey = (string) (getenv('ASSINATURA_DOCUMENTOS_INTERNAL_API_KEY') ?: '963eee2b2a97cdd77f1172549d403afee8fc2efc6f7c8d4cd571e9d4afdd1fb7');
    $identificacao = trim($placa) !== '' ? ' - ' . strtoupper(trim($placa)) : '';

    $download = baixarRelatorioTemporario($urlRelatorio, $idVistoria, $textoFallback);
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
        'descricao' => 'Relatório da vistoria de veículo' . $identificacao,
        'grupo_id' => '20',
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
            $urlRelatorio,
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
$stmtDados = mysqli_prepare($con, "SELECT * FROM `{$databaseName}`.`tbvistoria` WHERE idtbvistoria = ? LIMIT 1");
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
        (string) ($vistoria['matricula'] ?? ''),
        (string) ($vistoria['placa'] ?? ''),
        urlPublicaRelatorioVistoria($id),
        $id,
        gerarTextoRelatorioVistoria($vistoria, $id)
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
// 
// 