<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

setlocale(LC_ALL, 'pt_BR.utf8');
date_default_timezone_set('America/Sao_Paulo');

$hoje = date('Y-m-d H:i:s');

include_once __DIR__ . "/../func/log.php";
include __DIR__ . "/../control/conecta.php";

// Recebe os dados POST
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$placa = isset($_POST['placa']) ? trim($_POST['placa']) : '';
$autoinfra = isset($_POST['autoinfra']) ? trim($_POST['autoinfra']) : '';
$mat_autor = isset($_POST['mat_autor']) ? trim($_POST['mat_autor']) : '';
$is_substituicao = isset($_POST['is_substituicao']) ? true : false; // Flag para saber se é substituição

if (!$id) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    die(json_encode(['success' => false, 'message' => 'ID inválido']));
}

// Verifica arquivo
if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'Erro no upload do arquivo. ';
    switch ($_FILES['arquivo']['error'] ?? 'N/D') {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errorMsg .= 'Arquivo muito grande.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorMsg .= 'Upload incompleto.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorMsg .= 'Nenhum arquivo enviado.';
            break;
        default:
            $errorMsg .= 'Código: ' . ($_FILES['arquivo']['error'] ?? 'N/D');
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    die(json_encode(['success' => false, 'message' => $errorMsg]));
}

// Busca o recibo antigo (se existir)
$sqlOld = "SELECT recibo, reciboass, status FROM tbmovidatramite WHERE idtbmovidatramite = ?";
$stmtOld = mysqli_prepare($conn, $sqlOld);
mysqli_stmt_bind_param($stmtOld, 'i', $id);
mysqli_stmt_execute($stmtOld);
$resOld = mysqli_stmt_get_result($stmtOld);
$oldData = mysqli_fetch_assoc($resOld);
$oldRecibo = $oldData['recibo'] ?? '';
$oldReciboass = $oldData['reciboass'] ?? '';
$oldStatus = $oldData['status'] ?? '';
mysqli_stmt_close($stmtOld);

// Caminho baseado no diretório do script
$pastaUpload = '../docs/multas/';

// Cria a pasta se não existir
if (!is_dir($pastaUpload)) {
    mkdir($pastaUpload, 0777, true);
}

// Valida extensão
$extensao = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
$extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

if (!in_array($extensao, $extensoesPermitidas)) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    die(json_encode(['success' => false, 'message' => 'Extensão não permitida. Use: jpg, jpeg, png, gif ou pdf.']));
}

// Valida tamanho do arquivo (5MB máximo)
$maxFileSize = 32 * 1024 * 1024; // 5MB
if ($_FILES['arquivo']['size'] > $maxFileSize) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    die(json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo 5MB.']));
}

// Busca dados do banco
$sqla = "SELECT matricula, nome, autoinfra FROM tbmovidatramite WHERE idtbmovidatramite = ?";
$stmtA = mysqli_prepare($conn, $sqla);
mysqli_stmt_bind_param($stmtA, 'i', $id);
mysqli_stmt_execute($stmtA);
$resA = mysqli_stmt_get_result($stmtA);
$dados = mysqli_fetch_assoc($resA);
mysqli_stmt_close($stmtA);

if (!$dados) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    die(json_encode(['success' => false, 'message' => 'Registro não encontrado']));
}

$matricula = $dados['matricula'];
$autoinfraDb = $dados['autoinfra'];
$nome = $dados['nome'];

// Gera nome do arquivo
$nomeLimpo = preg_replace('/[^a-zA-Z0-9\-_]/', '', str_replace(' ', '-', $nome));
$nomeFinal = $autoinfraDb . '-' . $nomeLimpo . '-' . $matricula . '.' . $extensao;
$destinoCompleto = $pastaUpload . $nomeFinal;
$caminhoBanco = '/autofrota/docs/multas/' . $nomeFinal;

// Remove arquivo antigo se existir (substituição)
$isSubstituicao = false;
if (!empty($oldRecibo)) {
    $oldFilePath = __DIR__ . $oldRecibo;
    if (file_exists($oldFilePath) && $oldRecibo != $caminhoBanco) {
        unlink($oldFilePath);
        $isSubstituicao = true;
    }
}

// Move o novo arquivo
if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $destinoCompleto)) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    die(json_encode(['success' => false, 'message' => 'Erro ao salvar o arquivo no servidor']));
}

// Busca idmulta para atualizar tbmulta
$sql1 = "SELECT idmulta FROM tbmovidatramite WHERE idtbmovidatramite = ?";
$stmt1 = mysqli_prepare($conn, $sql1);
mysqli_stmt_bind_param($stmt1, 'i', $id);
mysqli_stmt_execute($stmt1);
$res1 = mysqli_stmt_get_result($stmt1);
$row1 = mysqli_fetch_assoc($res1);
$idmulta = $row1['idmulta'] ?? '';
mysqli_stmt_close($stmt1);

// Prepara os campos para atualização
$camposUpdate = "recibo = ?, dt_envio_dp = NOW(), reciboass = 'sim'";

// Se for primeiro upload (não tinha recibo), atualiza tramite e status
if (empty($oldRecibo)) {
    $camposUpdate .= ", tramite = 'Finalizado Frota', tdp = 'Validar Recibo', tfin = 'Fazer Pagamento', status = 2";
} else {
    // Se for substituição, mantém os mesmos valores, só atualiza a data
    $camposUpdate .= ", status = 2";
}

$sqlUpd = "UPDATE tbmovidatramite SET $camposUpdate WHERE idtbmovidatramite = ?";

$stmtUpd = mysqli_prepare($conn, $sqlUpd);
mysqli_stmt_bind_param($stmtUpd, 'si', $caminhoBanco, $id);
$updateSuccess = mysqli_stmt_execute($stmtUpd);
mysqli_stmt_close($stmtUpd);

if (!$updateSuccess) {
    // Se falhou, remove o arquivo que foi enviado
    if (file_exists($destinoCompleto)) {
        unlink($destinoCompleto);
    }
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    die(json_encode(['success' => false, 'message' => 'Erro ao atualizar o banco de dados']));
}

// Atualiza tbmulta apenas se for primeiro envio
if (empty($oldRecibo) && $idmulta) {
    $sqlMulta = "UPDATE tbmulta SET etapa = 'ENVIADO PARA DESCONTO' WHERE idtbmulta = ?";
    $stmtMulta = mysqli_prepare($conn, $sqlMulta);
    mysqli_stmt_bind_param($stmtMulta, 'i', $idmulta);
    mysqli_stmt_execute($stmtMulta);
    mysqli_stmt_close($stmtMulta);
} elseif (empty($oldRecibo)) {
    $sqlMulta = "UPDATE tbmulta SET etapa = 'ENVIADO PARA DESCONTO' WHERE placa = ? AND autoinfracao = ?";
    $stmtMulta = mysqli_prepare($conn, $sqlMulta);
    mysqli_stmt_bind_param($stmtMulta, 'ss', $placa, $autoinfraDb);
    mysqli_stmt_execute($stmtMulta);
    mysqli_stmt_close($stmtMulta);
}

// Log
if ($isSubstituicao) {
    $acao = "SUBSTITUIU recibo da multa {$autoinfraDb} - Veiculo {$placa}";
    $mensagem = "Recibo substituído com sucesso!";
} else {
    $acao = "Adicionou recibo da multa {$autoinfraDb} - Veiculo {$placa}";
    $mensagem = "Recibo enviado com sucesso!";
}

if (function_exists('enviarlognovo')) {
    enviarlognovo($hoje, $acao, $matricula, $mat_autor, 'multa', $placa);
}

// Retorna sucesso
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Processando...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f0f2f5;
        }
    </style>
</head>
<body>
<script>
    Swal.fire({
        title: '<?php echo $isSubstituicao ? 'Recibo Substituído!' : 'Sucesso!'; ?>',
        text: '<?php echo $mensagem; ?>',
        icon: 'success',
        confirmButtonText: 'OK',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            // Volta para a página anterior ou recarrega
            if (document.referrer && document.referrer.includes('historico_completo')) {
                window.location.href = document.referrer;
            } else {
                window.location.href = '../multa/multasfrota.php';
            }
        }
    });
</script>
</body>
</html>
<?php
exit;
?>