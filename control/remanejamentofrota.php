<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();

$conn = $GLOBALS['conn'] ?? null;
$databaseName = $GLOBALS['databaseName'] ?? 'bdautofrotas';
if (!$conn instanceof mysqli) {
    http_response_code(500);
    exit('Conexão indisponível.');
}
mysqli_set_charset($conn, 'utf8mb4');

$matriculaAutor = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$perfilAutor = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');
$matriculaTec = trim((string) ($_POST['matriculatec'] ?? ''));
$placa = strtoupper(trim((string) ($_POST['placa'] ?? '')));
$idtbsaldo = (int) ($_POST['idtbsaldo'] ?? 0);
$tipoAcao = (string) ($_POST['tipoacao'] ?? '');
$justificativa = trim((string) ($_POST['justificativa'] ?? ''));

function normalizarValorMonetario($valorBruto): float
{
    $valor = trim((string) $valorBruto);
    $valor = str_replace(['R$', ' '], '', $valor);

    $temVirgula = strpos($valor, ',') !== false;
    $temPonto = strpos($valor, '.') !== false;

    if ($temVirgula && $temPonto) {
        $ultimaVirgula = strrpos($valor, ',');
        $ultimoPonto = strrpos($valor, '.');
        if ($ultimaVirgula !== false && $ultimoPonto !== false && $ultimaVirgula > $ultimoPonto) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } else {
            $valor = str_replace(',', '', $valor);
        }
    } elseif ($temVirgula) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } else {
        $valor = str_replace(',', '', $valor);
    }

    return is_numeric($valor) ? (float) $valor : 0.0;
}

$saldoAnterior = normalizarValorMonetario($_POST['saldoatual'] ?? '0');
$valor = normalizarValorMonetario($_POST['valor'] ?? '0');

function voltarSaldo(string $mensagem): void
{
    $_SESSION['rel_saldo_msg'] = $mensagem;
    header('Location: ../relatorio-saldo-veiculos.php');
    exit;
}

if ($matriculaAutor === '' || $perfilAutor === '0') {
    voltarSaldo('Usuário sem permissão para remanejar saldo.');
}
if ($matriculaTec === '' || !in_array($tipoAcao, ['0', '1'], true) || $valor <= 0) {
    voltarSaldo('Dados inválidos para remanejamento.');
}
if ($idtbsaldo <= 0) {
    $stmtSaldoRecente = mysqli_prepare($conn, "SELECT idtbsaldo FROM `{$databaseName}`.`tbsaldo` WHERE matricula = ? ORDER BY data DESC, idtbsaldo DESC LIMIT 1");
    if (!$stmtSaldoRecente) {
        voltarSaldo('Erro ao localizar saldo do técnico.');
    }
    mysqli_stmt_bind_param($stmtSaldoRecente, 's', $matriculaTec);
    if (!mysqli_stmt_execute($stmtSaldoRecente)) {
        $erro = mysqli_stmt_error($stmtSaldoRecente);
        mysqli_stmt_close($stmtSaldoRecente);
        voltarSaldo('Erro ao localizar saldo do técnico: ' . $erro);
    }
    $saldoRecente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtSaldoRecente));
    mysqli_stmt_close($stmtSaldoRecente);

    if (!$saldoRecente || (int) ($saldoRecente['idtbsaldo'] ?? 0) <= 0) {
        voltarSaldo('Não há saldo cadastrado para este técnico/veículo.');
    }

    $idtbsaldo = (int) $saldoRecente['idtbsaldo'];
}
if (mb_strlen($justificativa) < 10) {
    voltarSaldo('A justificativa deve ter pelo menos 10 caracteres.');
}

mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "SELECT valoraplicado, kmproj, valrem, totalextra FROM `{$databaseName}`.`tbsaldo` WHERE idtbsaldo = ? FOR UPDATE");
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar leitura do saldo: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'i', $idtbsaldo);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Erro ao consultar saldo: ' . mysqli_stmt_error($stmt));
    }
    $saldo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$saldo) {
        throw new RuntimeException('Saldo não encontrado.');
    }

    $valorAplicado = (float) ($saldo['valoraplicado'] ?? 0);
    $kmProj = (float) ($saldo['kmproj'] ?? 0);
    $valRem = (float) ($saldo['valrem'] ?? 0);
    $totalExtra = (float) ($saldo['totalextra'] ?? 0);
    $multiplicador = $tipoAcao === '1' ? 1 : -1;

    $novoValorAplicado = $valorAplicado + ($multiplicador * $valor);
    $novoTotalExtra = $totalExtra + ($multiplicador * $valor);
    $novoValRem = $valRem + ($multiplicador * $valor);
    if ($novoValorAplicado < 0 || $novoTotalExtra < 0 || $novoValRem < 0) {
        throw new RuntimeException('Saldo insuficiente para remoção.');
    }
    $novoKmProj = $valorAplicado > 0 ? round(($novoValorAplicado * $kmProj) / $valorAplicado, 2) : $kmProj;

    $resumoAntesDepois = "tbsaldo atualizado (ID {$idtbsaldo})\n"
        . "valoraplicado: " . number_format($valorAplicado, 2, ',', '.') . " -> " . number_format($novoValorAplicado, 2, ',', '.') . "\n"
        . "kmproj: " . number_format($kmProj, 2, ',', '.') . " -> " . number_format($novoKmProj, 2, ',', '.') . "\n"
        . "valrem: " . number_format($valRem, 2, ',', '.') . " -> " . number_format($novoValRem, 2, ',', '.') . "\n"
        . "totalextra: " . number_format($totalExtra, 2, ',', '.') . " -> " . number_format($novoTotalExtra, 2, ',', '.');

    $stmt = mysqli_prepare($conn, "UPDATE `{$databaseName}`.`tbsaldo` SET valoraplicado = ?, kmproj = ?, valrem = ?, totalextra = ? WHERE idtbsaldo = ?");
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar atualização do saldo: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'ddddi', $novoValorAplicado, $novoKmProj, $novoValRem, $novoTotalExtra, $idtbsaldo);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Erro ao atualizar saldo: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    $tipo = (int) $tipoAcao;
    $saldoAnteriorFloat = (float) $saldoAnterior;
    $perfilAutorInt = (int) $perfilAutor;
    $stmt = mysqli_prepare($conn, "INSERT INTO `{$databaseName}`.`tbremanejamento` (data_e_hora, matricula, matr_autor, valor, perfil_autor, tipo, valor_anterior) VALUES (NOW(), ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar log de remanejamento: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'ssdiid', $matriculaTec, $matriculaAutor, $valor, $perfilAutorInt, $tipo, $saldoAnteriorFloat);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Erro ao gravar log de remanejamento: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "INSERT INTO `{$databaseName}`.`tbjustificativa` (matricula_autor, data_hora, tipo_acao, justificativa, matricula_tecnico, placa, valor) VALUES (?, NOW(), ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new RuntimeException('Erro ao preparar justificativa: ' . mysqli_error($conn));
    }
    mysqli_stmt_bind_param($stmt, 'sisssd', $matriculaAutor, $tipo, $justificativa, $matriculaTec, $placa, $valor);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Erro ao gravar justificativa: ' . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    $_SESSION['rel_saldo_alert_detalhes'] = $resumoAntesDepois;
    voltarSaldo('Remanejamento feito com sucesso!');
} catch (Throwable $e) {
    mysqli_rollback($conn);
    voltarSaldo('Erro ao remanejar saldo: ' . $e->getMessage());
}
