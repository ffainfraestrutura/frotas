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
$uploadDir = rtrim((string) (getenv('FROTAS_UPLOAD_DIR') ?: '/tmp/frotas_docs/cnhs'), '/\\') . DIRECTORY_SEPARATOR;
$uploadUrl = rtrim((string) (getenv('FROTAS_UPLOAD_URL') ?: $baseScheme . '://' . $baseHost . '/visualizar-upload.php?abrir='), '/');

function responderCadastroCnh(string $mensagem): void
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

function mensagemErroPastaUploadCnh(string $caminho): string
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

function colunaExisteCadastroCnh(mysqli $conn, string $databaseName, string $tabela, string $coluna): bool
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

function cadastrarLogCnh(mysqli $conn, string $databaseName, string $matricula, string $matriculaAutor): void
{
    $sql = "
        INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, matricula, mat_autor, tipo, placa)
        VALUES (?, 'Cadastrou cnh', ?, ?, 'cadastro', '')
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

function salvarArquivoCnh(string $matricula, string $uploadDir, string $uploadUrl): string
{
    if (empty($_FILES['arquivo']['name'])) {
        return '';
    }

    if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        responderCadastroCnh('Não foi possível fazer o upload do arquivo.');
    }

    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $extensao = strtolower((string) pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        responderCadastroCnh('Por favor, envie arquivos com as seguintes extensões: jpg, jpeg, png, gif ou pdf.');
    }

    if ((int) $_FILES['arquivo']['size'] > 1024 * 1024 * 2) {
        responderCadastroCnh('O arquivo enviado é muito grande. Envie arquivos de até 2MB.');
    }

    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            responderCadastroCnh(mensagemErroPastaUploadCnh($uploadDir));
        }
    }

    if (!is_writable($uploadDir)) {
        responderCadastroCnh(mensagemErroPastaUploadCnh($uploadDir));
    }

    $nomeFinal = 'cnh-' . preg_replace('/[^0-9A-Za-z_-]/', '', $matricula) . '.' . $extensao;

    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $uploadDir . $nomeFinal)) {
        responderCadastroCnh('Não foi possível enviar o arquivo.');
    }

    return $uploadUrl . rawurlencode($nomeFinal);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderCadastroCnh('Requisição inválida.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    responderCadastroCnh('Não foi possível conectar ao banco de dados.');
}

$nome = trim((string) ($_POST['nome'] ?? ''));
$matricula = trim((string) ($_POST['matricula'] ?? ''));
$cnh = preg_replace('/\D/', '', (string) ($_POST['cnh'] ?? ''));
$validade = trim((string) ($_POST['validade'] ?? ''));
$uf = strtoupper(trim((string) ($_POST['uf'] ?? '')));
$categoria = strtoupper(trim((string) ($_POST['categoria'] ?? '')));
$pontos = preg_replace('/\D/', '', (string) ($_POST['pontos'] ?? ''));
$consulta = trim((string) ($_POST['consulta'] ?? ''));
$suspensa = (string) ($_POST['suspensa'] ?? '0');
$codempresa = (string) ($_POST['codempresa'] ?? '1');
$matriculaAutor = trim((string) ($_POST['matr_autor'] ?? $_SESSION['matricula'] ?? ''));

if ($nome === '' || $matricula === '' || $cnh === '' || $validade === '' || $uf === '' || $categoria === '' || $consulta === '') {
    responderCadastroCnh('Preencha todos os campos obrigatórios.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $consulta)) {
    responderCadastroCnh('Informe datas válidas.');
}

if (!in_array($suspensa, ['0', '1'], true)) {
    $suspensa = '0';
}

if ($pontos === '') {
    $pontos = '0';
}

$pontosInt = (int) $pontos;
$suspensaInt = (int) $suspensa;

$sqlExiste = "SELECT 1 FROM `{$databaseName}`.`tbcnh` WHERE matricula = ? LIMIT 1";
$stmtExiste = mysqli_prepare($conn, $sqlExiste);
if (!$stmtExiste) {
    responderCadastroCnh('Não foi possível verificar a CNH existente.');
}

mysqli_stmt_bind_param($stmtExiste, 's', $matricula);
mysqli_stmt_execute($stmtExiste);
$resultadoExiste = mysqli_stmt_get_result($stmtExiste);
$jaExiste = $resultadoExiste && mysqli_num_rows($resultadoExiste) > 0;
if ($resultadoExiste) {
    mysqli_free_result($resultadoExiste);
}
mysqli_stmt_close($stmtExiste);

if ($jaExiste) {
    responderCadastroCnh('CNH já cadastrada.');
}

$arquivo = salvarArquivoCnh($matricula, $uploadDir, $uploadUrl);

$doc2 = '';
$politicauso = '';
$termocombust = '';
$contratoagregamento = '';
$termorescisao = '';
$ultrecibo = '';

$sqlInsert = "
    INSERT INTO `{$databaseName}`.`tbcnh` (
        numcnh,
        validade,
        uf,
        categoria,
        matricula,
        pontos,
        consulta,
        suspensa,
        doc1,
        doc2,
        politicauso,
        termocombust,
        contratoagregamento,
        termorescisao,
        ultrecibo
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";
$stmtInsert = mysqli_prepare($conn, $sqlInsert);
if (!$stmtInsert) {
    responderCadastroCnh('Não foi possível preparar o cadastro da CNH.');
}

mysqli_stmt_bind_param(
    $stmtInsert,
    'sssssisisssssss',
    $cnh,
    $validade,
    $uf,
    $categoria,
    $matricula,
    $pontosInt,
    $consulta,
    $suspensaInt,
    $arquivo,
    $doc2,
    $politicauso,
    $termocombust,
    $contratoagregamento,
    $termorescisao,
    $ultrecibo
);
$inseriu = mysqli_stmt_execute($stmtInsert);

if (!$inseriu) {
    $erroStmt = mysqli_stmt_error($stmtInsert);
    $errnoStmt = mysqli_stmt_errno($stmtInsert);
    $erroConn = mysqli_error($conn);
    $errnoConn = mysqli_errno($conn);
    $debug = '\nSQL: ' . $sqlInsert .
        '\nValores: [' .
        'numcnh=' . $cnh . ', ' .
        'validade=' . $validade . ', ' .
        'uf=' . $uf . ', ' .
        'categoria=' . $categoria . ', ' .
        'matricula=' . $matricula . ', ' .
        'pontos=' . $pontos . ', ' .
        'consulta=' . $consulta . ', ' .
        'suspensa=' . $suspensa . ', ' .
        'doc1=' . $arquivo . ', ' .
        'doc2=' . $doc2 . ', ' .
        'politicauso=' . $politicauso . ', ' .
        'termocombust=' . $termocombust . ', ' .
        'contratoagregamento=' . $contratoagregamento . ', ' .
        'termorescisao=' . $termorescisao . ', ' .
        'ultrecibo=' . $ultrecibo .
        ']';
    mysqli_stmt_close($stmtInsert);
    responderCadastroCnh(
        'Não foi possível cadastrar a CNH.' .
        ' Erro statement (' . $errnoStmt . '): ' . $erroStmt .
        ' | Erro conexão (' . $errnoConn . '): ' . $erroConn .
        $debug
    );
}

mysqli_stmt_close($stmtInsert);

if (colunaExisteCadastroCnh($conn, $databaseName, 'tbfuncionario', 'codempresa')) {
    $sqlUpdate = "UPDATE `{$databaseName}`.`tbfuncionario` SET codempresa = ? WHERE matricula = ?";
    $stmtUpdate = mysqli_prepare($conn, $sqlUpdate);
    if ($stmtUpdate) {
        mysqli_stmt_bind_param($stmtUpdate, 'ss', $codempresa, $matricula);
        mysqli_stmt_execute($stmtUpdate);
        mysqli_stmt_close($stmtUpdate);
    }
}

if (colunaExisteCadastroCnh($conn, $databaseName, 'tblog', 'idtblog')) {
    cadastrarLogCnh($conn, $databaseName, $matricula, $matriculaAutor);
}

responderCadastroCnh('CNH cadastrada com sucesso.');