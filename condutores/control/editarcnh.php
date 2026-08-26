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

function responderEdicaoCnh(string $mensagem): void
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

function mensagemErroPastaUploadEdicaoCnh(string $caminho): string
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

function colunaExisteEdicaoCnh(mysqli $conn, string $databaseName, string $tabela, string $coluna): bool
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

function registrarLogEdicaoCnh(mysqli $conn, string $databaseName, string $matricula, string $matriculaAutor): void
{
    $sql = "
        INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, matricula, mat_autor, tipo, placa)
        VALUES (?, 'Editou cnh', ?, ?, 'cadastro', '')
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

function buscarDocumentoAtualCnh(mysqli $conn, string $databaseName, int $id, string $matricula): array
{
    $where = $id > 0 ? 'idtbcnh = ?' : 'matricula = ?';
    $sql = "SELECT doc1, doc2 FROM `{$databaseName}`.`tbcnh` WHERE {$where} LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return ['doc1' => '', 'doc2' => ''];
    }

    if ($id > 0) {
        mysqli_stmt_bind_param($stmt, 'i', $id);
    } else {
        mysqli_stmt_bind_param($stmt, 's', $matricula);
    }

    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $registro = $resultado ? mysqli_fetch_assoc($resultado) : null;

    if ($resultado) {
        mysqli_free_result($resultado);
    }

    mysqli_stmt_close($stmt);

    return $registro ?: ['doc1' => '', 'doc2' => ''];
}

function salvarArquivoEdicaoCnh(string $matricula, string $uploadDir, string $uploadPathPrefix, bool $jaPossuiDoc1): string
{
    if (empty($_FILES['arquivo']['name'])) {
        return '';
    }

    if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        responderEdicaoCnh('Não foi possível fazer o upload do arquivo.');
    }

    $tmpName = (string) ($_FILES['arquivo']['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        responderEdicaoCnh('Arquivo temporário inválido para upload.');
    }

    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $extensao = strtolower((string) pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        responderEdicaoCnh('Por favor, envie arquivos com as seguintes extensões: jpg, jpeg, png, gif ou pdf.');
    }

    if ((int) $_FILES['arquivo']['size'] > 1024 * 1024 * 4) {
        responderEdicaoCnh('O arquivo enviado é muito grande. Envie arquivos de até 4MB.');
    }

    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            responderEdicaoCnh(mensagemErroPastaUploadEdicaoCnh($uploadDir));
        }
    }

    if (!is_writable($uploadDir)) {
        responderEdicaoCnh(mensagemErroPastaUploadEdicaoCnh($uploadDir));
    }

    $sufixo = $jaPossuiDoc1 ? '-2' : '';
    $nomeFinal = 'cnh-' . preg_replace('/[^0-9A-Za-z_-]/', '', $matricula) . $sufixo . '.' . $extensao;
    $destinoFinal = rtrim($uploadDir, '/\\') . DIRECTORY_SEPARATOR . $nomeFinal;

    if (!move_uploaded_file($tmpName, $destinoFinal)) {
        if (!@copy($tmpName, $destinoFinal)) {
            responderEdicaoCnh('Não foi possível enviar o arquivo.');
        }
        @unlink($tmpName);
    }

    return $uploadPathPrefix . $nomeFinal;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderEdicaoCnh('Requisição inválida.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    responderEdicaoCnh('Não foi possível conectar ao banco de dados.');
}

$id = (int) ($_POST['id'] ?? 0);
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
    responderEdicaoCnh('Preencha todos os campos obrigatórios.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $consulta)) {
    responderEdicaoCnh('Informe datas válidas.');
}

if (!in_array($suspensa, ['0', '1'], true)) {
    $suspensa = '0';
}

if ($pontos === '') {
    $pontos = '0';
}

$documentosAtuais = buscarDocumentoAtualCnh($conn, $databaseName, $id, $matricula);
$arquivo = salvarArquivoEdicaoCnh($matricula, $uploadDir, $uploadPathPrefix, !empty($documentosAtuais['doc1']));
$pontosInt = (int) $pontos;
$suspensaInt = (int) $suspensa;

$campos = 'numcnh = ?, validade = ?, uf = ?, categoria = ?, matricula = ?, pontos = ?, consulta = ?, suspensa = ?';
$tipos = 'sssssisi';
$valores = [$cnh, $validade, $uf, $categoria, $matricula, $pontosInt, $consulta, $suspensaInt];

if ($arquivo !== '') {
    if (!empty($documentosAtuais['doc1'])) {
        $campos .= ', doc2 = ?';
    } else {
        $campos .= ', doc1 = ?';
    }
    $tipos .= 's';
    $valores[] = $arquivo;
}

if ($id > 0) {
    $sqlUpdate = "UPDATE `{$databaseName}`.`tbcnh` SET {$campos} WHERE idtbcnh = ?";
    $tipos .= 'i';
    $valores[] = $id;
} else {
    $sqlUpdate = "UPDATE `{$databaseName}`.`tbcnh` SET {$campos} WHERE matricula = ?";
    $tipos .= 's';
    $valores[] = $matricula;
}

$stmtUpdate = mysqli_prepare($conn, $sqlUpdate);
if (!$stmtUpdate) {
    responderEdicaoCnh('Não foi possível preparar a edição da CNH.');
}

mysqli_stmt_bind_param($stmtUpdate, $tipos, ...$valores);
$editou = mysqli_stmt_execute($stmtUpdate);
$linhasAfetadas = mysqli_stmt_affected_rows($stmtUpdate);
mysqli_stmt_close($stmtUpdate);

if (!$editou) {
    responderEdicaoCnh('Não foi possível editar a CNH.');
}

if ($linhasAfetadas < 0) {
    responderEdicaoCnh('Não foi possível localizar a CNH para edição.');
}

if (colunaExisteEdicaoCnh($conn, $databaseName, 'tbfuncionario', 'codempresa')) {
    $sqlFuncionario = "UPDATE `{$databaseName}`.`tbfuncionario` SET nome = ?, codempresa = ? WHERE matricula = ?";
    $stmtFuncionario = mysqli_prepare($conn, $sqlFuncionario);
    if ($stmtFuncionario) {
        mysqli_stmt_bind_param($stmtFuncionario, 'sss', $nome, $codempresa, $matricula);
        mysqli_stmt_execute($stmtFuncionario);
        mysqli_stmt_close($stmtFuncionario);
    }
} else {
    $sqlFuncionario = "UPDATE `{$databaseName}`.`tbfuncionario` SET nome = ? WHERE matricula = ?";
    $stmtFuncionario = mysqli_prepare($conn, $sqlFuncionario);
    if ($stmtFuncionario) {
        mysqli_stmt_bind_param($stmtFuncionario, 'ss', $nome, $matricula);
        mysqli_stmt_execute($stmtFuncionario);
        mysqli_stmt_close($stmtFuncionario);
    }
}

if (colunaExisteEdicaoCnh($conn, $databaseName, 'tblog', 'idtblog')) {
    registrarLogEdicaoCnh($conn, $databaseName, $matricula, $matriculaAutor);
}

responderEdicaoCnh('Cadastro de CNH editado com sucesso.');