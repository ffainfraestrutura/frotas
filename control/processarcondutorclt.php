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
$funcionario = buscarUmaLinha($conn, "SELECT * FROM `{$databaseCorp}`.`tbfuncionario` WHERE matricula = ? AND idtbempresa = 2 LIMIT 1", 's', [$matricula]);
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

mysqli_begin_transaction($conn);

$colunasCondutor = consultaPreparada($conn, "SHOW COLUMNS FROM `{$databaseName}`.`tbcondutor`");
if ($colunasCondutor['erro'] !== '') {
    mysqli_rollback($conn);
    retornarCnhClt($retorno, 'Não foi possível consultar o cadastro de condutores: ' . $colunasCondutor['erro']);
}

$colunasExistentes = array_column($colunasCondutor['linhas'], 'Field');
$colunasFuncionario = ['matricula', 'nome', 'status', 'dtadmissao', 'cpf', 'rg', 'dtnasc', 'uf_trabalho', 'estado', 'ccusto', 'cargo', 'projeto', 'endereco', 'bairro', 'cidade', 'cep', 'email', 'tel_corp'];
$dadosCondutor = [];
foreach ($colunasFuncionario as $coluna) {
    if (in_array($coluna, $colunasExistentes, true) && array_key_exists($coluna, $funcionario)) {
        $dadosCondutor[$coluna] = (string) ($funcionario[$coluna] ?? '');
    }
}
if (in_array('estado', $colunasExistentes, true) && !isset($dadosCondutor['estado']) && isset($dadosCondutor['uf_trabalho'])) {
    $dadosCondutor['estado'] = $dadosCondutor['uf_trabalho'];
}
if (in_array('regime', $colunasExistentes, true)) {
    $dadosCondutor['regime'] = '1';
}
if (in_array('ativo', $colunasExistentes, true)) {
    $dadosCondutor['ativo'] = strcasecmp((string) ($funcionario['status'] ?? ''), 'ATIVO') === 0 ? '1' : '0';
}
if (in_array('statuscond', $colunasExistentes, true)) {
    $dadosCondutor['statuscond'] = (string) ($funcionario['status'] ?? '');
}

$condutorExistente = buscarUmaLinha($conn, "SELECT idtbcondutor FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? ORDER BY idtbcondutor DESC LIMIT 1", 's', [$matricula]);
if ($condutorExistente === []) {
    $colunas = array_keys($dadosCondutor);
    $placeholders = implode(', ', array_fill(0, count($colunas), '?'));
    $resultadoCondutor = consultaPreparada(
        $conn,
        "INSERT INTO `{$databaseName}`.`tbcondutor` (`" . implode('`, `', $colunas) . "`) VALUES ({$placeholders})",
        str_repeat('s', count($dadosCondutor)),
        array_values($dadosCondutor)
    );
} else {
    $atribuicoes = array_map(static function (string $coluna): string {
        return "`{$coluna}` = ?";
    }, array_keys($dadosCondutor));
    $parametrosCondutor = array_values($dadosCondutor);
    $parametrosCondutor[] = (int) $condutorExistente['idtbcondutor'];
    $resultadoCondutor = consultaPreparada(
        $conn,
        "UPDATE `{$databaseName}`.`tbcondutor` SET " . implode(', ', $atribuicoes) . ' WHERE idtbcondutor = ?',
        str_repeat('s', count($dadosCondutor)) . 'i',
        $parametrosCondutor
    );
    if ($resultadoCondutor['erro'] === '') {
        $resultadoCondutor = consultaPreparada(
            $conn,
            "DELETE FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? AND idtbcondutor <> ?",
            'si',
            [$matricula, (int) $condutorExistente['idtbcondutor']]
        );
    }
}
if ($resultadoCondutor['erro'] !== '') {
    mysqli_rollback($conn);
    retornarCnhClt($retorno, 'Não foi possível salvar o condutor: ' . $resultadoCondutor['erro']);
}

$existente = buscarUmaLinha($conn, "SELECT idtbcnh FROM `{$databaseName}`.`tbcnh` WHERE matricula = ? LIMIT 1", 's', [$matricula]);
$dados = [$numero, $validade, $uf, $categoria, $pontos, $consulta, $suspensa];
if ($existente === []) {
    $resultado = consultaPreparada($conn, "INSERT INTO `{$databaseName}`.`tbcnh` (numcnh, validade, uf, categoria, pontos, consulta, suspensa, matricula) VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 'ssssssss', array_merge($dados, [$matricula]));
} else {
    $resultado = consultaPreparada($conn, "UPDATE `{$databaseName}`.`tbcnh` SET numcnh = ?, validade = ?, uf = ?, categoria = ?, pontos = ?, consulta = ?, suspensa = ? WHERE matricula = ?", 'ssssssss', array_merge($dados, [$matricula]));
}
if ($resultado['erro'] !== '') {
    mysqli_rollback($conn);
    retornarCnhClt($retorno, 'Não foi possível salvar a CNH: ' . $resultado['erro']);
}

mysqli_commit($conn);
retornarCnhClt('../condutores/listar_condutorespj.php', 'CNH do colaborador salva com sucesso.');