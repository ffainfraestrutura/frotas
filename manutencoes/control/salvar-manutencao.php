<?php
require_once __DIR__ . '/../../auth.php';
require_once __DIR__ . '/../../control/conecta.php';

exigirLogin();

$perfilLogado = (string) ($_SESSION['perfil'] ?? '');
$matriculaLogada = (string) ($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$podeEditar = $perfilLogado === '4';

function redirectComMensagem(int $id, string $msg): never
{
    if ($id <= 0) {
        $placa = normalizarPlaca($_POST['placa'] ?? '');
        header('Location: ../cadastrar-manutencao-preventiva.php?placa=' . rawurlencode($placa) . '&msg=' . rawurlencode($msg));
        exit;
    }

    header('Location: ../editar-manutencao.php?idtbmanprev=' . $id . '&msg=' . rawurlencode($msg));
    exit;
}

function redirectParaListagem(string $msg): never
{
    header('Location: ../listagem-manutencao.php?msg=' . rawurlencode($msg));
    exit;
}

function normalizarTexto(?string $valor): string
{
    return trim((string) $valor);
}

function normalizarPlaca(?string $placa): string
{
    $placa = strtoupper(trim((string) $placa));
    return str_replace(['-', ' '], '', $placa);
}

function normalizarHodometro(?string $valor): string
{
    $valor = trim((string) $valor);
    return str_replace('.', '', $valor);
}

function normalizarValorMonetario(?string $valor): ?string
{
    $valor = trim((string) $valor);
    if ($valor === '') {
        return null;
    }

    if (strpos($valor, ' ') !== false) {
        $partes = explode(' ', $valor);
        $valor = end($partes) ?: '';
    }

    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);

    return $valor === '' ? null : $valor;
}

function normalizarData(?string $data): ?string
{
    $data = trim((string) $data);
    return $data === '' ? null : $data;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirectComMensagem(0, 'Requisição inválida.');
}

$id = (int) ($_POST['idtbmanprev'] ?? 0);

// Se id <= 0, trataremos como criação de nova manutenção (inserção)
$isCreate = $id <= 0;

if (!$podeEditar) {
    redirectComMensagem($id, 'Sem permissão para editar esta manutenção.');
}

$mapaTabelas = [
    'tbveic' => 'tbveiculo',
    'tbstatusveic' => 'tbveiculostatus',
    'tbaplicacaoveic' => 'tbveiculoaplicacao',
    'tbmodeloveic' => 'tbveiculomodelo',
    'bdaniel.tbfuncionario' => 'bdautofrotas.tbfuncionario',
    'tbatipovel' => 'tbveltipo',
    'tbastatusvel' => 'tbvelstatus',
];

$placa = normalizarPlaca($_POST['placa'] ?? '');
$tipo = normalizarTexto($_POST['manutencao'] ?? $_POST['tipo'] ?? '');
$hodometro = normalizarHodometro($_POST['hodometro'] ?? '');
$solicitante = normalizarTexto($_POST['solicitante'] ?? '');
$status = normalizarTexto($_POST['status'] ?? '');
$etapa = normalizarTexto($_POST['etapa'] ?? '');
$dataocorrencia = normalizarData($_POST['dataocorrencia'] ?? null);
$modelo = normalizarTexto($_POST['modelo'] ?? '');
$fornman = normalizarTexto($_POST['fornman'] ?? '');
$descricao = normalizarTexto($_POST['descricao'] ?? '');
$ccusto = normalizarTexto($_POST['ccusto'] ?? '');
$oficina = normalizarTexto($_POST['oficina'] ?? '');
$dataagendamento = normalizarData($_POST['dataagendamento'] ?? null);
$horaagendamento = normalizarTexto($_POST['horaagendamento'] ?? '');
$prevsaida = normalizarData($_POST['prevsaida'] ?? null);
$dataentrada = normalizarData($_POST['dataentrada'] ?? null);
$dataretirada = normalizarData($_POST['dataretirada'] ?? null);
$tipopagamento = normalizarTexto($_POST['tipopagamento'] ?? '');
$reembolsoaprov = normalizarTexto($_POST['reembolsoaprov'] ?? '0');
$valorreembolso = normalizarValorMonetario($_POST['valorreembolso'] ?? null);
$valoroficina = normalizarValorMonetario($_POST['valoroficina'] ?? null);
$valordesconto = normalizarValorMonetario($_POST['valordesconto'] ?? null);
$valormaoobra = normalizarValorMonetario($_POST['valormaoobra'] ?? null);
$valormaterial = normalizarValorMonetario($_POST['valormaterial'] ?? null);
$valortransp = normalizarValorMonetario($_POST['valortransp'] ?? null);
$outrosvalor = normalizarValorMonetario($_POST['outrosvalor'] ?? null);
$descontarcond = normalizarTexto($_POST['descontarcond'] ?? '0');
$datavencimento = normalizarData($_POST['datavencimento'] ?? null);
$datapagamento = normalizarData($_POST['datapagamento'] ?? null);
$formapagam = normalizarTexto($_POST['formapagam'] ?? '');
$condicaopag = normalizarTexto($_POST['condicaopag'] ?? '');
$numparc = normalizarTexto($_POST['numparc'] ?? '');
$valorparcela = normalizarValorMonetario($_POST['valorparcela'] ?? null);
$dataprimparc = normalizarData($_POST['dataprimparc'] ?? null);
$protocolo = normalizarTexto($_POST['protocolo'] ?? '');
$dataconclusao = normalizarData($_POST['dataconclusao'] ?? null);
$placaanterior = normalizarPlaca($_POST['placaanterior'] ?? '');
$observacao = normalizarTexto($_POST['observacao'] ?? '');

$limiteNumerico = 1000000;
$camposNumericos = [
    'Hodômetro' => $hodometro,
    'Valor de reembolso' => $valorreembolso,
    'Valor da oficina' => $valoroficina,
    'Valor do desconto' => $valordesconto,
    'Valor de mão de obra' => $valormaoobra,
    'Valor de material' => $valormaterial,
    'Valor de transporte' => $valortransp,
    'Outros valores' => $outrosvalor,
    'Número de parcelas' => $numparc,
    'Valor da parcela' => $valorparcela,
];
foreach ($camposNumericos as $rotulo => $valorNumerico) {
    if ($valorNumerico !== null && $valorNumerico !== '' && (!is_numeric($valorNumerico) || (float) $valorNumerico < 0 || (float) $valorNumerico > $limiteNumerico)) {
        redirectComMensagem($id, $rotulo . ' deve ser um número de até 7 dígitos (máximo de 1.000.000).');
    }
}

$dataagendamentoCompleta = $dataagendamento;
if ($dataagendamento !== null && $horaagendamento !== '') {
    $dataagendamentoCompleta = $dataagendamento . ' ' . $horaagendamento . ':00';
}

$tabelaVeiculo = 'bdautofrotas.' . $mapaTabelas['tbveic'];
if ($placa !== '') {
    $sqlVeiculo = "SELECT placa FROM {$tabelaVeiculo} WHERE placa = ? LIMIT 1";
    $stmtVeiculo = mysqli_prepare($conn, $sqlVeiculo);
    if ($stmtVeiculo) {
        mysqli_stmt_bind_param($stmtVeiculo, 's', $placa);
        mysqli_stmt_execute($stmtVeiculo);
        $resVeiculo = mysqli_stmt_get_result($stmtVeiculo);
        $veiculoExiste = $resVeiculo && mysqli_num_rows($resVeiculo) > 0;
        mysqli_stmt_close($stmtVeiculo);

        if (!$veiculoExiste) {
            redirectComMensagem($id, 'Placa não encontrada na base de veículos.');
        }
    }
}

$docAtual = null;
$stmtAtual = mysqli_prepare($conn, 'SELECT doc FROM bdautofrotas.tbmanprev WHERE idtbmanprev = ? LIMIT 1');
if ($stmtAtual) {
    mysqli_stmt_bind_param($stmtAtual, 'i', $id);
    mysqli_stmt_execute($stmtAtual);
    $resAtual = mysqli_stmt_get_result($stmtAtual);
    $rowAtual = $resAtual ? mysqli_fetch_assoc($resAtual) : null;
    $docAtual = $rowAtual['doc'] ?? null;
    mysqli_stmt_close($stmtAtual);
}

$doc = $docAtual;
if (isset($_FILES['arquivo']) && is_array($_FILES['arquivo']) && ($_FILES['arquivo']['name'] ?? '') !== '') {
    $permitidas = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    $ext = strtolower((string) pathinfo((string) $_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $permitidas, true)) {
        redirectComMensagem($id, 'Formato de arquivo inválido. Use: jpg, jpeg, png, gif ou pdf.');
    }

    $pastaRelativa = '../docs/docmanutencao/';
    $pastaUpload = __DIR__ . '/' . $pastaRelativa;
    if (!is_dir($pastaUpload)) {
        @mkdir($pastaUpload, 0775, true);
    }

    $baseNome = ($placa !== '' ? $placa : 'MAN') . '-' . date('YmdHis') . '-' . $tipo;
    $baseNome = preg_replace('/[^A-Z0-9_-]/i', '_', (string) $baseNome);
    $nomeFinal = $baseNome . '.' . $ext;
    $destino = $pastaUpload . $nomeFinal;

    if (!move_uploaded_file((string) $_FILES['arquivo']['tmp_name'], $destino)) {
        redirectComMensagem($id, 'Não foi possível enviar o arquivo.');
    }

    $doc = $pastaRelativa . $nomeFinal;
}

    if ($isCreate) {
        $columns = [
            'placa','tipo','hodometro','solicitante','status','etapa','dataocorrencia','modelo','fornman','descricao',
            'ccusto','oficina','dataagendamento','prevsaida','dataentrada','dataretirada','tipopagamento','reembolsoaprov',
            'valorreembolso','valoroficina','valordesconto','valormaoobra','valormaterial','valortransp','outrosvalor',
            'descontarcond','datavencimento','datapagamento','formapagam','condicaopag','numparc','valorparcela','dataprimparc',
            'protocolo','dataconclusao','placaanterior','observacao','doc'
        ];

        $placeholders = array_fill(0, count($columns), '?');
        $sqlInsert = 'INSERT INTO bdautofrotas.tbmanprev (' . implode(',', $columns) . ', atualizadoem) VALUES (' . implode(',', $placeholders) . ', NOW())';

        $values = [
            $placa,
            $tipo,
            $hodometro,
            $solicitante,
            $status,
            $etapa,
            $dataocorrencia,
            $modelo,
            $fornman,
            $descricao,
            $ccusto,
            $oficina,
            $dataagendamentoCompleta,
            $prevsaida,
            $dataentrada,
            $dataretirada,
            $tipopagamento,
            $reembolsoaprov,
            $valorreembolso,
            $valoroficina,
            $valordesconto,
            $valormaoobra,
            $valormaterial,
            $valortransp,
            $outrosvalor,
            $descontarcond,
            $datavencimento,
            $datapagamento,
            $formapagam,
            $condicaopag,
            $numparc,
            $valorparcela,
            $dataprimparc,
            $protocolo,
            $dataconclusao,
            $placaanterior,
            $observacao,
            $doc,
        ];

        $stmtIns = mysqli_prepare($conn, $sqlInsert);
        if ($stmtIns) {
            $types = str_repeat('s', count($values));
            $refs = [];
            $refs[] = & $types;
            for ($i = 0; $i < count($values); $i++) {
                $refs[] = & $values[$i];
            }
            call_user_func_array([$stmtIns, 'bind_param'], $refs);

            $okIns = mysqli_stmt_execute($stmtIns);
            if ($okIns) {
                $newId = mysqli_insert_id($conn);
                mysqli_stmt_close($stmtIns);
                header('Location: ../editar-manutencao.php?idtbmanprev=' . $newId . '&msg=' . rawurlencode('Prévia da manutenção criada. Confira os dados e confirme para finalizar.'));
                exit;
            }
            mysqli_stmt_close($stmtIns);
        }
        // se falhar inserção, retorna erro abaixo
    }

$sqlCompleto = "UPDATE bdautofrotas.tbmanprev
SET placa=?, tipo=?, hodometro=?, solicitante=?, status=?, etapa=?, dataocorrencia=?, modelo=?, fornman=?, descricao=?,
    ccusto=?, oficina=?, dataagendamento=?, prevsaida=?, dataentrada=?, dataretirada=?, tipopagamento=?, reembolsoaprov=?,
    valorreembolso=?, valoroficina=?, valordesconto=?, valormaoobra=?, valormaterial=?, valortransp=?, outrosvalor=?,
    descontarcond=?, datavencimento=?, datapagamento=?, formapagam=?, condicaopag=?, numparc=?, valorparcela=?, dataprimparc=?,
    protocolo=?, dataconclusao=?, placaanterior=?, observacao=?, doc=?, atualizadoem=NOW()
WHERE idtbmanprev=?";

$stmt = mysqli_prepare($conn, $sqlCompleto);
if ($stmt) {
    mysqli_stmt_bind_param(
        $stmt,
        'ssssssssssssssssssssssssssssssssssssssi',
        $placa,
        $tipo,
        $hodometro,
        $solicitante,
        $status,
        $etapa,
        $dataocorrencia,
        $modelo,
        $fornman,
        $descricao,
        $ccusto,
        $oficina,
        $dataagendamentoCompleta,
        $prevsaida,
        $dataentrada,
        $dataretirada,
        $tipopagamento,
        $reembolsoaprov,
        $valorreembolso,
        $valoroficina,
        $valordesconto,
        $valormaoobra,
        $valormaterial,
        $valortransp,
        $outrosvalor,
        $descontarcond,
        $datavencimento,
        $datapagamento,
        $formapagam,
        $condicaopag,
        $numparc,
        $valorparcela,
        $dataprimparc,
        $protocolo,
        $dataconclusao,
        $placaanterior,
        $observacao,
        $doc,
        $id
    );

    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    if ($ok) {
        redirectParaListagem('Manutenção finalizada com sucesso.');
    }
}

$sqlFallback = "UPDATE bdautofrotas.tbmanprev
SET tipo=?, hodometro=?, status=?, etapa=?, oficina=?, descricao=?, observacao=?, dataagendamento=?, dataretirada=?,
    valoroficina=?, valormaoobra=?, valormaterial=?, atualizadoem=NOW()
WHERE idtbmanprev=?";
$stmtFallback = mysqli_prepare($conn, $sqlFallback);
if ($stmtFallback) {
    mysqli_stmt_bind_param(
        $stmtFallback,
        'sssssssssdddi',
        $tipo,
        $hodometro,
        $status,
        $etapa,
        $oficina,
        $descricao,
        $observacao,
        $dataagendamentoCompleta,
        $dataretirada,
        $valoroficina,
        $valormaoobra,
        $valormaterial,
        $id
    );

    $okFallback = mysqli_stmt_execute($stmtFallback);
    mysqli_stmt_close($stmtFallback);
    if ($okFallback) {
        redirectParaListagem('Manutenção finalizada com sucesso.');
    }
}

redirectComMensagem($id, 'Erro ao atualizar manutenção.');