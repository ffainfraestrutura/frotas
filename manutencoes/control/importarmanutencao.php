<?php
require_once __DIR__ . '/../../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/../../control/conecta.php';
exigirLogin();

setlocale(LC_ALL, 'pt_BR.utf8');
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: text/html; charset=utf-8');

$uploadDir = __DIR__ . '/../docs';

function responderImportacaoManutencao(string $mensagem, bool $sucesso = false): void
{
    $mensagemJs = json_encode($mensagem, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    $sucessoJs = $sucesso ? 'true' : 'false';

    echo "<script>
        alert({$mensagemJs});
        if ({$sucessoJs} && window.opener && !window.opener.closed) {
            window.opener.location.reload();
            window.close();
        } else if ({$sucessoJs}) {
            window.location.href = '../listagem-manutencao.php';
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '../importar-manutencao.php';
        }
    </script>";
    exit;
}

function resolverAutoloadManutencao(): string
{
    $candidatos = [
        __DIR__ . '/../vendor2/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor2/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor2/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
        __DIR__ . '/../../../servidor05/frotas/gestao/vendor2/autoload.php',
        __DIR__ . '/../../../servidor05/frotas/gestao/vendor/autoload.php',
        __DIR__ . '/../../../servidor05/frotas/vendor/autoload.php',
        __DIR__ . '/../../../../servidor05/frotas/gestao/vendor2/autoload.php',
        __DIR__ . '/../../../../servidor05/frotas/gestao/vendor/autoload.php',
        __DIR__ . '/../../../../servidor05/frotas/vendor/autoload.php',
    ];

    foreach ($candidatos as $autoload) {
        if (is_file($autoload)) {
            return $autoload;
        }
    }

    return '';
}

function limparValorManutencao($valor): string
{
    $valor = trim((string) $valor);

    if (strpos($valor, "'") === 0) {
        $valor = substr($valor, 1);
    }

    return trim($valor);
}

function normalizarTextoManutencao($valor): string
{
    return trim((string) limparValorManutencao($valor));
}

function normalizarNumeroManutencao($valor): string
{
    $valor = limparValorManutencao($valor);

    if ($valor === '') {
        return '';
    }

    $valor = str_replace(['R$', ' ', "\xc2\xa0"], '', $valor);

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return is_numeric($valor) ? $valor : limparValorManutencao($valor);
}

function normalizarInteiroManutencao($valor): string
{
    $valor = normalizarNumeroManutencao($valor);

    if ($valor === '') {
        return '';
    }

    return (string) (int) round((float) $valor);
}

function normalizarNumeroOpcionalManutencao($valor): ?string
{
    $numero = normalizarNumeroManutencao($valor);

    return $numero === '' ? null : $numero;
}

function normalizarInteiroOpcionalManutencao($valor): ?string
{
    $numero = normalizarInteiroManutencao($valor);

    return $numero === '' ? null : $numero;
}

function validarDataManutencao(string $dataFormatada): bool
{
    if ($dataFormatada === '' || strlen($dataFormatada) < 10) {
        return false;
    }
    
    // Extrai ano (posição 0-3 em 'Y-m-d')
    $ano = (int) substr($dataFormatada, 0, 4);
    
    // Valida se o ano está em um intervalo razoável (1900-2100)
    return $ano >= 1900 && $ano <= 2100;
}

function normalizarDataManutencao($valor, bool $comHora = false): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    if (is_numeric($valor)) {
        try {
            $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float) $valor);
            $data = new DateTime('@' . $timestamp);
            $resultado = $data->format($comHora ? 'Y-m-d H:i:s' : 'Y-m-d');
            
            // Valida o resultado
            if (!validarDataManutencao($resultado)) {
                return '';
            }
            
            return $resultado;
        } catch (Exception $e) {
            return '';
        }
    }

    $valor = limparValorManutencao($valor);
    if ($valor === '') {
        return '';
    }

    $formatos = $comHora
        ? ['d/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i:s', 'd-m-Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y', 'd-m-Y', 'Y-m-d']
        : ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd/m/y', 'd-m-y'];

    foreach ($formatos as $formato) {
        $data = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
        if ($data instanceof DateTimeImmutable) {
            $resultado = $data->format($comHora ? 'Y-m-d H:i:s' : 'Y-m-d');
            
            // Valida o resultado
            if (validarDataManutencao($resultado)) {
                return $resultado;
            }
        }
    }

    return '';
}

function normalizarDataOpcionalManutencao($valor, bool $comHora = false): ?string
{
    $data = normalizarDataManutencao($valor, $comHora);

    return $data === '' ? null : $data;
}

function normalizarBinarioManutencao($valor): string
{
    $valor = strtoupper(limparValorManutencao($valor));
    $valor = strtr($valor, ['Ã' => 'A', 'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Õ' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Í' => 'I']);

    if (in_array($valor, ['SIM', 'S', '1'], true)) {
        return '1';
    }

    if (in_array($valor, ['NAO', 'NÃO', 'N', '0'], true)) {
        return '0';
    }

    return limparValorManutencao($valor);
}

function normalizarBinarioOpcionalManutencao($valor): ?string
{
    $binario = normalizarBinarioManutencao($valor);

    return $binario === '' ? null : $binario;
}

function normalizarStatusManutencao($valor): string
{
    $valor = limparValorManutencao($valor);

    return strcasecmp($valor, 'CONCLUÍDO') === 0 ? 'CONCLUIDO' : $valor;
}

function normalizarProtocoloManutencao($valor): string
{
    $valor = limparValorManutencao($valor);

    return strpos($valor, "'") === 0 ? substr($valor, 1) : $valor;
}

function bindParamsManutencao(mysqli_stmt $stmt, string $types, array $params): bool
{
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }

    return mysqli_stmt_bind_param($stmt, $types, ...$refs);
}

function executarStmtManutencao(mysqli $conn, string $sql, string $types, array $params, string $mensagemErro): void
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        responderImportacaoManutencao($mensagemErro . ': ' . mysqli_error($conn));
    }

    if ($types !== '' && !bindParamsManutencao($stmt, $types, $params)) {
        $erro = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        responderImportacaoManutencao($mensagemErro . ': ' . $erro);
    }

    $executou = mysqli_stmt_execute($stmt);
    $erro = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$executou) {
        responderImportacaoManutencao($mensagemErro . ': ' . $erro);
    }
}

function buscarManutencaoExistente(mysqli $conn, string $databaseName, string $placa, string $tipo, string $dataAbertura): int
{
    $sql = "
        SELECT idtbmanprev
        FROM `{$databaseName}`.`tbmanprev`
        WHERE placa = ? AND tipo = ? AND DATE(data) = ?
        ORDER BY idtbmanprev DESC
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        responderImportacaoManutencao('Não foi possível preparar a verificação da manutenção da placa ' . $placa . '.');
    }

    mysqli_stmt_bind_param($stmt, 'sss', $placa, $tipo, $dataAbertura);
    if (!mysqli_stmt_execute($stmt)) {
        $erro = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        responderImportacaoManutencao('Não foi possível verificar a manutenção da placa ' . $placa . ': ' . $erro);
    }

    mysqli_stmt_store_result($stmt);
    mysqli_stmt_bind_result($stmt, $id);
    $encontrou = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    return $encontrou ? (int) $id : 0;
}

function registrarLogImportacaoManutencao(mysqli $conn, string $databaseName, string $matriculaAutor): void
{
    $sql = "
        INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, matricula, mat_autor, tipo, placa)
        VALUES (?, 'Importou man em lote', '', ?, 'cadastro', '')
    ";
    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return;
    }

    $agora = date('Y-m-d H:i:s');
    mysqli_stmt_bind_param($stmt, 'ss', $agora, $matriculaAutor);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderImportacaoManutencao('Requisição inválida.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    responderImportacaoManutencao('Não foi possível conectar ao banco bdautofrotas.');
}

$autoload = resolverAutoloadManutencao();
if ($autoload === '') {
    responderImportacaoManutencao('Biblioteca PhpSpreadsheet não encontrada para processar a planilha.');
}
require_once $autoload;

if (empty($_FILES['arquivo']['name']) || !isset($_FILES['arquivo']['tmp_name'])) {
    responderImportacaoManutencao('Selecione uma planilha para importar.');
}

if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    responderImportacaoManutencao('Não foi possível receber o arquivo enviado.');
}

$extensao = strtolower((string) pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
if (!in_array($extensao, ['xls', 'xlsx'], true)) {
    responderImportacaoManutencao('Envie uma planilha nos formatos .xls ou .xlsx.');
}

if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    responderImportacaoManutencao('Biblioteca PhpSpreadsheet carregada sem a classe IOFactory.');
}

$matriculaAutor = trim((string) ($_POST['matr_autor'] ?? $_SESSION['matricula'] ?? $_SESSION['usuario'] ?? ''));
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['arquivo']['tmp_name']);
$linhas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

$colunas = [
    'placa', 'data', 'tipo', 'hodometro', 'atualizadoem', 'solicitante', 'status', 'dataocorrencia',
    'modelo', 'Km', 'fornman', 'descricao', 'observacao', 'ccusto', 'oficina', 'dataagendamento', 'prevsaida',
    'dataentrada', 'dataretirada', 'tipopagamento', 'reembolsoaprov', 'valorreembolso', 'valoroficina',
    'valordesconto', 'valormaoobra', 'valormaterial', 'valortransp', 'outrosvalor', 'descontarcond',
    'datavencimento', 'datapagamento', 'formapagam', 'condicaopag', 'numparc', 'valorparcela',
    'dataprimparc', 'protocolo', 'dataconclusao', 'placaanterior',
];

$inseridos = 0;
$atualizados = 0;
$ignorados = 0;
$ignoradosPorDataInvalida = 0;
$ignoradosPorAgendamentoInvalido = 0;
$totalLinhas = 0;
$placasUnicas = [];

foreach ($linhas as $linha) {
    $totalLinhas++;
    $tipo = normalizarTextoManutencao($linha[0] ?? '');

    if ($tipo === '' || strcasecmp($tipo, 'Tipo Solicitação') === 0 || strcasecmp($tipo, 'Tipo Solicitacao') === 0) {
        $ignorados++;
        continue;
    }

    $placa = strtoupper(normalizarTextoManutencao($linha[1] ?? ''));
    $dataAbertura = normalizarDataManutencao($linha[2] ?? '');

    if ($placa === '') {
        $ignorados++;
        continue;
    }
    
    if ($dataAbertura === '') {
        $ignoradosPorDataInvalida++;
        $ignorados++;
        continue;
    }

    if ($placa !== '') {
        $placasUnicas[$placa] = true;
    }

    $dataAgendamento = normalizarDataManutencao($linha[13] ?? '');
    if ($dataAgendamento === '') {
        $ignoradosPorAgendamentoInvalido++;
        $ignorados++;
        continue;
    }

    $km = normalizarInteiroManutencao($linha[8] ?? '');
    $descricao = normalizarTextoManutencao($linha[10] ?? '');
    $registro = [
        'placa' => $placa,
        'data' => $dataAbertura,
        'tipo' => $tipo,
        'hodometro' => $km,
        'atualizadoem' => normalizarDataOpcionalManutencao($linha[3] ?? '', true),
        'solicitante' => normalizarTextoManutencao($linha[4] ?? ''),
        'status' => normalizarStatusManutencao($linha[5] ?? ''),
        'dataocorrencia' => normalizarDataOpcionalManutencao($linha[6] ?? ''),
        'modelo' => normalizarTextoManutencao($linha[7] ?? ''),
        'Km' => $km,
        'fornman' => normalizarTextoManutencao($linha[9] ?? ''),
        'descricao' => $descricao,
        'observacao' => $descricao,
        'ccusto' => normalizarTextoManutencao($linha[11] ?? ''),
        'oficina' => normalizarTextoManutencao($linha[12] ?? ''),
        'dataagendamento' => $dataAgendamento,
        'prevsaida' => normalizarDataOpcionalManutencao($linha[14] ?? ''),
        'dataentrada' => normalizarDataOpcionalManutencao($linha[15] ?? ''),
        'dataretirada' => normalizarDataOpcionalManutencao($linha[16] ?? ''),
        'tipopagamento' => normalizarTextoManutencao($linha[17] ?? ''),
        'reembolsoaprov' => normalizarBinarioOpcionalManutencao($linha[18] ?? ''),
        'valorreembolso' => normalizarNumeroOpcionalManutencao($linha[19] ?? ''),
        'valoroficina' => normalizarNumeroOpcionalManutencao($linha[20] ?? ''),
        'valordesconto' => normalizarNumeroOpcionalManutencao($linha[21] ?? ''),
        'valormaoobra' => normalizarNumeroOpcionalManutencao($linha[22] ?? ''),
        'valormaterial' => normalizarNumeroOpcionalManutencao($linha[23] ?? ''),
        'valortransp' => normalizarNumeroOpcionalManutencao($linha[24] ?? ''),
        'outrosvalor' => normalizarNumeroOpcionalManutencao($linha[25] ?? ''),
        'descontarcond' => normalizarNumeroOpcionalManutencao($linha[26] ?? ''),
        'datavencimento' => normalizarDataOpcionalManutencao($linha[27] ?? ''),
        'datapagamento' => normalizarDataOpcionalManutencao($linha[28] ?? ''),
        'formapagam' => normalizarTextoManutencao($linha[29] ?? ''),
        'condicaopag' => normalizarTextoManutencao($linha[30] ?? ''),
        'numparc' => normalizarInteiroOpcionalManutencao($linha[31] ?? ''),
        'valorparcela' => normalizarNumeroOpcionalManutencao($linha[32] ?? ''),
        'dataprimparc' => normalizarDataOpcionalManutencao($linha[33] ?? ''),
        'protocolo' => normalizarProtocoloManutencao($linha[34] ?? ''),
        'dataconclusao' => normalizarDataOpcionalManutencao($linha[35] ?? ''),
        'placaanterior' => strtoupper(normalizarTextoManutencao($linha[36] ?? '')),
    ];

    $idExistente = buscarManutencaoExistente($conn, $databaseName, $placa, $tipo, $dataAbertura);

    if ($idExistente > 0) {
        $sets = implode(', ', array_map(static fn($coluna) => "`{$coluna}` = ?", $colunas));
        $sql = "UPDATE `{$databaseName}`.`tbmanprev` SET {$sets} WHERE idtbmanprev = ?";
        $params = array_values($registro);
        $params[] = $idExistente;
        executarStmtManutencao($conn, $sql, str_repeat('s', count($registro)) . 'i', $params, 'Não foi possível atualizar a manutenção da placa ' . $placa);
        $atualizados++;
    } else {
        $campos = implode(', ', array_map(static fn($coluna) => "`{$coluna}`", $colunas));
        $placeholders = implode(', ', array_fill(0, count($colunas), '?'));
        $sql = "INSERT INTO `{$databaseName}`.`tbmanprev` ({$campos}) VALUES ({$placeholders})";
        executarStmtManutencao($conn, $sql, str_repeat('s', count($registro)), array_values($registro), 'Não foi possível importar a manutenção da placa ' . $placa);
        $inseridos++;
    }
}

registrarLogImportacaoManutencao($conn, $databaseName, $matriculaAutor);

$avisoArquivo = '';
$caminhoArquivo = '';
$diagnosticoPermissoes = '';

// Diagnóstico do diretório de upload
$pastaUploadParent = dirname($uploadDir);
$diagnosticoPermissoes .= "\n--- DIAGNÓSTICO DE DIRETÓRIO ---\n";
$diagnosticoPermissoes .= "Pasta de destino: {$uploadDir}\n";
$diagnosticoPermissoes .= "Pasta pai: {$pastaUploadParent}\n";
$diagnosticoPermissoes .= "Existe pasta de destino? " . (is_dir($uploadDir) ? 'SIM' : 'NÃO') . "\n";
$diagnosticoPermissoes .= "Existe pasta pai? " . (is_dir($pastaUploadParent) ? 'SIM' : 'NÃO') . "\n";

if (is_dir($pastaUploadParent)) {
    $permissoesParent = substr(sprintf('%o', fileperms($pastaUploadParent)), -4);
    $diagnosticoPermissoes .= "Permissões da pasta pai: {$permissoesParent}\n";
    $diagnosticoPermissoes .= "Pasta pai é gravável? " . (is_writable($pastaUploadParent) ? 'SIM' : 'NÃO') . "\n";
}

if (is_dir($uploadDir)) {
    $permissoes = substr(sprintf('%o', fileperms($uploadDir)), -4);
    $diagnosticoPermissoes .= "Permissões da pasta: {$permissoes}\n";
    $diagnosticoPermissoes .= "Pasta é gravável? " . (is_writable($uploadDir) ? 'SIM' : 'NÃO') . "\n";
}

// Tenta criar/acessar diretório
if (!is_dir($uploadDir)) {
    $tentouCriar = @mkdir($uploadDir, 0775, true);
    $diagnosticoPermissoes .= "Tentou criar? SIM\n";
    $diagnosticoPermissoes .= "Sucesso em criar? " . ($tentouCriar ? 'SIM' : 'NÃO') . "\n";
    
    if (!$tentouCriar) {
        $avisoArquivo = "\n⚠️ ERRO: Não foi possível criar a pasta de upload.\n{$diagnosticoPermissoes}";
    }
} else {
    $diagnosticoPermissoes .= "Pasta já existe!\n";
}

// Se conseguiu preparar a pasta, tenta salvar o arquivo
if (is_dir($uploadDir) && is_writable($uploadDir)) {
    $dataArquivo = str_replace([' ', '-', ':'], '', date('Y-m-d H:i:s'));
    $nomeArquivo = 'manutencao-' . $dataArquivo . '.' . $extensao;
    $caminhoCompleto = $uploadDir . '/' . $nomeArquivo;
    
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminhoCompleto)) {
        $avisoArquivo = "\n⚠️ ERRO: Arquivo foi processado mas não foi possível salvar em:\n{$caminhoCompleto}\n{$diagnosticoPermissoes}";
    } else {
        $caminhoArquivo = "\n✓ Arquivo salvo em: {$uploadDir}/{$nomeArquivo}";
    }
} elseif (!$avisoArquivo) {
    $avisoArquivo = "\n⚠️ ERRO: Pasta de upload não existe ou não tem permissão de escrita.\n{$diagnosticoPermissoes}";
}

$dataProcessamento = date('d/m/Y H:i:s');
$totalPlacas = count($placasUnicas);
$linhasProcessadas = $inseridos + $atualizados;

$avisoData = $ignoradosPorDataInvalida > 0 
    ? "\nAVISO: {$ignoradosPorDataInvalida} linhas ignoradas por data inválida. Verifique o formato dd/mm/yyyy na coluna C." 
    : '';

$avisoAgendamento = $ignoradosPorAgendamentoInvalido > 0
    ? "\nAVISO: {$ignoradosPorAgendamentoInvalido} linhas ignoradas por data de agendamento inválida. Verifique o formato dd/mm/yyyy na coluna N."
    : '';

responderImportacaoManutencao(
    "Importado com sucesso em {$dataProcessamento}.\n" .
    "Total de linhas enviadas: {$totalLinhas}\n" .
    "Total de placas: {$totalPlacas}\n" .
    "Inseridos: {$inseridos}\n" .
    "Atualizados: {$atualizados}\n" .
    "Linhas ignoradas: {$ignorados}\n" . 
    $avisoData .
    $avisoAgendamento .
    $avisoArquivo,
    true
);
