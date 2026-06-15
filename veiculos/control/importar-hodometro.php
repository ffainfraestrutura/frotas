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

$uploadDir = __DIR__ . '/../docs/veiculos/';

function responderImportacaoHodometro(string $mensagem, bool $sucesso = false): void
{
    $mensagemJs = json_encode($mensagem, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    $sucessoJs = $sucesso ? 'true' : 'false';

    echo "<script>
        alert({$mensagemJs});
        if ({$sucessoJs}) {
            window.location.href = '../listagem-veiculo.php';
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '../importar-hodometro.php';
        }
    </script>";
    exit;
}

function resolverAutoloadHodometro(): string
{
    $candidatos = [
        __DIR__ . '/../vendor2/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor2/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../vendor2/autoload.php',
        __DIR__ . '/../../../vendor/autoload.php',
    ];

    foreach ($candidatos as $autoload) {
        if (is_file($autoload)) {
            return $autoload;
        }
    }

    return '';
}

function limparValorHodometro($valor): string
{
    $valor = trim((string) $valor);

    if (strpos($valor, "'") === 0) {
        $valor = substr($valor, 1);
    }

    return trim($valor);
}

function normalizarPlacaHodometro($valor): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/', '', limparValorHodometro($valor)));
}

function normalizarPlacaBancoHodometro(string $expressao): string
{
    return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM({$expressao}), '-', ''), ' ', ''), '.', ''), '/', ''), CHAR(9), ''))";
}

function normalizarNumeroHodometro($valor): string
{
    $valor = limparValorHodometro($valor);

    if ($valor === '') {
        return '';
    }

    $valor = str_replace(['.', ' '], '', $valor);
    $valor = str_replace(',', '.', $valor);

    return is_numeric($valor) ? (string) (int) round((float) $valor) : '';
}

function registrarLogImportacaoHodometro(mysqli $conn, string $databaseName, string $matriculaAutor): void
{
    $sql = "
        INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, matricula, mat_autor, tipo, placa)
        VALUES (?, 'Atualizou hodometros em lote', '', ?, 'cadastro', '')
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
    responderImportacaoHodometro('Requisição inválida.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    responderImportacaoHodometro('Não foi possível conectar ao banco configurado.');
}

$autoload = resolverAutoloadHodometro();
if ($autoload === '') {
    responderImportacaoHodometro('Biblioteca PhpSpreadsheet não encontrada para processar a planilha.');
}
require_once $autoload;

if (empty($_FILES['arquivo']['name']) || !isset($_FILES['arquivo']['tmp_name'])) {
    responderImportacaoHodometro('Selecione uma planilha para importar.');
}

if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    responderImportacaoHodometro('Não foi possível receber o arquivo enviado.');
}

$extensao = strtolower((string) pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
if (!in_array($extensao, ['xls', 'xlsx'], true)) {
    responderImportacaoHodometro('Envie uma planilha nos formatos .xls ou .xlsx.');
}

if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    responderImportacaoHodometro('Biblioteca PhpSpreadsheet carregada sem a classe IOFactory.');
}

$matriculaAutor = trim((string) ($_POST['matr_autor'] ?? $_SESSION['matricula'] ?? $_SESSION['usuario'] ?? ''));
$agora = date('Y-m-d H:i:s');
$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['arquivo']['tmp_name']);
$linhas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

$atualizados = 0;
$ignorados = 0;
$naoEncontrados = 0;
$detalhesAtualizacoes = [];

foreach ($linhas as $linha) {
    $placa = normalizarPlacaHodometro($linha[0] ?? '');

    if ($placa === '' || in_array($placa, ['PLACA', 'VEICULO', 'VEICULOPLACA'], true)) {
        $ignorados++;
        continue;
    }

    $hodometro = normalizarNumeroHodometro($linha[1] ?? '');
    if ($hodometro === '') {
        $ignorados++;
        continue;
    }

    // ONDE PROCURA: Tabela tbveiculo, coluna placa
    $sqlBusca = "
        SELECT idtbveiculo
        FROM `{$databaseName}`.`tbveiculo`
        WHERE TRIM(placa) = ?
        LIMIT 1
    ";
    $stmtBusca = mysqli_prepare($conn, $sqlBusca);
    if (!$stmtBusca) {
        responderImportacaoHodometro('Não foi possível preparar a busca da placa ' . $placa . '.');
    }

    mysqli_stmt_bind_param($stmtBusca, 's', $placa);
    $executouBusca = mysqli_stmt_execute($stmtBusca);
    if (!$executouBusca) {
        $erroBusca = mysqli_stmt_error($stmtBusca);
        mysqli_stmt_close($stmtBusca);
        responderImportacaoHodometro('Não foi possível localizar a placa ' . $placa . ': ' . $erroBusca);
    }

    mysqli_stmt_store_result($stmtBusca);
    mysqli_stmt_bind_result($stmtBusca, $idtbveiculoEncontrado);
    $encontrouPlaca = mysqli_stmt_fetch($stmtBusca);
    mysqli_stmt_close($stmtBusca);

    if (!$encontrouPlaca) {
        $sqlBuscaNormalizada = "
            SELECT idtbveiculo
            FROM `{$databaseName}`.`tbveiculo`
            WHERE " . normalizarPlacaBancoHodometro('placa') . " = ?
            LIMIT 1
        ";
        $stmtBuscaNormalizada = mysqli_prepare($conn, $sqlBuscaNormalizada);
        if (!$stmtBuscaNormalizada) {
            responderImportacaoHodometro('Não foi possível preparar a busca normalizada da placa ' . $placa . '.');
        }

        mysqli_stmt_bind_param($stmtBuscaNormalizada, 's', $placa);
        $executouBuscaNormalizada = mysqli_stmt_execute($stmtBuscaNormalizada);
        if (!$executouBuscaNormalizada) {
            $erroBuscaNormalizada = mysqli_stmt_error($stmtBuscaNormalizada);
            mysqli_stmt_close($stmtBuscaNormalizada);
            responderImportacaoHodometro('Não foi possível localizar a placa ' . $placa . ': ' . $erroBuscaNormalizada);
        }

        mysqli_stmt_store_result($stmtBuscaNormalizada);
        mysqli_stmt_bind_result($stmtBuscaNormalizada, $idtbveiculoEncontrado);
        $encontrouPlaca = mysqli_stmt_fetch($stmtBuscaNormalizada);
        mysqli_stmt_close($stmtBuscaNormalizada);
    }

    if (!$encontrouPlaca) {
        $naoEncontrados++;
        continue;
    }

    $sql = "
        UPDATE `{$databaseName}`.`tbveiculo`
        SET hodometro = ?, datamovimentacao = ?
        WHERE UPPER(REPLACE(REPLACE(TRIM(placa), '-', ''), ' ', '')) = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        responderImportacaoHodometro('Não foi possível preparar a atualização da placa ' . $placa . '.');
    }

    mysqli_stmt_bind_param($stmt, 'sss', $hodometro, $agora, $placa);
    $executou = mysqli_stmt_execute($stmt);
    $erro = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$executou) {
        responderImportacaoHodometro('Não foi possível atualizar a placa ' . $placa . ': ' . $erro);
    }

    $atualizados++;
    $detalhesAtualizacoes[] = $placa . ' (#' . (string) $idtbveiculoEncontrado . ') → ' . number_format((int) $hodometro, 0, ',', '.');
}

registrarLogImportacaoHodometro($conn, $databaseName, $matriculaAutor);

// PASTA NECESSÁRIA: /docs/veiculos/
$avisoArquivo = '';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    $avisoArquivo = ' ⚠️ Aviso: A pasta "' . $uploadDir . '" não pôde ser criada. Verifique as permissões do servidor.';
} else {
    $dataArquivo = str_replace([' ', '-', ':'], '', $agora);
    $nomeArquivo = 'hodometros-' . $dataArquivo . '.' . $extensao;
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $uploadDir . $nomeArquivo)) {
        $avisoArquivo = ' ⚠️ Aviso: Não foi possível arquivar a planilha em "' . $uploadDir . '". Dados gravados no banco.';
    }
}

$detalhesTexto = $detalhesAtualizacoes !== [] ? "\n\nDetalhes:\n" . implode("\n", $detalhesAtualizacoes) : '';

responderImportacaoHodometro(
    "✓ Importado com sucesso."
    . "\n━━━━━━━━━━━━━━━━━━━━━━━━━"
    . "\nAtualizados: {$atualizados}"
    . "\nPlacas não encontradas: {$naoEncontrados}"
    . "\nLinhas ignoradas: {$ignorados}"
    . $detalhesTexto
    . ($avisoArquivo !== '' ? "\n\n" . $avisoArquivo : ''),
    true
);
