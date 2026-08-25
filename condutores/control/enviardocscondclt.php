<?php
require_once __DIR__ . '/../../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../../control/conecta.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: text/html; charset=utf-8');

$baseHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
$baseScheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)) ? 'https' : 'http';
$uploadDir = rtrim((string) (getenv('FROTAS_UPLOAD_DIR') ?: '/tmp/frotas_docs/condutor'), '/\\') . DIRECTORY_SEPARATOR;
$uploadUrl = rtrim((string) (getenv('FROTAS_UPLOAD_URL') ?: $baseScheme . '://' . $baseHost . '/visualizar-upload.php?abrir='), '/');

function responderEnvioDocsCondutorClt(string $mensagem): void
{
    $mensagemJs = json_encode($mensagem, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    $recarregarOrigem = stripos($mensagem, 'sucesso') !== false;
    $recarregarOrigemJs = $recarregarOrigem ? 'true' : 'false';
    echo "<script>
        alert({$mensagemJs});
        if ({$recarregarOrigemJs} && window.opener && !window.opener.closed) {
            window.opener.location.reload();
            window.close();
        } else if ({$recarregarOrigemJs}) {
            window.location.href = '../listar_condutoresclt.php';
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '../listar_condutoresclt.php';
        }
    </script>";
    exit;
}

function mensagemErroPastaUploadCondutorClt(string $caminho): string
{
    if (file_exists($caminho) && !is_dir($caminho)) {
        return "Não foi possível preparar a pasta de upload: o caminho '{$caminho}' já existe e não é uma pasta.";
    }

    if (!is_dir($caminho) && !@mkdir($caminho, 0775, true) && !is_dir($caminho)) {
        $erro = error_get_last()['message'] ?? 'permissão de criação negada ou caminho inválido';
        return "Não foi possível preparar a pasta de upload em '{$caminho}'. Verifique se o diretório existe, se o caminho está correto e se o servidor tem permissão para criar a pasta. Detalhe: {$erro}";
    }

    if (!is_writable($caminho)) {
        return "Não foi possível preparar a pasta de upload: a pasta '{$caminho}' existe, mas não possui permissão de escrita.";
    }

    return '';
}

function colunaExisteDocsCondutorClt(mysqli $conn, string $databaseName, string $tabela, string $coluna): bool
{
    $sql = "SHOW COLUMNS FROM `{$databaseName}`.`{$tabela}` LIKE ?";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $coluna);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $existe = $resultado && mysqli_num_rows($resultado) > 0;

    if ($resultado) {
        mysqli_free_result($resultado);
    }

    mysqli_stmt_close($stmt);

    return $existe;
}

function registrarLogDocsCondutorClt(mysqli $conn, string $databaseName, string $matricula, string $matriculaAutor): void
{
    $sql = "
        INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, matricula, mat_autor, tipo, placa)
        VALUES (?, 'Enviou documentos de condutor', ?, ?, 'cadastro', '')
    ";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return;
    }

    $agora = date('Y-m-d H:i:s');
    mysqli_stmt_bind_param($stmt, 'sss', $agora, $matricula, $matriculaAutor);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function salvarDocumentoCondutorClt(string $campo, string $matricula, string $sufixo, string $uploadDir, string $uploadUrl): string
{
    if (empty($_FILES[$campo]['name'])) {
        return '';
    }

    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        responderEnvioDocsCondutorClt('Não foi possível fazer o upload do arquivo de ' . $sufixo . '.');
    }

    $extensoesPermitidas = ['pdf', 'doc', 'docx'];
    $extensao = strtolower((string) pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        responderEnvioDocsCondutorClt('Por favor, envie arquivos com as seguintes extensões: pdf, doc ou docx.');
    }

    if ((int) $_FILES[$campo]['size'] > 1024 * 1024 * 32) {
        responderEnvioDocsCondutorClt('O arquivo enviado é muito grande. Envie arquivos de até 32MB.');
    }

    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            responderEnvioDocsCondutorClt(mensagemErroPastaUploadCondutorClt($uploadDir));
        }
    }

    if (!is_writable($uploadDir)) {
        responderEnvioDocsCondutorClt(mensagemErroPastaUploadCondutorClt($uploadDir));
    }

    $timestamp = date('YmdHis');
    $matriculaLimpa = preg_replace('/[^0-9A-Za-z_-]/', '', $matricula);
    $nomeFinal = $matriculaLimpa . '-' . $timestamp . '-' . $sufixo . '.' . $extensao;

    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $uploadDir . $nomeFinal)) {
        responderEnvioDocsCondutorClt('Não foi possível enviar o arquivo de ' . $sufixo . '.');
    }

    return $uploadUrl . rawurlencode($nomeFinal);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderEnvioDocsCondutorClt('Requisição inválida.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    responderEnvioDocsCondutorClt('Não foi possível conectar ao banco de dados.');
}

$matricula = trim((string) ($_POST['matcond'] ?? ''));
$matriculaAutor = trim((string) ($_POST['mat_autor'] ?? $_SESSION['matricula'] ?? ''));

if ($matricula === '') {
    responderEnvioDocsCondutorClt('Matrícula do condutor não informada.');
}

$arquivos = [
    'politicauso' => ['coluna' => 'politicauso', 'sufixo' => 'politicauso'],
    'termocombustivel' => ['coluna' => 'termocombust', 'sufixo' => 'termocombustivel'],
    'contrato' => ['coluna' => 'contratoagregamento', 'sufixo' => 'contrato'],
    'rescisao' => ['coluna' => 'termorescisao', 'sufixo' => 'rescisao'],
    'ultrecibo' => ['coluna' => 'ultrecibo', 'sufixo' => 'ultrecibo'],
];

$enviouAlgum = false;

foreach ($arquivos as $campo => $config) {
    $caminho = salvarDocumentoCondutorClt($campo, $matricula, $config['sufixo'], $uploadDir, $uploadUrl);

    if ($caminho === '') {
        continue;
    }

    $coluna = $config['coluna'];
    $sqlUpdate = "UPDATE `{$databaseName}`.`tbcnh` SET `{$coluna}` = ? WHERE matricula = ?";
    $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);

    if (!$stmtUpdate) {
        responderEnvioDocsCondutorClt('Não foi possível preparar o envio dos documentos.');
    }

    mysqli_stmt_bind_param($stmtUpdate, 'ss', $caminho, $matricula);
    $atualizou = mysqli_stmt_execute($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);

    if (!$atualizou) {
        responderEnvioDocsCondutorClt('Não foi possível salvar o documento enviado.');
    }

    $enviouAlgum = true;
}

if (!$enviouAlgum) {
    responderEnvioDocsCondutorClt('Selecione ao menos um documento para envio.');
}

if (colunaExisteDocsCondutorClt($conn, $databaseName, 'tblog', 'idtblog')) {
    registrarLogDocsCondutorClt($conn, $databaseName, $matricula, $matriculaAutor);
}

responderEnvioDocsCondutorClt('Documentos enviados com sucesso.');