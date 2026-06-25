<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? ($GLOBALS['conn'] ?? null);
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? ($GLOBALS['databaseName'] ?? 'bdautofrotas'));
$matricula = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');

if (!$conn instanceof mysqli) {
    exit('Conexão indisponível.');
}

function voltarPedidoTecnico(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['tecnico_pedido_mensagem'] = $mensagem;
    $_SESSION['tecnico_pedido_tipo'] = $tipo;
    header('Location: ../tecnico.php');
    exit;
}

function normalizarValorPedidoTecnico(string $valor): float
{
    $valor = trim($valor);
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
    return is_numeric($valor) ? (float) $valor : 0.0;
}

function registrarLogPedidoTecnico(mysqli $conn, string $databaseName, string $matricula, float $valor): void
{
    $sql = "INSERT INTO `{$databaseName}`.`tblog`
        (data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }

    $dataHora = date('Y-m-d H:i:s');
    $acao = 'Pediu cota extra';
    $flagLog = 2;
    $valorAnterior = 0.0;

    mysqli_stmt_bind_param($stmt, 'ssissdd', $dataHora, $acao, $flagLog, $matricula, $matricula, $valorAnterior, $valor);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarPedidoTecnico('Método inválido.');
}

$tokenPost = (string) ($_POST['form_token'] ?? '');
$tokenSessao = (string) ($_SESSION['form_token_tecnico'] ?? '');
if ($tokenPost === '' || $tokenSessao === '' || !hash_equals($tokenSessao, $tokenPost)) {
    voltarPedidoTecnico('Formulário expirado ou já enviado. Atualize a página e tente novamente.');
}
unset($_SESSION['form_token_tecnico']);

$valor = normalizarValorPedidoTecnico((string) ($_POST['valor'] ?? ''));
$justificativa = trim((string) ($_POST['justificativa'] ?? ''));
$kmhodometro = trim((string) ($_POST['kmhodometro'] ?? ''));
$placaPost = strtoupper(trim((string) ($_POST['placa'] ?? '')));

if ($matricula === '') {
    voltarPedidoTecnico('Matrícula não encontrada na sessão.');
}
if ($valor <= 0) {
    voltarPedidoTecnico('Informe um valor solicitado válido.');
}
if ($justificativa === '') {
    voltarPedidoTecnico('Informe a justificativa do pedido.');
}
if ($kmhodometro === '' || !ctype_digit($kmhodometro)) {
    voltarPedidoTecnico('Informe um hodômetro válido.');
}
if ($placaPost === '') {
    voltarPedidoTecnico('Informe a placa do veículo.');
}

$supervisor = buscarUmaLinha(
    $conn,
    "SELECT u2.matricula AS matricula_supervisor, u2.nome AS nome_supervisor
       FROM `{$databaseName}`.`tbusuario` u1
       LEFT JOIN `{$databaseName}`.`tbequipe_supervisor` s ON u1.idtbsupervisor = s.idtbsupervisor
       LEFT JOIN `{$databaseName}`.`tbusuario` u2 ON s.matricula = u2.matricula
      WHERE u1.matricula = ?
      LIMIT 1",
    's',
    [$matricula]
);
$matriculaSupervisor = trim((string) ($supervisor['matricula_supervisor'] ?? ''));
if ($matriculaSupervisor === '') {
    voltarPedidoTecnico('Pedido não concluído: colaborador sem supervisor.');
}

$veiculo = buscarUmaLinha($conn, "SELECT placa FROM `{$databaseName}`.`tbveiculo` WHERE matcond = ? LIMIT 1", 's', [$matricula]);
$placaBanco = strtoupper(trim((string) ($veiculo['placa'] ?? '')));
$placa = $placaPost !== '' ? $placaPost : $placaBanco;
if ($placa === '') {
    voltarPedidoTecnico('Pedido não concluído: colaborador sem veículo vinculado.');
}

$duplicado = buscarUmaLinha(
    $conn,
    "SELECT 1 AS existe FROM `{$databaseName}`.`tbpedidostec`
      WHERE matricula = ? AND DATE(data) = CURDATE() AND flag = 0 AND escalonado = 0 AND desctec IS NULL
      LIMIT 1",
    's',
    [$matricula]
);
if ($duplicado !== []) {
    voltarPedidoTecnico('Já existe pedido em aberto para sua matrícula hoje.');
}

$inicioSemana = date('Y-m-d', strtotime(date('N') === '1' ? 'today' : 'last monday'));
$fimSemana = date('Y-m-d', strtotime($inicioSemana . ' +6 days'));
$saldo = buscarUmaLinha(
    $conn,
    "SELECT
            ROUND(COALESCE(ts.orcsemanal, 0) + COALESCE(cota_semana.total_cotas, 0), 2) AS valoraplicado,
            ROUND(COALESCE(ts.orcsemanal, 0), 2) AS orcsemanal,
            ROUND(COALESCE(ts.kmproj, 0), 2) AS kmproj,
            ROUND(COALESCE(ts.kmosatual, 0), 2) AS kmosatual,
            ROUND(COALESCE(ts.valoraplicado, 0) - COALESCE(ts.orcsemanal, 0), 2) AS totalextra,
            ROUND(COALESCE(ts.slddscnt, 0), 2) AS valordescontado
       FROM (SELECT * FROM `{$databaseName}`.`tbsaldo` WHERE matricula = ? ORDER BY data DESC LIMIT 1) ts
       LEFT JOIN (
            SELECT matricula, SUM(valorinserido) AS total_cotas
              FROM `{$databaseName}`.`tbcotaextraac`
             WHERE matricula = ? AND data >= ? AND data <= ?
             GROUP BY matricula
       ) cota_semana ON ts.matricula = cota_semana.matricula",
    'ssss',
    [$matricula, $matricula, $inicioSemana . ' 00:00:00', $fimSemana . ' 23:59:59']
);
if ($saldo === []) {
    voltarPedidoTecnico('Pedido não concluído: saldo não encontrado para sua matrícula.');
}

if (!isset($_FILES['arquivo']) || !is_uploaded_file($_FILES['arquivo']['tmp_name'])) {
    voltarPedidoTecnico('Envie a foto do hodômetro.');
}
if ((int) ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    voltarPedidoTecnico('Não foi possível receber o arquivo enviado.');
}
$maxBytes = 10 * 1024 * 1024;
if ((int) ($_FILES['arquivo']['size'] ?? 0) > $maxBytes) {
    voltarPedidoTecnico('O arquivo enviado é muito grande. Envie imagens de até 10MB.');
}
$extensao = strtolower(pathinfo((string) ($_FILES['arquivo']['name'] ?? ''), PATHINFO_EXTENSION));
$permitidas = ['jpg', 'jpeg', 'png', 'gif'];
if (!in_array($extensao, $permitidas, true)) {
    voltarPedidoTecnico('Envie uma foto do hodômetro.');
}

$mimeArquivo = '';
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $mimeArquivo = (string) finfo_file($finfo, (string) $_FILES['arquivo']['tmp_name']);
        finfo_close($finfo);
    }
}
if ($mimeArquivo !== '' && strpos($mimeArquivo, 'image/') !== 0) {
    voltarPedidoTecnico('Envie uma foto do hodômetro.');
}

$docsDir = dirname(__DIR__) . '/docs';
if (!is_dir($docsDir) && !mkdir($docsDir, 0775, true)) {
    voltarPedidoTecnico('Não foi possível preparar a pasta de documentos.');
}
$nomeArquivo = preg_replace('/[^0-9A-Za-z_-]/', '', $matricula) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensao;
$caminhoFisico = $docsDir . '/' . $nomeArquivo;
$caminhoBanco = '/docs/' . $nomeArquivo;
if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminhoFisico)) {
    voltarPedidoTecnico('Erro no envio da foto, tente novamente.');
}

$dataPedido = date('Y-m-d H:i:s');
$flag = 0;
$tipocota = 0;
$dataplantao = null;
$valorinserido = 0.0;
$orcsemanal = (float) ($saldo['orcsemanal'] ?? 0);
$kmproj = (float) ($saldo['kmproj'] ?? 0);
$kmos = (float) ($saldo['kmosatual'] ?? 0);
$gps = '';
$valordescontado = (float) ($saldo['valordescontado'] ?? 0);
$sldcartao = 0.0;
$totalextra = (float) ($saldo['totalextra'] ?? 0);
$escalonado = 0;
$desctec = null;
$kmprojatual = $kmproj;
$orcsematual = $orcsemanal;
$justsup = null;
$valsup = null;
$frota = 0;

$sqlInsert = "INSERT INTO `{$databaseName}`.`tbpedidostec`
    (matricula, valor, justificativa, data, flag, tipocota, dataplantao, dir, valorinserido, kmhodometro, orcsemanal, kmproj, kmos, gps, valordescontado, sldcartao, totalextra, escalonado, desctec, kmprojatual, orcsematual, justsup, valsup, placa, frota)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sqlInsert);
if (!$stmt) {
    @unlink($caminhoFisico);
    voltarPedidoTecnico('Erro ao preparar gravação do pedido: ' . mysqli_error($conn));
}
$tipos = 'sdssiissdsdddsdddiiddsdsi';
$parametros = [
    $matricula,
    $valor,
    $justificativa,
    $dataPedido,
    $flag,
    $tipocota,
    $dataplantao,
    $caminhoBanco,
    $valorinserido,
    $kmhodometro,
    $orcsemanal,
    $kmproj,
    $kmos,
    $gps,
    $valordescontado,
    $sldcartao,
    $totalextra,
    $escalonado,
    $desctec,
    $kmprojatual,
    $orcsematual,
    $justsup,
    $valsup,
    $placa,
    $frota,
];
$referencias = [];
foreach ($parametros as $indice => $parametro) {
    $referencias[$indice] = &$parametros[$indice];
}
mysqli_stmt_bind_param($stmt, $tipos, ...$referencias);
if (!mysqli_stmt_execute($stmt)) {
    $erro = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    @unlink($caminhoFisico);
    voltarPedidoTecnico('Erro ao gravar pedido: ' . $erro);
}
mysqli_stmt_close($stmt);

registrarLogPedidoTecnico($conn, $databaseName, $matricula, $valor);

voltarPedidoTecnico('Pedido realizado com sucesso e enviado para análise.', 'success');