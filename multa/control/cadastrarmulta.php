<?php
date_default_timezone_set('America/Sao_Paulo');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../../func/log.php';
require_once '../../control/conecta.php';

define('REDIRECT_SUCCESS', '../cadastromulta.php');
define('REDIRECT_ERROR', '../cadastromulta.php');

$db = null;
$transactionStarted = false;

if (isset($conexao) && $conexao instanceof mysqli) {
    $db = $conexao;
} elseif (isset($conn) && $conn instanceof mysqli) {
    $db = $conn;
}

if (!$db) {
    responderComAlerta('Erro de conexao com o banco de dados.', REDIRECT_ERROR);
}

mysqli_set_charset($db, 'utf8mb4');

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderComAlerta('Requisicao invalida.', REDIRECT_ERROR);
}

try {
    $hoje = date('Y-m-d H:i:s');

    $placa = normalizarPlaca(post('placa'));
    $filial = post('filial');
    $ccusto = post('ccusto');
    $fornecedor = post('fornecedor');
    $anotacao = post('anotacao', '');
    $tipoinfracao = post('tipoinfracao');
    $autoinfracao = upper(post('autoinfracao'));
    $datainfracao = post('datainfracao');
    $horainfracao = post('horainfracao');
    $datalimitecond = post('datalimitecond');
    $datalimiteloc = post('datalimiteloc');
    $expedicao = post('expedicao');
    $recebimento = post('recebimento');
    $vencimento = post('vencimento');
    $codigom = post('codigom');
    $pontos = postInt('pontos');
    $gravidade = post('gravidade');
    $valor = parseMoeda(post('valor'));
    $valdesconto = parseMoeda(post('valdesconto', '0'));
    $taxaadm = parseMoeda(post('taxaadm', '0'));
    $juros = parsePercentual(post('juros', '0'));
    $valtotal = parseMoeda(post('valtotal', '0'));
    $valcomdesc = parseMoeda(post('valcomdesc', '0'));
    $orgao = upper(post('orgao'));
    $endereco = upper(post('endereco'));
    $municipio = upper(post('municipio'));
    $matriculac = somenteNumeros(post('matriculac'));
    $nomecInformado = upper(post('nomec', ''));
    $matrAutor = somenteNumeros(post('matr_autor'));

    $descontoapartir = nullableDate(post('descontoapartir', ''));
    $descontadocondutor = post('descontadocondutor', '');
    $tipodesconto = post('tipodesconto', '');
    $datadesconto = nullableDate(post('datadesconto', ''));
    $realinfrator = post('realinfrator', '');
    $pessoaindicada = post('pessoaindicada', '');
    $condassinatura = post('condassinatura', '');
    $reciboassinado = nullableDate(post('reciboassinado', ''));
    $recusaassinar = nullableDate(post('recusaassinar', ''));

    $status = post('status', '1');
    $etapa = post('etapa', 'EM ABERTO');
    $descricaoinfra = post('descricaoinfra', post('descricao', ''));
    $datarecorg = nullableDate(post('datarecorg', ''));
    $protrecorg = post('protrecorg', '');
    $comprpag = post('comprpag', '');
    $pgempresa = post('pgempresa', '');
    $datapagempr = nullableDate(post('datapagempr', ''));
    $motivonpg = post('motivonpg', '');
    $numprotocolo = post('numprotocolo', post('datanumprotocolo', ''));
    $expprotocolo = nullableDate(post('expprotocolo', ''));
    $recprotocolo = nullableDate(post('recprotocolo', ''));

    validarObrigatorios(array(
        'placa' => $placa,
        'filial' => $filial,
        'ccusto' => $ccusto,
        'fornecedor' => $fornecedor,
        'tipo de infracao' => $tipoinfracao,
        'auto de infracao' => $autoinfracao,
        'data da infracao' => $datainfracao,
        'hora da infracao' => $horainfracao,
        'data limite do condutor' => $datalimitecond,
        'data limite da locadora' => $datalimiteloc,
        'expedicao' => $expedicao,
        'recebimento' => $recebimento,
        'vencimento' => $vencimento,
        'codigo da multa' => $codigom,
        'gravidade' => $gravidade,
        'valor' => $valor,
        'orgao autuador' => $orgao,
        'endereco' => $endereco,
        'municipio' => $municipio,
        'matricula do condutor' => $matriculac
    ));

    validarData($datainfracao, 'Data da infracao');
    validarHora($horainfracao, 'Hora da infracao');
    validarData($datalimitecond, 'Data limite do condutor');
    validarData($datalimiteloc, 'Data limite da locadora');
    validarData($expedicao, 'Data de expedicao');
    validarData($recebimento, 'Data de recebimento');
    validarData($vencimento, 'Data de vencimento');

    if (!preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $placa)) {
        throw new Exception('Placa invalida.');
    }

    if ($pontos < 0) {
        throw new Exception('Pontuacao invalida.');
    }

    $diainfracao = $datainfracao . ' ' . $horainfracao . ':00';
    $valorMulta = (float)($valtotal > 0 ? $valtotal : $valor);
    $numparcelas = max(1, (int)ceil($valorMulta / 200));
    $valparcelas = number_format(ceil(($valorMulta / $numparcelas) * 100) / 100, 2, '.', '');

    $funcionario = buscarFuncionarioPorMatricula($db, $matriculac);
    $cpf = $funcionario['cpf'];
    $nomec = $funcionario['nome'] !== '' ? $funcionario['nome'] : $nomecInformado;

    if ($nomec === '') {
        throw new Exception('Condutor nao encontrado para a matricula informada.');
    }

    if (!placaExiste($db, $placa)) {
        responderComAlerta('Placa nao cadastrada no sistema.', REDIRECT_ERROR);
    }

    iniciarTransacao($db);
    $transactionStarted = true;

    $sqlMulta = "
        INSERT INTO tbmulta (
            placa, filial, ccusto, fornecedor, anotacao, tipoinfracao, autoinfracao,
            datainfracao, datalimitecond, datalimiteloc, expedicao, recebimento,
            vencimento, codigom, pontos, gravidade, valor, valdesconto, taxaadm,
            juros, valtotal, orgao, endereco, municipio, matriculac, nomec,
            numparcelas, valparcelas, descontoapartir, descontadocondutor,
            tipodesconto, datadesconto, realinfrator, pessoaindicada,
            condassinatura, reciboassinado, recusaassinar, status, etapa,
            descricaoinfra, valcomdesc, datarecorg, protrecorg, comprpag,
            pgempresa, datapagempr, motivonpg, numprotocolo, expprotocolo,
            recprotocolo
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?
        )
    ";

    executarPrepared($db, $sqlMulta, array(
        $placa,
        $filial,
        $ccusto,
        $fornecedor,
        $anotacao,
        $tipoinfracao,
        $autoinfracao,
        $diainfracao,
        $datalimitecond,
        $datalimiteloc,
        $expedicao,
        $recebimento,
        $vencimento,
        $codigom,
        (string)$pontos,
        $gravidade,
        $valor,
        $valdesconto,
        $taxaadm,
        $juros,
        $valtotal,
        $orgao,
        $endereco,
        $municipio,
        $matriculac,
        $nomec,
        (string)$numparcelas,
        $valparcelas,
        $descontoapartir,
        $descontadocondutor,
        $tipodesconto,
        $datadesconto,
        $realinfrator,
        $pessoaindicada,
        $condassinatura,
        $reciboassinado,
        $recusaassinar,
        $status,
        $etapa,
        $descricaoinfra,
        $valcomdesc,
        $datarecorg,
        $protrecorg,
        $comprpag,
        $pgempresa,
        $datapagempr,
        $motivonpg,
        $numprotocolo,
        $expprotocolo,
        $recprotocolo
    ));

    $idtbmulta = (string)mysqli_insert_id($db);

    $sqlTramite = "
        INSERT INTO tbmovidatramite (
            placa, autoinfra, pontuacao, gravidade, apCondDV, dtinfra, dtvenc,
            valor, status, nome, matricula, cpf, tramite, locadora, idmulta
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ";

    executarPrepared($db, $sqlTramite, array(
        $placa,
        $autoinfracao,
        (string)$pontos,
        $gravidade,
        $datalimitecond,
        $diainfracao,
        $vencimento,
        $valor,
        '1',
        $nomec,
        $matriculac,
        $cpf,
        'Inserir infrator',
        $fornecedor,
        $idtbmulta
    ));

    enviarlognovo($hoje, 'Cadastrou multa', $matriculac, $matrAutor, 'cadastro', $placa);

    confirmarTransacao($db);
    $transactionStarted = false;

    responderComAlerta('Multa cadastrada com sucesso.', REDIRECT_SUCCESS);
} catch (Exception $erro) {
    if ($db instanceof mysqli && $transactionStarted) {
        desfazerTransacao($db);
    }

    error_log('Erro ao cadastrar multa: ' . $erro->getMessage());
    //responderComAlerta('Nao foi possivel cadastrar a multa. Verifique os dados e tente novamente.', REDIRECT_ERROR);

      echo '<p style="color: red;">Erro: ' . htmlspecialchars($erro->getMessage()) . '</p>';
}

function post($key, $default = '')
{
    if (!isset($_POST[$key]) || is_array($_POST[$key])) {
        return (string)$default;
    }

    return trim((string)$_POST[$key]);
}

function postInt($key, $default = 0)
{
    $value = somenteNumeros(post($key, (string)$default));
    return $value === '' ? $default : (int)$value;
}

function somenteNumeros($value)
{
    return preg_replace('/\D+/', '', (string)$value);
}

function normalizarPlaca($placa)
{
    return upper(preg_replace('/[^A-Za-z0-9]/', '', (string)$placa));
}

function upper($value)
{
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper((string)$value, 'UTF-8');
    }

    return strtoupper((string)$value);
}

function parseMoeda($value)
{
    $value = trim((string)$value);

    if ($value === '') {
        return '0.00';
    }

    $value = str_replace(array('R$', ' '), '', $value);
    $value = str_replace('.', '', $value);
    $value = str_replace(',', '.', $value);

    if (!is_numeric($value)) {
        return '0.00';
    }

    return number_format((float)$value, 2, '.', '');
}

function parsePercentual($value)
{
    $value = trim(str_replace('%', '', (string)$value));
    $value = str_replace(',', '.', $value);

    if ($value === '' || !is_numeric($value)) {
        return '0.00';
    }

    return number_format((float)$value, 2, '.', '');
}

function nullableDate($value)
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function validarObrigatorios($campos)
{
    foreach ($campos as $nome => $valor) {
        if ($valor === null || $valor === '') {
            throw new Exception('Campo obrigatorio ausente: ' . $nome . '.');
        }
    }
}

function validarData($value, $label)
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    $warningCount = is_array($errors) ? $errors['warning_count'] : 0;
    $errorCount = is_array($errors) ? $errors['error_count'] : 0;

    if (!$date || $warningCount > 0 || $errorCount > 0) {
        throw new Exception($label . ' invalida.');
    }
}

function validarHora($value, $label)
{
    $date = DateTime::createFromFormat('H:i', $value);
    $errors = DateTime::getLastErrors();
    $warningCount = is_array($errors) ? $errors['warning_count'] : 0;
    $errorCount = is_array($errors) ? $errors['error_count'] : 0;

    if (!$date || $warningCount > 0 || $errorCount > 0) {
        throw new Exception($label . ' invalida.');
    }
}

function buscarFuncionarioPorMatricula($db, $matricula)
{
    $stmt = executarPrepared($db, 'SELECT nome, cpf FROM bdaniel.tbfuncionario WHERE matricula = ? LIMIT 1', array($matricula));
    mysqli_stmt_bind_result($stmt, $nome, $cpf);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return array(
        'nome' => $found ? (string)$nome : '',
        'cpf' => $found ? (string)$cpf : ''
    );
}

function placaExiste($db, $placa)
{
    $stmt = executarPrepared($db, 'SELECT 1 FROM tbveiculo WHERE placa = ? LIMIT 1', array($placa));
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $exists;
}

function executarPrepared($db, $sql, $params)
{
    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        throw new Exception('Erro ao preparar SQL: ' . mysqli_error($db));
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $refs = array();
        $refs[] = $types;

        foreach ($params as $key => $value) {
            $refs[] = &$params[$key];
        }

        if (!call_user_func_array(array($stmt, 'bind_param'), $refs)) {
            $message = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            throw new Exception('Erro ao vincular parametros: ' . $message);
        }
    }

    if (!mysqli_stmt_execute($stmt)) {
        $message = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception('Erro ao executar SQL: ' . $message);
    }

    return $stmt;
}

function iniciarTransacao($db)
{
    if (function_exists('mysqli_begin_transaction')) {
        mysqli_begin_transaction($db);
        return;
    }

    mysqli_query($db, 'START TRANSACTION');
}

function confirmarTransacao($db)
{
    if (!mysqli_commit($db)) {
        throw new Exception('Erro ao confirmar transacao: ' . mysqli_error($db));
    }
}

function desfazerTransacao($db)
{
    mysqli_rollback($db);
}

function responderComAlerta($mensagem, $url)
{
    $mensagemJson = json_encode($mensagem, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    $urlJson = json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><title>Cadastro de Multa</title></head><body>';
    echo '<script>alert(' . $mensagemJson . ');window.location.href=' . $urlJson . ';</script>';
    echo '</body></html>';
    exit;
}
