<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/conecta.php';
require_once __DIR__ . '/../includes/portal_helpers.php';
exigirLogin();

$acao = trim((string) ($_POST['acao'] ?? 'cadastrar'));
$editando = $acao === 'editar';
$matriculaOriginal = trim((string) ($_POST['matricula_original'] ?? ''));
$matricula = trim((string) ($_POST['matricula'] ?? ''));
$nome = trim((string) ($_POST['nome'] ?? ''));
$retornoFormulario = $editando && $matriculaOriginal !== ''
    ? '../editar_condutorespj.php?matricula=' . urlencode($matriculaOriginal)
    : '../cadastrar_condutorespj.php';

function redirecionarComMensagem(string $url, string $mensagem): void
{
    $separador = strpos($url, '?') === false ? '?' : '&';
    header('Location: ' . $url . $separador . 'msg=' . urlencode($mensagem));
    exit;
}

if ($matricula === '' || $nome === '') {
    redirecionarComMensagem($retornoFormulario, 'Informe matrícula e nome.');
}

$permitidos = ['matricula','nome','status','dtadmissao','cpf','rg','dtnasc','uf_trabalho','estado','ccusto','cargo','projeto','endereco','bairro','cidade','cep'];
$colsInfo = consultaPreparada($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tbfuncionario'", 's', [$databaseName]);
$colunasExistentes = array_column($colsInfo['linhas'], 'COLUMN_NAME');
$dados = [];
foreach ($permitidos as $coluna) {
    if (in_array($coluna, $colunasExistentes, true) && array_key_exists($coluna, $_POST)) {
        $valor = trim((string) $_POST[$coluna]);
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

    $condutorExistente = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? AND matricula LIKE '62%' LIMIT 1", 's', [$matriculaOriginal]);
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
}

$email = trim((string) ($_POST['email'] ?? ''));
$telefone = trim((string) ($_POST['telefone'] ?? ''));
$matriculaContato = $matricula;
if ($email !== '' || $telefone !== '') {
    $userCols = consultaPreparada($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'tbusuario'", 's', [$databaseName]);
    $userExistentes = array_column($userCols['linhas'], 'COLUMN_NAME');
    $userDados = ['matricula' => $matriculaContato];
    if (in_array('email', $userExistentes, true)) { $userDados['email'] = $email; }
    if (in_array('telefone', $userExistentes, true)) { $userDados['telefone'] = $telefone; }
    if (count($userDados) > 1 && in_array('matricula', $userExistentes, true)) {
        $usuarioExistente = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseName}`.`tbusuario` WHERE matricula = ? LIMIT 1", 's', [$editando ? $matriculaOriginal : $matriculaContato]);
        if ($usuarioExistente !== []) {
            $atualizacoesUsuario = [];
            $parametrosUsuario = [];
            if (isset($userDados['email'])) { $atualizacoesUsuario[] = '`email` = ?'; $parametrosUsuario[] = $email; }
            if (isset($userDados['telefone'])) { $atualizacoesUsuario[] = '`telefone` = ?'; $parametrosUsuario[] = $telefone; }
            if ($editando && $matricula !== $matriculaOriginal) { $atualizacoesUsuario[] = '`matricula` = ?'; $parametrosUsuario[] = $matricula; }
            $parametrosUsuario[] = $editando ? $matriculaOriginal : $matriculaContato;
            consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbusuario` SET " . implode(', ', $atualizacoesUsuario) . " WHERE matricula = ?", str_repeat('s', count($parametrosUsuario)), $parametrosUsuario);
        } else {
            consultaPreparada($conn, "INSERT INTO `{$databaseName}`.`tbusuario` (`" . implode('`,`', array_keys($userDados)) . "`) VALUES (" . implode(',', array_fill(0, count($userDados), '?')) . ")", str_repeat('s', count($userDados)), array_values($userDados));
        }
    }
} elseif ($editando && $matricula !== $matriculaOriginal) {
    consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbusuario` SET matricula = ? WHERE matricula = ?", 'ss', [$matricula, $matriculaOriginal]);
}

$mensagemSucesso = $editando ? 'Condutor PJ atualizado com sucesso.' : 'Condutor PJ cadastrado com sucesso.';
redirecionarComMensagem('../listar_condutorespj.php', $mensagemSucesso);