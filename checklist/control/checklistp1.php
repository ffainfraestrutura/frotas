<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($con instanceof mysqli) || preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
    http_response_code(405);
    exit('Requisição inválida.');
}

$valor = static fn(string $campo): string => trim((string) ($_POST[$campo] ?? ''));
$placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $valor('placa')) ?? '');
$data = $valor('datavistoria');
$hora = $valor('horavistoria');
$datavistoria = ($data !== '' ? $data : date('Y-m-d')) . ' ' . ($hora !== '' ? $hora : date('H:i:s'));

$camposFormulario = [
    'nome','matricula','cpf','cnh','categoriacnh','validadecnh','placa','modelo','anofabricacao','unidade','centrocusto',
    'tipo','vistoriador','matrvistoriador','datavistoria','estado','avaria','hodometro','niveltanque','observacao','documentacao','statusveic',
    'teto','tetoesp','tetoesq','tetodir','frente','capo','parabrisa','farolesq','faroldir','parachoque','grade',
    'latesq','paralamaesq','retrovesq','cxaresq','ptdiantesq','pttrasesq','latdir','paralamadir','retrovdir','cxardir','ptdiantdir','pttrasdir',
    'traseira','lantesq','lantdir','tmpmala','parachoquet','itinterno','painel','som','bancos','ilumint','tmpbag','retrovint','tapetes',
    'pneus','step','marcapneus','kitstep','calotas','bateria','safecar','limpint','limpext'
];
$dadosFormulario = [];
foreach ($camposFormulario as $campo) {
    $dadosFormulario[$campo] = $valor($campo);
}
$dadosFormulario['cpf'] = preg_replace('/\D/', '', $dadosFormulario['cpf']) ?? '';
$dadosFormulario['placa'] = $placa;
$dadosFormulario['datavistoria'] = $datavistoria;
$dadosFormulario['vistoriador'] = $dadosFormulario['vistoriador'] ?: (string) ($autofrota['usuario'] ?? '');
$dadosFormulario['matrvistoriador'] = $dadosFormulario['matrvistoriador'] ?: (string) ($autofrota['matricula'] ?? '');

$campos = array_keys($dadosFormulario);
$dados = array_values($dadosFormulario);
$campos[] = 'statusreg';
$dados[] = '0';

mysqli_begin_transaction($con);
try {
    $marcadores = implode(',', array_fill(0, count($campos), '?'));
    $sql = 'INSERT INTO `' . $databaseName . '`.`tbvistoria` (`' . implode('`,`', $campos) . "`) VALUES ($marcadores)";
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) throw new RuntimeException(mysqli_error($con));
    mysqli_stmt_bind_param($stmt, str_repeat('s', count($dados)), ...$dados);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
    $id = mysqli_insert_id($con);

    // Segue o fluxo legado: cria apenas o registro-base para o Passo 2.
    // O schema não possui DEFAULT nas colunas de foto, portanto todas precisam
    // receber string vazia até que cada upload do Passo 2 atualize sua coluna.
    $fotoVazia = '';
    $idVistoriaFotos = (string) $id;
    $stmtFotos = mysqli_prepare(
        $con,
        "INSERT INTO `{$databaseName}`.`tbvistoriafotos`
        (placa, frontal, traseira, direita, esquerda, bateria, selfie, cnh,
         extra1, extra2, extra3, extra4, extra5, datavistoriaf, idtbvistoria, painel)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmtFotos) throw new RuntimeException(mysqli_error($con));
    mysqli_stmt_bind_param(
        $stmtFotos,
        'ssssssssssssssss',
        $placa,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $fotoVazia,
        $datavistoria,
        $idVistoriaFotos,
        $fotoVazia
    );
    if (!mysqli_stmt_execute($stmtFotos)) throw new RuntimeException(mysqli_stmt_error($stmtFotos));
    mysqli_commit($con);

    $query = http_build_query(['placa'=>$placa, 'datavistoria'=>$data, 'matricula'=>$dadosFormulario['matricula'], 'idinserido'=>$id]);
    header('Location: ../checklistp2.php?' . $query);
    exit;
} catch (Throwable $erro) {
    mysqli_rollback($con);
    error_log('[checklistp1] ' . $erro->getMessage());
    http_response_code(500);
    echo 'Não foi possível salvar o checklist. Tente novamente.';
}