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
$colsInfo = consultaPreparada($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tbfuncionario'", 's', [$databaseName]);
$colunasExistentes = array_column($colsInfo['linhas'], 'COLUMN_NAME');
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

if ($editando) {
    if ($matriculaOriginal === '') {
        redirecionarComMensagem('../listar_condutorespj.php', 'Matrícula original não informada.');
    }

    $condutorExistente = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? AND matricula LIKE '162%' LIMIT 1", 's', [$matriculaOriginal]);
    if ($condutorExistente === []) {
        redirecionarComMensagem('../listar_condutorespj.php', 'Condutor PJ não encontrado.');
    }

    if ($matricula !== $matriculaOriginal) {
        $matriculaEmUso = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1", 's', [$matricula]);
        if ($matriculaEmUso !== []) {
            redirecionarComMensagem($retornoFormulario, 'Matrícula já cadastrada.');
        }
    }

    $atribuicoes = [];
    foreach (array_keys($dados) as $coluna) {
        $atribuicoes[] = "`{$coluna}` = ?";
    }

    $sql = "UPDATE `{$databaseName}`.`tbfuncionario` SET " . implode(', ', $atribuicoes) . " WHERE matricula = ?";
    $parametros = array_values($dados);
    $parametros[] = $matriculaOriginal;
    $consulta = consultaPreparada($conn, $sql, str_repeat('s', count($parametros)), $parametros);
    if ($consulta['erro'] !== '') {
        redirecionarComMensagem($retornoFormulario, 'Erro ao atualizar: ' . $consulta['erro']);
    }
} else {
    $existe = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1", 's', [$matricula]);
    if ($existe !== []) {
        redirecionarComMensagem('../cadastrar_condutorespj.php', 'Matrícula já cadastrada.');
    }

    $colunas = array_keys($dados);
    $placeholders = implode(',', array_fill(0, count($colunas), '?'));
    $sql = "INSERT INTO `{$databaseName}`.`tbfuncionario` (`" . implode('`,`', $colunas) . "`) VALUES ({$placeholders})";
    $consulta = consultaPreparada($conn, $sql, str_repeat('s', count($dados)), array_values($dados));
    if ($consulta['erro'] !== '') {
        redirecionarComMensagem('../cadastrar_condutorespj.php', 'Erro ao cadastrar: ' . $consulta['erro']);
    }

    $usuarioExistente = buscarUmaLinha($conn, "SELECT id FROM `{$databaseName}`.`tbusuario` WHERE usuario = ? OR matricula = ? LIMIT 1", 'ss', [$matricula, $matricula]);
    if ($usuarioExistente !== []) {
        $consultaUsuario = consultaPreparada(
            $conn,
            "UPDATE `{$databaseName}`.`tbusuario` SET usuario = ?, matricula = ?, senha = ?, perfil = '1' WHERE id = ?",
            'sssi',
            [$matricula, $matricula, $matricula, (int) $usuarioExistente['id']]
        );
    } else {
        $consultaUsuario = consultaPreparada(
            $conn,
            "INSERT INTO `{$databaseName}`.`tbusuario` (usuario, matricula, senha, perfil) VALUES (?, ?, ?, '1')",
            'sss',
            [$matricula, $matricula, $matricula]
        );
    }

    if ($consultaUsuario['erro'] !== '') {
        redirecionarComMensagem('../cadastrar_condutorespj.php', 'Condutor PJ cadastrado, mas ocorreu erro ao criar usuário: ' . $consultaUsuario['erro']);
    }
}

if ($editando && $matricula !== $matriculaOriginal) {
    consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbusuario` SET matricula = ? WHERE matricula = ?", 'ss', [$matricula, $matriculaOriginal]);
}

$mensagemSucesso = $editando ? 'Condutor PJ atualizado com sucesso.' : 'Funcionário e usuário criados com sucesso. Primeiro acesso via matrícula/matrícula.';
redirecionarComMensagem('../listar_condutorespj.php', $mensagemSucesso);