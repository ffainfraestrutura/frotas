<?php
require_once __DIR__ . '/../../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../../control/conecta.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: text/html; charset=utf-8');

$uploadDir = __DIR__ . '/../docs/condutor/';
$uploadPathPrefix = '/docs/condutor/';

function responderEnvioDocsCondutor(string $mensagem): void
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
            window.location.href = '../listagemcnh.php';
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '../listagemcnh.php';
        }
    </script>";
    exit;
}

function colunaExisteDocsCondutor(mysqli $conn, string $databaseName, string $tabela, string $coluna): bool
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

function registrarLogDocsCondutor(mysqli $conn, string $databaseName, string $matricula, string $matriculaAutor): void
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

function salvarDocumentoCondutor(string $campo, string $matricula, string $sufixo, string $uploadDir, string $uploadPathPrefix): string
{
    if (empty($_FILES[$campo]['name'])) {
        return '';
    }

    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        responderEnvioDocsCondutor('Não foi possível fazer o upload do arquivo de ' . $sufixo . '.');
    }

    $extensoesPermitidas = ['pdf', 'doc', 'docx'];
    $extensao = strtolower((string) pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        responderEnvioDocsCondutor('Por favor, envie arquivos com as seguintes extensões: pdf, doc ou docx.');
    }

    if ((int) $_FILES[$campo]['size'] > 1024 * 1024 * 32) {
        responderEnvioDocsCondutor('O arquivo enviado é muito grande. Envie arquivos de até 32MB.');
    }

    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        responderEnvioDocsCondutor('Não foi possível preparar a pasta de upload.');
    }

    $timestamp = date('YmdHis');
    $matriculaLimpa = preg_replace('/[^0-9A-Za-z_-]/', '', $matricula);
    $nomeFinal = $matriculaLimpa . '-' . $timestamp . '-' . $sufixo . '.' . $extensao;

    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $uploadDir . $nomeFinal)) {
        responderEnvioDocsCondutor('Não foi possível enviar o arquivo de ' . $sufixo . '.');
    }

    return $uploadPathPrefix . $nomeFinal;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderEnvioDocsCondutor('Requisição inválida.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    responderEnvioDocsCondutor('Não foi possível conectar ao banco de dados.');
}

$matricula = trim((string) ($_POST['matcond'] ?? ''));
$matriculaAutor = trim((string) ($_POST['mat_autor'] ?? $_SESSION['matricula'] ?? ''));

if ($matricula === '') {
    responderEnvioDocsCondutor('Matrícula do condutor não informada.');
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
    $caminho = salvarDocumentoCondutor($campo, $matricula, $config['sufixo'], $uploadDir, $uploadPathPrefix);

    if ($caminho === '') {
        continue;
    }

    $coluna = $config['coluna'];
    $sqlUpdate = "UPDATE `{$databaseName}`.`tbcnh` SET `{$coluna}` = ? WHERE matricula = ?";
    $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);

    if (!$stmtUpdate) {
        responderEnvioDocsCondutor('Não foi possível preparar o envio dos documentos.');
    }

    mysqli_stmt_bind_param($stmtUpdate, 'ss', $caminho, $matricula);
    $atualizou = mysqli_stmt_execute($stmtUpdate);
    mysqli_stmt_close($stmtUpdate);

    if (!$atualizou) {
        responderEnvioDocsCondutor('Não foi possível salvar o documento enviado.');
    }

    $enviouAlgum = true;
}

if (!$enviouAlgum) {
    responderEnvioDocsCondutor('Selecione ao menos um documento para envio.');
}

if (colunaExisteDocsCondutor($conn, $databaseName, 'tblog', 'idtblog')) {
    registrarLogDocsCondutor($conn, $databaseName, $matricula, $matriculaAutor);
}

responderEnvioDocsCondutor('Documentos enviados com sucesso.');
