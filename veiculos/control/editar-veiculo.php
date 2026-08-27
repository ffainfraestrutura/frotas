<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../control/conecta.php';

if (function_exists('exigirLogin')) {
    exigirLogin();
}

date_default_timezone_set('America/Sao_Paulo');

if (!isset($con) && isset($conn)) {
    $con = $conn;
}

if (!isset($databaseName) || $databaseName === '') {
    $databaseName = 'bdautofrotas';
}

function responderEdicaoVeiculo(string $mensagem): void
{
    echo "<script language='javascript' type='text/javascript'>alert('" . addslashes($mensagem) . "');window.close();</script>";
    exit;
}

function campoPostEdicaoVeiculo(string $campo, string $padrao = ''): string
{
    return trim((string) ($_POST[$campo] ?? $padrao));
}

function campoPostAliasEdicaoVeiculo(array $campos, string $padrao = ''): string
{
    foreach ($campos as $campo) {
        if (isset($_POST[$campo])) {
            return trim((string) $_POST[$campo]);
        }
    }

    return $padrao;
}

function normalizarPlacaEdicaoVeiculo(string $placa): string
{
    return strtoupper(str_replace(['-', ' '], '', trim($placa)));
}

function normalizarNumeroBrEdicaoVeiculo(string $valor): string
{
    $valor = trim($valor);
    if ($valor === '') {
        return '';
    }

    $partes = explode(' ', $valor);
    $valor = end($partes) ?: $valor;
    $valor = str_replace('.', '', $valor);
    return str_replace(',', '.', $valor);
}

function montarDataHoraEdicaoVeiculo(string $data, string $hora): ?string
{
    $data = trim($data);
    $hora = trim($hora);

    if ($data === '') {
        return null;
    }

    if ($hora === '') {
        $hora = '00:00:00';
    }

    if (strlen($hora) === 5) {
        $hora .= ':00';
    }

    return $data . ' ' . $hora;
}

function nuloSeVazioEdicaoVeiculo(?string $valor): ?string
{
    $valor = trim((string) $valor);
    return $valor === '' ? null : $valor;
}

function nuloSeVazioNumericoEdicaoVeiculo(string $valor): ?int
{
    $valor = trim($valor);
    return $valor === '' ? null : (int) $valor;
}

function nuloSeVazioDecimalEdicaoVeiculo(string $valor): ?string
{
    $valor = normalizarNumeroBrEdicaoVeiculo($valor);
    return $valor === '' ? null : $valor;
}

function simNaoParaIntEdicaoVeiculo(string $valor): ?int
{
    $valor = strtoupper(trim($valor));
    if ($valor === 'SIM' || $valor === '1') {
        return 1;
    }
    if ($valor === 'NÃO' || $valor === 'NAO' || $valor === '0') {
        return 0;
    }
    return null;
}

function salvarUploadDocumentoEdicaoVeiculo(string $campo, string $placa): string
{
    if (empty($_FILES[$campo]['tmp_name']) || !is_uploaded_file($_FILES[$campo]['tmp_name'])) {
        return '';
    }

    $erroUpload = (int) ($_FILES[$campo]['error'] ?? UPLOAD_ERR_OK);
    if ($erroUpload !== UPLOAD_ERR_OK) {
        responderEdicaoVeiculo('Não foi possível fazer o upload do documento do veículo. Código do erro: ' . $erroUpload);
    }

    $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $nomeOriginal = (string) ($_FILES[$campo]['name'] ?? '');
    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

    if (!in_array($extensao, $extensoesPermitidas, true)) {
        responderEdicaoVeiculo('Por favor, envie arquivos com as extensões JPG, JPEG, PNG, GIF ou PDF.');
    }

    $tamanhoMaximo = 1024 * 1024 * 32;
    if ((int) ($_FILES[$campo]['size'] ?? 0) > $tamanhoMaximo) {
        responderEdicaoVeiculo('O arquivo enviado é muito grande. Envie arquivos de até 32Mb.');
    }

    $pasta = __DIR__ . '/../docs/';
    if (!is_dir($pasta) && !mkdir($pasta, 0775, true)) {
        responderEdicaoVeiculo('Não foi possível preparar a pasta de documentos do veículo.');
    }

    $nomeFinal = $placa . 'docbo.' . $extensao;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $pasta . $nomeFinal)) {
        responderEdicaoVeiculo('Erro no envio do documento do veículo.');
    }

    return '/docs/' . $nomeFinal;
}

function inserirLogEdicaoVeiculo(mysqli $con, string $databaseName, string $dataHora, string $matriculaAutor, string $placa): void
{
    $schema = str_replace('`', '``', $databaseName);
    $sql = "INSERT INTO `{$schema}`.`tblog` (data_e_hora, acao, matricula, mat_autor, tipo, placa) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        return;
    }

    $acao = 'Editou veiculo';
    $matricula = '';
    $tipo = 'cadastro';
    mysqli_stmt_bind_param($stmt, 'ssssss', $dataHora, $acao, $matricula, $matriculaAutor, $tipo, $placa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderEdicaoVeiculo('Requisição inválida para edição de veículo.');
}

if (!isset($con) || !($con instanceof mysqli)) {
    responderEdicaoVeiculo('Não foi possível conectar ao banco de dados.');
}

$idtbveiculo = campoPostEdicaoVeiculo('idtbveiculo');
$placa = normalizarPlacaEdicaoVeiculo(campoPostEdicaoVeiculo('placa'));

if ($idtbveiculo === '') {
    responderEdicaoVeiculo('Identificador do veículo não informado.');
}

if ($placa === '') {
    responderEdicaoVeiculo('Informe a placa do veículo.');
}

$hoje = date('Y-m-d H:i:s');
$dados = [
    'placa' => $placa,
    'uf' => campoPostEdicaoVeiculo('uf'),
    'aplicacao' => campoPostEdicaoVeiculo('aplicacaofrota'),
    'marca' => campoPostEdicaoVeiculo('marca'),
    'modelo' => campoPostEdicaoVeiculo('modelo'),
    'versao' => campoPostEdicaoVeiculo('versao'),
    'categoria' => campoPostEdicaoVeiculo('categoria'),
    'tipo' => nuloSeVazioNumericoEdicaoVeiculo(campoPostAliasEdicaoVeiculo(['tipoveic', 'classificacao'])),
    'cor' => campoPostEdicaoVeiculo('cor'),
    'zerokm' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('zerokm')),
    'anofabr' => nuloSeVazioNumericoEdicaoVeiculo(campoPostAliasEdicaoVeiculo(['anofabr', 'anofabric'])),
    'anomodelo' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('anomodelo')),
    'velocmax' => nuloSeVazioNumericoEdicaoVeiculo(campoPostAliasEdicaoVeiculo(['velocmax', 'velmax'])),
    'renavam' => campoPostEdicaoVeiculo('renavam'),
    'chassi' => strtoupper(campoPostEdicaoVeiculo('chassi')),
    'nummotor' => campoPostEdicaoVeiculo('nummotor'),
    'combustivel' => campoPostEdicaoVeiculo('combustivel'),
    'tanque' => nuloSeVazioDecimalEdicaoVeiculo(campoPostEdicaoVeiculo('tanque')),
    'motorizacao' => campoPostEdicaoVeiculo('motorizacao'),
    'nportas' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('nportas')),
    'npassageiros' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('npassageiros')),
    'calibragem' => nuloSeVazioDecimalEdicaoVeiculo(campoPostEdicaoVeiculo('calibragem')),
    'aro' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('aro')),
    'qtdpneus' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('qtdpneus')),
    'qtdestepe' => nuloSeVazioNumericoEdicaoVeiculo(campoPostAliasEdicaoVeiculo(['qtdestepe', 'qtdestepes'])),
    'qtdeixos' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('qtdeixos')),
    'gnv' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('gnv')),
    'gps' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('gps')),
    'tagpedagio' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('tagpedagio')),
    'status' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('status', '1')),
    'hodometro' => nuloSeVazioNumericoEdicaoVeiculo(str_replace('.', '', campoPostEdicaoVeiculo('hodometro'))),
    'tipoposse' => campoPostEdicaoVeiculo('tipoposse'),
    'idlocador' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('locador')),
    'hodometroinicial' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('hodometroinicial')),
    'statusvel' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('statusvel', '1')),
    'obsveiculo' => campoPostEdicaoVeiculo('obsveiculo'),
    'datamovimentacao' => montarDataHoraEdicaoVeiculo(campoPostEdicaoVeiculo('datamovimentacao'), campoPostEdicaoVeiculo('horamovimentacao')),
    'matcond' => campoPostEdicaoVeiculo('matcond'),
    'oficina' => campoPostEdicaoVeiculo('oficina'),
    'dtentrega' => nuloSeVazioEdicaoVeiculo(campoPostEdicaoVeiculo('dtentrega')),
    'dtdevolucao' => nuloSeVazioEdicaoVeiculo(campoPostEdicaoVeiculo('dtdevolucao')),
    'tipovel' => nuloSeVazioNumericoEdicaoVeiculo(campoPostEdicaoVeiculo('tipovel')),
    'situacao' => campoPostEdicaoVeiculo('situacao'),
    'doccrlv' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('doccrlv')),
    'airbag' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('airbag')),
    'gpsemp' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('gpsemp')),
    'rack' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('rack')),
    'ncontloc' => campoPostEdicaoVeiculo('ncontloc'),
    'dtdisponivelloc' => nuloSeVazioEdicaoVeiculo(campoPostEdicaoVeiculo('dtdisponivelloc')),
    'dtdevolucaoloc' => nuloSeVazioEdicaoVeiculo(campoPostEdicaoVeiculo('dtdevolucaoloc')),
    'valaquisicao' => nuloSeVazioDecimalEdicaoVeiculo(campoPostEdicaoVeiculo('valaquisicao')),
    'blindagem' => simNaoParaIntEdicaoVeiculo(campoPostEdicaoVeiculo('blindagem')),
    'basegestao' => campoPostEdicaoVeiculo('basegestao'),
    'ccusto' => campoPostEdicaoVeiculo('centrocusto'),
    'dttermodisp' => nuloSeVazioEdicaoVeiculo(campoPostEdicaoVeiculo('dttermo')),
    'dtdesmobilizacao' => nuloSeVazioEdicaoVeiculo(campoPostEdicaoVeiculo('dtdisp')),
    'unidade' => campoPostEdicaoVeiculo('unidade'),
];

$caminhoDocumento = salvarUploadDocumentoEdicaoVeiculo('doc01', $placa);
if ($caminhoDocumento !== '') {
    $dados['bo'] = $caminhoDocumento;
}

$schema = str_replace('`', '``', $databaseName);
$atribuicoes = [];
foreach (array_keys($dados) as $coluna) {
    $colunaSegura = str_replace('`', '``', $coluna);
    $atribuicoes[] = "`{$colunaSegura}` = ?";
}

$sql = "UPDATE `{$schema}`.`tbveiculo` SET " . implode(', ', $atribuicoes) . ' WHERE `idtbveiculo` = ?';
$stmt = mysqli_prepare($con, $sql);
if (!$stmt) {
    responderEdicaoVeiculo('Erro ao preparar edição do veículo: ' . mysqli_error($con));
}

$tipos = str_repeat('s', count($dados) + 1);
$valores = array_values($dados);
$valores[] = $idtbveiculo;
$parametrosBind = [$stmt, $tipos];
foreach ($valores as $indice => &$valor) {
    $parametrosBind[] = &$valor;
}
unset($valor);
call_user_func_array('mysqli_stmt_bind_param', $parametrosBind);

if (!mysqli_stmt_execute($stmt)) {
    $erro = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    responderEdicaoVeiculo('Erro ao editar veículo: ' . $erro);
}
mysqli_stmt_close($stmt);

if ($dados['status'] === '0') {
    $sqlCondutor = "UPDATE `{$schema}`.`tbcondutor` SET datadissoc = ?, ativo = '0', statuscond = '' WHERE placaassoc = ? AND ativo = '1'";
    $stmtCondutor = mysqli_prepare($con, $sqlCondutor);
    if ($stmtCondutor) {
        mysqli_stmt_bind_param($stmtCondutor, 'ss', $hoje, $placa);
        if (!mysqli_stmt_execute($stmtCondutor)) {
            $erro = mysqli_stmt_error($stmtCondutor);
            mysqli_stmt_close($stmtCondutor);
            responderEdicaoVeiculo('Veículo editado, mas houve erro ao desativar condutor: ' . $erro);
        }
        mysqli_stmt_close($stmtCondutor);
    }
}

inserirLogEdicaoVeiculo($con, $databaseName, $hoje, campoPostEdicaoVeiculo('matr_autor'), $placa);
mysqli_close($con);

responderEdicaoVeiculo('Cadastro de veículo editado com sucesso.');
?>