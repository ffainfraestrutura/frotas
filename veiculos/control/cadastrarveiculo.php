<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../control/conecta.php';
require_once __DIR__ . '/documentos-veiculo.php';
exigirLogin();

date_default_timezone_set('America/Sao_Paulo');

if (!isset($con) && isset($conn)) {
    $con = $conn;
}

if (!isset($databaseName) || $databaseName === '') {
    $databaseName = 'bdautofrotas';
}

function responderCadastroVeiculo(string $mensagem): void
{
    echo "<script language='javascript' type='text/javascript'>alert('" . addslashes($mensagem) . "');window.close();</script>";
    exit;
}

function redirecionarListagemVeiculo(string $mensagem = ''): void
{
    $destino = '../listagem-veiculo.php';
    if ($mensagem !== '') {
        $destino .= '?msg=' . urlencode($mensagem);
    }

    echo '<script language="javascript" type="text/javascript">window.location.href = ' . json_encode($destino, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
    exit;
}

function campoPost(string $campo, string $padrao = ''): string
{
    return trim((string) ($_POST[$campo] ?? $padrao));
}

function normalizarPlaca(string $placa): string
{
    return strtoupper(str_replace(['-', ' '], '', trim($placa)));
}

function normalizarNumeroBr(string $valor): string
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

function montarDataHora(string $data, string $hora): string
{
    $data = trim($data);
    $hora = trim($hora);

    if ($data === '') {
        return '';
    }

    if ($hora === '') {
        $hora = '00:00:00';
    }

    if (strlen($hora) === 5) {
        $hora .= ':00';
    }

    return $data . ' ' . $hora;
}

function nuloSeVazio(?string $valor): ?string
{
    $valor = trim((string) $valor);
    return $valor === '' ? null : $valor;
}

function nuloSeVazioNumerico(string $valor): ?int
{
    $valor = trim($valor);
    if ($valor === '') {
        return null;
    }

    return (int) $valor;
}

function simNaoParaInt(string $valor): ?int
{
    $valor = strtoupper(trim($valor));
    if ($valor === 'SIM') {
        return 1;
    }
    if ($valor === 'NÃO' || $valor === 'NAO' || $valor === 'NãO' || $valor === 'NÃo') {
        return 0;
    }
    if ($valor === '1') {
        return 1;
    }
    if ($valor === '0') {
        return 0;
    }
    return null;
}

function inserirLogCadastroVeiculo(mysqli $con, string $dataHora, string $matriculaAutor, string $placa): void
{
    $sql = 'INSERT INTO tblog (data_e_hora, acao, matricula, mat_autor, tipo, placa) VALUES (?, ?, ?, ?, ?, ?)';
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) {
        return;
    }

    $acao = 'Cadastrou veiculo';
    $matricula = '';
    $tipo = 'cadastro';
    mysqli_stmt_bind_param($stmt, 'ssssss', $dataHora, $acao, $matricula, $matriculaAutor, $tipo, $placa);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderCadastroVeiculo('Requisição inválida para cadastro de veículo.');
}

if (!isset($con) || !($con instanceof mysqli)) {
    responderCadastroVeiculo('Não foi possível conectar ao banco de dados.');
}

$hoje = date('Y-m-d H:i:s');
$placa = normalizarPlaca(campoPost('placa'));

if ($placa === '') {
    responderCadastroVeiculo('Informe a placa do veículo.');
}

$camposObrigatorios = [
    'status' => 'Informe o status.',
    'uf' => 'Informe a UF do veículo.',
    'aplicacaofrota' => 'Informe a aplicação da frota.',
    'marca' => 'Informe a marca do veículo.',
    'modelo' => 'Informe o modelo do veículo.',
    'categoria' => 'Informe a categoria do veículo.',
    'cor' => 'Informe a cor do veículo.',
    'zerokm' => 'Informe se o veículo é 0km.',
    'anofabric' => 'Informe o ano de fabricação do veículo.',
    'statusvel' => 'Informe o status do veículo.',
    'tipovel' => 'Informe o tipo operacional do veículo.',
    'situacao' => 'Informe a situação do veículo.',
    'combustivel' => 'Informe o combustível do veículo.',
    'gnv' => 'Informe se o veículo é GNV.',
    'gps' => 'Informe se o veículo possui GPS.',
    'gpsemp' => 'Informe a empresa de GPS.',
    'tipoposse' => 'Informe o tipo de posse do veículo.',
];

foreach ($camposObrigatorios as $campo => $mensagem) {
    if (campoPost($campo) === '') {
        responderCadastroVeiculo($mensagem);
    }
}

$dados = [
    'placa' => $placa,
    'uf' => campoPost('uf'),
    'aplicacao' => campoPost('aplicacaofrota'),
    'marca' => campoPost('marca'),
    'modelo' => campoPost('modelo'),
    'versao' => campoPost('versao'),
    'categoria' => nuloSeVazio(campoPost('categoria')),
    'tipo' => nuloSeVazio(campoPost('tipoveic', campoPost('classificacao'))),
    'cor' => campoPost('cor'),
    'zerokm' => simNaoParaInt(campoPost('zerokm')),
    'anofabr' => campoPost('anofabric'),
    'anomodelo' => nuloSeVazioNumerico(campoPost('anomodelo')),
    'velocmax' => nuloSeVazioNumerico(campoPost('velmax')),
    'renavam' => campoPost('renavam'),
    'chassi' => strtoupper(campoPost('chassi')),
    'nummotor' => campoPost('nummotor'),
    'combustivel' => campoPost('combustivel'),
    'tanque' => nuloSeVazio(campoPost('tanque')),
    'motorizacao' => campoPost('motorizacao'),
    'nportas' => nuloSeVazioNumerico(campoPost('nportas')),
    'npassageiros' => nuloSeVazioNumerico(campoPost('npassageiros')),
    'calibragem' => nuloSeVazio(campoPost('calibragem')),
    'aro' => nuloSeVazioNumerico(campoPost('aro')),
    'qtdpneus' => nuloSeVazioNumerico(campoPost('qtdpneus')),
    'qtdestepe' => nuloSeVazioNumerico(campoPost('qtdestepes')),
    'qtdeixos' => nuloSeVazioNumerico(campoPost('qtdeixos')),
    'gnv' => simNaoParaInt(campoPost('gnv')),
    'gps' => simNaoParaInt(campoPost('gps')),
    'tagpedagio' => simNaoParaInt(campoPost('tagpedagio')),
    'status' => campoPost('status', '1'),
    'hodometro' => nuloSeVazioNumerico(str_replace('.', '', campoPost('hodometro'))),
    'tipoposse' => campoPost('tipoposse'),
    'idlocador' => nuloSeVazioNumerico(campoPost('locador')),
    'statusvel' => campoPost('statusvel'),
    'tipovel' => campoPost('tipovel'),
    'situacao' => campoPost('situacao'),
    'hodometroinicial' => nuloSeVazioNumerico(campoPost('hodometroinicial')),
    'datamovimentacao' => nuloSeVazio(montarDataHora(campoPost('datamovimentacao'), campoPost('horamovimentacao'))),
    'matcond' => campoPost('matcond'),
    'dtentrega' => nuloSeVazio(campoPost('dtentrega')),
    'dtdevolucao' => nuloSeVazio(campoPost('dtdevolucao')),
    'gpsemp' => nuloSeVazioNumerico(campoPost('gpsemp')),
    'doccrlv' => simNaoParaInt(campoPost('doccrlv')),
    'airbag' => simNaoParaInt(campoPost('airbag')),
    'rack' => simNaoParaInt(campoPost('rack')),
    'blindagem' => simNaoParaInt(campoPost('blindagem')),
    'oficina' => campoPost('oficina'),
    'ncontloc' => campoPost('ncontloc'),
    'dtdisponivelloc' => nuloSeVazio(campoPost('dtdisponivelloc')),
    'dtdevolucaoloc' => nuloSeVazio(campoPost('dtdevolucaoloc')),
    'valaquisicao' => nuloSeVazio(normalizarNumeroBr(campoPost('valaquisicao'))),
    'obsveiculo' => campoPost('obsveiculo'),
    'baseffa' => campoPost('baseffa'),
    'unidade' => campoPost('unidade'),
    'basegestao' => campoPost('basegestao'),
    'ccusto' => campoPost('centrocusto'),
    'dttermodisp' => nuloSeVazio(campoPost('dttermo')),
    'dtdesmobilizacao' => nuloSeVazio(campoPost('dtdisp')),
];

if (in_array($dados['statusvel'], ['5', '18'], true)) {
    $dados['matcond'] = '';
}

$sqlExiste = "SELECT idtbveiculo FROM `{$databaseName}`.`tbveiculo` WHERE placa = ? LIMIT 1";
$stmtExiste = mysqli_prepare($con, $sqlExiste);
if (!$stmtExiste) {
    responderCadastroVeiculo('Erro ao verificar veículo: ' . mysqli_error($con));
}
mysqli_stmt_bind_param($stmtExiste, 's', $placa);
mysqli_stmt_execute($stmtExiste);
mysqli_stmt_store_result($stmtExiste);
$veiculoExiste = mysqli_stmt_num_rows($stmtExiste) > 0;
mysqli_stmt_close($stmtExiste);

if ($veiculoExiste) {
    $destinoCadastro = '../cadastroveiculo.php?msg=' . urlencode('Veículo já cadastrado para a placa ' . $placa . '. Cadastre outro veículo.');
    echo '<script language="javascript" type="text/javascript">window.location.href = ' . json_encode($destinoCadastro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
    exit;
}

$colunas = array_keys($dados);
$placeholders = implode(', ', array_fill(0, count($colunas), '?'));
$listaColunas = '`' . implode('`, `', $colunas) . '`';
$sqlInserir = "INSERT INTO `{$databaseName}`.`tbveiculo` ({$listaColunas}) VALUES ({$placeholders})";
$stmtInserir = mysqli_prepare($con, $sqlInserir);

if (!$stmtInserir) {
    responderCadastroVeiculo('Erro ao preparar cadastro: ' . mysqli_error($con));
}

$tipos = str_repeat('s', count($dados));
$valores = array_values($dados);
$parametrosBind = [$stmtInserir, $tipos];
foreach ($valores as $indice => &$valor) {
    $parametrosBind[] = &$valor;
}
unset($valor);
call_user_func_array('mysqli_stmt_bind_param', $parametrosBind);

if (!mysqli_stmt_execute($stmtInserir)) {
    $erro = mysqli_stmt_error($stmtInserir);
    mysqli_stmt_close($stmtInserir);
    responderCadastroVeiculo('Erro ao cadastrar veículo: ' . $erro);
}
mysqli_stmt_close($stmtInserir);

try {
    $documentos = salvarUploadsDocumentosVeiculo($placa);
    persistirDocumentosVeiculo($con, $databaseName, $placa, $documentos);
} catch (RuntimeException $erroDocumentos) {
    responderCadastroVeiculo($erroDocumentos->getMessage());
}

if ($dados['matcond'] !== '') {
    $sqlFuncionario = "SELECT nome FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1";
    $stmtFuncionario = mysqli_prepare($con, $sqlFuncionario);
    if ($stmtFuncionario) {
        mysqli_stmt_bind_param($stmtFuncionario, 's', $dados['matcond']);
        mysqli_stmt_execute($stmtFuncionario);
        $resultadoFuncionario = mysqli_stmt_get_result($stmtFuncionario);
        $funcionario = $resultadoFuncionario ? mysqli_fetch_assoc($resultadoFuncionario) : null;
        mysqli_stmt_close($stmtFuncionario);

        if (!empty($funcionario['nome'])) {
            $sqlCondutor = "INSERT INTO `{$databaseName}`.`tbcondutor` (nome, matricula, ativo, placaassoc, dataassoc) VALUES (?, ?, '1', ?, ?)";
            $stmtCondutor = mysqli_prepare($con, $sqlCondutor);
            if ($stmtCondutor) {
                $nomeCondutor = (string) $funcionario['nome'];
                $matriculaCondutor = $dados['matcond'];
                mysqli_stmt_bind_param($stmtCondutor, 'ssss', $nomeCondutor, $matriculaCondutor, $placa, $hoje);
                mysqli_stmt_execute($stmtCondutor);
                mysqli_stmt_close($stmtCondutor);
            }
        }
    }
}

inserirLogCadastroVeiculo($con, $hoje, campoPost('matr_autor'), $placa);
mysqli_close($con);

redirecionarListagemVeiculo('Veículo cadastrado com sucesso. Placa: ' . $placa . '.');