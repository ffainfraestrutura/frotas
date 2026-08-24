<?php

$arquivosBootstrap = array(
    __DIR__ . '/../auth.php',
    __DIR__ . '/../../auth.php',
    __DIR__ . '/auth.php',
);
$arquivoAuth = '';
foreach ($arquivosBootstrap as $caminho) {
    if (is_file($caminho)) {
        $arquivoAuth = $caminho;
        break;
    }
}
if ($arquivoAuth === '') {
    die('Arquivo auth.php não encontrado.');
}
require_once $arquivoAuth;

$arquivosConexao = array(
    __DIR__ . '/conecta.php',
    __DIR__ . '/../../control/conecta.php',
    __DIR__ . '/../control/conecta.php',
);
$arquivoConexao = '';
foreach ($arquivosConexao as $caminho) {
    if (is_file($caminho)) {
        $arquivoConexao = $caminho;
        break;
    }
}
if ($arquivoConexao === '') {
    die('Arquivo conecta.php não encontrado.');
}
require_once $arquivoConexao;

$arquivosHelpers = array(
    __DIR__ . '/../includes/portal_helpers.php',
    __DIR__ . '/../../includes/portal_helpers.php',
);
$arquivoHelpers = '';
foreach ($arquivosHelpers as $caminho) {
    if (is_file($caminho)) {
        $arquivoHelpers = $caminho;
        break;
    }
}
if ($arquivoHelpers === '') {
    die('Arquivo portal_helpers.php não encontrado.');
}
require_once $arquivoHelpers;
exigirLogin();

$databaseCorp = trim((string) ($databaseCorp ?? ($GLOBALS['databaseCorp'] ?? '')));
if ($databaseCorp === '') {
    $databaseCorp = 'bdcorp';
}
$databaseName = trim((string) ($databaseName ?? ($database ?? ($GLOBALS['databaseName'] ?? ($GLOBALS['database'] ?? '')))));
if ($databaseName === '') {
    die('Banco de dados do Autofrota não configurado.');
}

$acao = trim((string) ($_POST['acao'] ?? 'cadastrar'));
$editando = $acao === 'editar';
$matriculaOriginal = trim((string) ($_POST['matricula_original'] ?? ''));
$matricula = trim((string) ($_POST['matricula'] ?? ''));
$nome = trim((string) ($_POST['nome'] ?? ''));
$retornoFormulario = $editando && $matriculaOriginal !== ''
    ? '../editar_condutorespj.php?matricula=' . urlencode($matriculaOriginal)
    : '../cadastrar_condutorespj.php';

function redirecionarComMensagem($url, $mensagem)
{
    $separador = strpos($url, '?') === false ? '?' : '&';
    header('Location: ' . $url . $separador . 'msg=' . urlencode($mensagem));
    exit;
}

if ($matricula === '' || $nome === '') {
    redirecionarComMensagem($retornoFormulario, 'Informe matrícula e nome.');
}

if (preg_match('/^16[0-9]{5}$/D', $matricula) !== 1) {
    redirecionarComMensagem($retornoFormulario, 'A matrícula deve começar com 16 e conter exatamente 7 dígitos.');
}

$cpfInformado = preg_replace('/\D+/', '', (string) ($_POST['cpf'] ?? ''));
if ($cpfInformado !== '' && (strlen($cpfInformado) < 8 || strlen($cpfInformado) > 11)) {
    redirecionarComMensagem($retornoFormulario, 'CPF deve conter entre 8 e 11 dígitos.');
}

$emailInformado = trim((string) ($_POST['email'] ?? ''));
if ($emailInformado !== '' && filter_var($emailInformado, FILTER_VALIDATE_EMAIL) === false) {
    redirecionarComMensagem($retornoFormulario, 'Informe um e-mail válido.');
}

$telefoneInformado = preg_replace('/\D+/', '', (string) ($_POST['tel_corp'] ?? ''));
if (strlen($telefoneInformado) > 11) {
    redirecionarComMensagem($retornoFormulario, 'Telefone deve conter no máximo 11 dígitos.');
}

$permitidos = ['matricula','nome','status','dtadmissao','cpf','rg','dtnasc','uf_trabalho','estado','ccusto','cargo','projeto','endereco','bairro','cidade','cep','email','tel_corp'];
$colsInfo = consultaPreparada($conn, "SHOW COLUMNS FROM `{$databaseName}`.`tbcondutor`");
$colunasExistentes = array_column($colsInfo['linhas'], 'Field');
$dados = [];
foreach ($permitidos as $coluna) {
    if (in_array($coluna, $colunasExistentes, true) && array_key_exists($coluna, $_POST)) {
        $valor = trim((string) $_POST[$coluna]);
        if (in_array($coluna, ['cpf', 'tel_corp'], true)) {
            $valor = preg_replace('/\D+/', '', $valor);
        }
        if (in_array($coluna, ['nome','cargo','projeto','endereco','bairro','cidade','uf_trabalho','estado'], true)) {
            $valor = mb_strtoupper($valor, 'UTF-8');
        }
        $dados[$coluna] = $valor;
    }
}
if (in_array('estado', $colunasExistentes, true) && !isset($dados['estado']) && isset($dados['uf_trabalho'])) {
    $dados['estado'] = $dados['uf_trabalho'];
}
if (isset($dados['status'])) {
    if (in_array('ativo', $colunasExistentes, true)) {
        $dados['ativo'] = strcasecmp($dados['status'], 'ATIVO') === 0 ? '1' : '0';
    }
    if (in_array('statuscond', $colunasExistentes, true)) {
        $dados['statuscond'] = $dados['status'];
    }
}

if ($editando) {
    if ($matriculaOriginal === '') {
        redirecionarComMensagem('../listar_condutorespj.php', 'Matrícula original não informada.');
    }

    $condutorExistente = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? AND matricula REGEXP '^16[0-9]{5}$' LIMIT 1", 's', [$matriculaOriginal]);
    if ($condutorExistente === []) {
        redirecionarComMensagem('../listar_condutorespj.php', 'Condutor PJ não encontrado.');
    }

    if ($matricula !== $matriculaOriginal) {
        $matriculaEmUso = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? LIMIT 1", 's', [$matricula]);
        if ($matriculaEmUso !== []) {
            redirecionarComMensagem($retornoFormulario, 'Matrícula já cadastrada.');
        }
    }

    $atribuicoes = [];
    foreach (array_keys($dados) as $coluna) {
        $atribuicoes[] = "`{$coluna}` = ?";
    }

    $sql = "UPDATE `{$databaseName}`.`tbcondutor` SET " . implode(', ', $atribuicoes) . " WHERE matricula = ?";
    $parametros = array_values($dados);
    $parametros[] = $matriculaOriginal;
    $consulta = consultaPreparada($conn, $sql, str_repeat('s', count($parametros)), $parametros);
    if ($consulta['erro'] !== '') {
        redirecionarComMensagem($retornoFormulario, 'Erro ao atualizar: ' . $consulta['erro']);
    }
} else {
    $existe = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? LIMIT 1", 's', [$matricula]);
    if ($existe !== []) {
        redirecionarComMensagem('../cadastrar_condutorespj.php', 'Matrícula já cadastrada.');
    }

    // Esta tela cadastra exclusivamente condutores PJ e já os libera como ativos.
    if (in_array('regime', $colunasExistentes, true)) {
        $dados['regime'] = '0';
    }
    if (in_array('ativo', $colunasExistentes, true)) {
        $dados['ativo'] = '1';
    }

    $colunas = array_keys($dados);
    $placeholders = implode(',', array_fill(0, count($colunas), '?'));
    $sql = "INSERT INTO `{$databaseName}`.`tbcondutor` (`" . implode('`,`', $colunas) . "`) VALUES ({$placeholders})";
    $consulta = consultaPreparada($conn, $sql, str_repeat('s', count($dados)), array_values($dados));
    if ($consulta['erro'] !== '') {
        redirecionarComMensagem('../cadastrar_condutorespj.php', 'Erro ao cadastrar: ' . $consulta['erro']);
    }

    $usuarioExistente = buscarUmaLinha($conn, "SELECT id_usuario FROM `{$databaseCorp}`.`tbusuario` WHERE usuario = ? OR matricula = ? LIMIT 1", 'ss', [$matricula, $matricula]);
    if ($usuarioExistente !== []) {
        $consultaUsuario = consultaPreparada(
            $conn,
            "UPDATE `{$databaseCorp}`.`tbusuario` SET usuario = ?, matricula = ?, senha = ?, perfil = '1', autofrota = 1 WHERE id_usuario = ?",
            'sssi',
            [$matricula, $matricula, $matricula, (int) $usuarioExistente['id_usuario']]
        );
    } else {
        $consultaUsuario = consultaPreparada(
            $conn,
            "INSERT INTO `{$databaseCorp}`.`tbusuario` (usuario, matricula, senha, perfil, autofrota) VALUES (?, ?, ?, '1', 1)",
            'sss',
            [$matricula, $matricula, $matricula]
        );
    }

    if ($consultaUsuario['erro'] !== '') {
        redirecionarComMensagem('../cadastrar_condutorespj.php', 'Condutor PJ cadastrado, mas ocorreu erro ao criar usuário: ' . $consultaUsuario['erro']);
    }
}

if ($editando && $matricula !== $matriculaOriginal) {
    consultaPreparada($conn, "UPDATE `{$databaseCorp}`.`tbusuario` SET usuario = ?, matricula = ? WHERE matricula = ?", 'sss', [$matricula, $matricula, $matriculaOriginal]);
}

$mensagemSucesso = $editando ? 'Condutor PJ atualizado com sucesso.' : 'Condutor e usuário criados com sucesso. Primeiro acesso via matrícula/matrícula.';
redirecionarComMensagem('../listar_condutorespj.php', $mensagemSucesso);