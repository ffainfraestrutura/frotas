<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$sessao = autofrotaInit();
$conn = $sessao['conn'] ?? null;
$databaseName = (string) ($sessao['databaseName'] ?? '');
$databaseCorp = trim((string) ($sessao['databaseCorp'] ?? ($GLOBALS['databaseCorp'] ?? 'bdcorp')));

function retornarCnhClt(string $url, string $mensagem): void
{
    header('Location: ' . $url . (strpos($url, '?') === false ? '?' : '&') . 'msg=' . urlencode($mensagem));
    exit;
}

$matricula = trim((string) ($_POST['matricula'] ?? ''));
$retorno = '../condutores/editar_condutoresclt.php?matricula=' . urlencode($matricula);
$funcionario = buscarUmaLinha($conn, "SELECT matricula FROM `{$databaseCorp}`.`tbfuncionario` WHERE matricula = ? AND idtbempresa = 2 LIMIT 1", 's', [$matricula]);
if ($matricula === '' || $funcionario === []) {
    retornarCnhClt('../condutores/listar_condutoresclt.php', 'Funcionário da empresa 2 não encontrado.');
}

$numero = preg_replace('/\D+/', '', (string) ($_POST['cnh_numero'] ?? ''));
$validade = trim((string) ($_POST['cnh_validade'] ?? ''));
$uf = mb_strtoupper(trim((string) ($_POST['cnh_uf'] ?? '')), 'UTF-8');
$categoria = mb_strtoupper(trim((string) ($_POST['cnh_categoria'] ?? '')), 'UTF-8');
$pontos = preg_replace('/\D+/', '', (string) ($_POST['cnh_pontos'] ?? '')) ?: '0';
$consulta = trim((string) ($_POST['cnh_consulta'] ?? ''));
$suspensa = in_array((string) ($_POST['cnh_suspensa'] ?? '0'), ['0', '1'], true) ? (string) $_POST['cnh_suspensa'] : '0';
if ($numero === '' || $validade === '' || $uf === '' || $categoria === '' || $consulta === '') {
    retornarCnhClt($retorno, 'Preencha número, validade, UF, categoria e data da consulta ao DETRAN.');
}
if (strlen($numero) > 12 || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $validade) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $consulta) !== 1) {
    retornarCnhClt($retorno, 'Confira o número e as datas informadas para a CNH.');
}

$existente = buscarUmaLinha($conn, "SELECT idtbcnh FROM `{$databaseName}`.`tbcnh` WHERE matricula = ? LIMIT 1", 's', [$matricula]);
$dados = [$numero, $validade, $uf, $categoria, $pontos, $consulta, $suspensa];
if ($existente === []) {
    $resultado = consultaPreparada($conn, "INSERT INTO `{$databaseName}`.`tbcnh` (numcnh, validade, uf, categoria, pontos, consulta, suspensa, matricula) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 'ssssssss', array_merge($dados, [$matricula]));
} else {
    $resultado = consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbcnh` SET numcnh = ?, validade = ?, uf = ?, categoria = ?, pontos = ?, consulta = ?, suspensa = ? WHERE matricula = ?", 'ssssssss', array_merge($dados, [$matricula]));
}
if ($resultado['erro'] !== '') {
    retornarCnhClt($retorno, 'Não foi possível salvar a CNH: ' . $resultado['erro']);
}
retornarCnhClt('../condutores/listar_condutoresclt.php', 'CNH do colaborador salva com sucesso.');