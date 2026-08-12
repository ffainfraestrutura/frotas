<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');

$id = (int) ($_POST['idinserido'] ?? 0);
$tokenRecebido = (string) ($_POST['csrf_token'] ?? '');
$tokenSessao = (string) ($_SESSION['checklist_csrf_token'] ?? '');

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !($con instanceof mysqli)
    || preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1
    || $id < 1
    || $tokenSessao === ''
    || !hash_equals($tokenSessao, $tokenRecebido)) {
    http_response_code(400);
    exit('Não foi possível excluir o checklist.');
}

$arquivosFotos = [];
mysqli_begin_transaction($con);
try {
    $stmtPendente = mysqli_prepare(
        $con,
        "SELECT idtbvistoria FROM `{$databaseName}`.`tbvistoria`
         WHERE idtbvistoria = ? AND statusreg = 0
         FOR UPDATE"
    );
    if (!$stmtPendente) throw new RuntimeException(mysqli_error($con));
    mysqli_stmt_bind_param($stmtPendente, 'i', $id);
    if (!mysqli_stmt_execute($stmtPendente)) throw new RuntimeException(mysqli_stmt_error($stmtPendente));
    if (!mysqli_fetch_row(mysqli_stmt_get_result($stmtPendente))) {
        throw new DomainException('Checklist pendente não encontrado.');
    }

    $stmtCaminhos = mysqli_prepare($con, "SELECT frontal, traseira, direita, esquerda, bateria, selfie, cnh, extra1, extra2, extra3, extra4, extra5, painel FROM `{$databaseName}`.`tbvistoriafotos` WHERE idtbvistoria = ?");
    if (!$stmtCaminhos) throw new RuntimeException(mysqli_error($con));
    mysqli_stmt_bind_param($stmtCaminhos, 'i', $id);
    if (!mysqli_stmt_execute($stmtCaminhos)) throw new RuntimeException(mysqli_stmt_error($stmtCaminhos));
    $registroFotos = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCaminhos)) ?: [];
    $arquivosFotos = array_filter(array_values($registroFotos), static fn($caminho): bool => is_string($caminho) && $caminho !== '');

    $stmtFotos = mysqli_prepare($con, "DELETE FROM `{$databaseName}`.`tbvistoriafotos` WHERE idtbvistoria = ?");
    if (!$stmtFotos) throw new RuntimeException(mysqli_error($con));
    mysqli_stmt_bind_param($stmtFotos, 'i', $id);
    if (!mysqli_stmt_execute($stmtFotos)) throw new RuntimeException(mysqli_stmt_error($stmtFotos));

    $stmtVistoria = mysqli_prepare($con, "DELETE FROM `{$databaseName}`.`tbvistoria` WHERE idtbvistoria = ? AND statusreg = 0");
    if (!$stmtVistoria) throw new RuntimeException(mysqli_error($con));
    mysqli_stmt_bind_param($stmtVistoria, 'i', $id);
    if (!mysqli_stmt_execute($stmtVistoria) || mysqli_stmt_affected_rows($stmtVistoria) !== 1) {
        throw new RuntimeException('Não foi possível excluir a vistoria pendente.');
    }

    mysqli_commit($con);

    $diretorioFotos = realpath(dirname(__DIR__) . '/docs_old');
    if ($diretorioFotos !== false) {
        foreach ($arquivosFotos as $caminhoFoto) {
            $arquivo = $diretorioFotos . DIRECTORY_SEPARATOR . basename($caminhoFoto);
            if (is_file($arquivo)) {
                @unlink($arquivo);
            }
        }
    }

    unset($_SESSION['checklist_csrf_token']);
    header('Location: ../checklistinicio.php?excluido=1');
    exit;
} catch (Throwable $erro) {
    mysqli_rollback($con);
    error_log('[excluir-checklist] ID ' . $id . ': ' . $erro->getMessage());
    http_response_code($erro instanceof DomainException ? 404 : 500);
    echo 'Não foi possível excluir o checklist pendente.';
}