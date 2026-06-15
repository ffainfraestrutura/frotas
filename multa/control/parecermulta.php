<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';


$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

if (!$conn instanceof mysqli || $databaseName === '') {
    header('Location: ../multasfrota.php');
    exit;
}

$usuario = (string) ($_SESSION['usuario'] ?? '');
$idMov = trim((string) ($_POST['idtbmovidatramite'] ?? ''));
$etapa = trim((string) ($_POST['etapa1'] ?? ''));
$parecer = trim((string) ($_POST['parecer'] ?? ''));

if ($idMov === '' || $parecer === '') {
    header('Location: ../multasfrota.php');
    exit;
}

$mapaEtapa = [
    'RECIBO ENVIADO PARA O DP' => ['Finalizado Frota', 'Validar Recibo', '', '', 2],
    'AGUARDANDO ASSINATURA CONDUTOR' => ['Imprimir Recibo DP', '', '', '', 1],
    'ABERTO' => ['Inserir infrator', '', '', '', 1],
    'COLABORADOR DEMITIDO' => ['Finalizado Frota', '', '', '', 0],
    'AGUARDANDO DESCONTO' => ['Finalizado Frota', 'Confirmar Desconto', 'Fazer Pagamento', '', 2],
    'CONCLUÍDO' => ['Finalizado Frota', 'DP Finalizado', 'Finalizado Financeiro', '', 5],
    'EM ANÁLISE' => ['Finalizado Frota', 'Validar recibo', '', '', 2],
    'MULTA INDEVIDA' => ['Finalizado Frota', 'DP Finalizado', 'Finalizado Financeiro', '', 5],
    'RECUSA' => ['', '', '', '', 0],
    'COLABORADOR NÃO COMPARECEU' => ['', '', '', '', 0],
    'MULTA ASSINADA' => ['Finalizado Frota', 'DP Finalizado', 'Finalizado Financeiro', '', 5],
    'FINALIZADO FINANCEIRO' => ['Finalizado Frota', 'DP Finalizado', 'Finalizado Financeiro', '', 5],
];

mysqli_set_charset($conn, 'utf8mb4');

$stmt = mysqli_prepare($conn, "SELECT idmulta, placa, autoinfra, parecer FROM `{$databaseName}`.tbmovidatramite WHERE idtbmovidatramite = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $idMov);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);

if (!$row) {
    header('Location: ../multasfrota.php');
    exit;
}

$idtbmulta = (string) ($row['idmulta'] ?? '');
$placa = (string) ($row['placa'] ?? '');
$autoinfracao = (string) ($row['autoinfra'] ?? '');
$parecerAtual = (string) ($row['parecer'] ?? '');

if ($etapa !== '') {
    [$tramite, $tdp, $tfin, $tsup, $status] = $mapaEtapa[$etapa] ?? ['Inserir infrator', '', '', '', 1];

    if ($idtbmulta !== '') {
        $stmt = mysqli_prepare($conn, "UPDATE `{$databaseName}`.tbmulta SET etapa = ? WHERE idtbmulta = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $etapa, $idtbmulta);
    } else {
        $stmt = mysqli_prepare($conn, "UPDATE `{$databaseName}`.tbmulta SET etapa = ? WHERE placa = ? AND autoinfracao = ?");
        mysqli_stmt_bind_param($stmt, 'sss', $etapa, $placa, $autoinfracao);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "UPDATE `{$databaseName}`.tbmovidatramite SET tramite = ?, tdp = ?, tfin = ?, tsup = ?, status = ? WHERE idtbmovidatramite = ?");
    mysqli_stmt_bind_param($stmt, 'ssssis', $tramite, $tdp, $tfin, $tsup, $status, $idMov);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($parecerAtual === '') {
    $stmt = mysqli_prepare($conn, "UPDATE `{$databaseName}`.tbmovidatramite SET parecer = ?, parecerpor = ? WHERE idtbmovidatramite = ?");
} else {
    $stmt = mysqli_prepare($conn, "UPDATE `{$databaseName}`.tbmovidatramite SET parecerdp = ?, parecerpordp = ? WHERE idtbmovidatramite = ?");
}
mysqli_stmt_bind_param($stmt, 'sss', $parecer, $usuario, $idMov);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: ../multasfrota.php');
exit;
