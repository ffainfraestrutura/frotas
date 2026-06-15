<?php
require_once __DIR__ . '/../auth.php';

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

require_once __DIR__ . '/conecta.php';
exigirLogin();

setlocale(LC_ALL, 'pt_BR.utf8');
date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: text/html; charset=utf-8');

$uploadDir = __DIR__ . '/../docs/condutor/';

function responderImportacaoCnh(string $mensagem, bool $sucesso = false): void
{
    $mensagemJs = json_encode($mensagem, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    $sucessoJs = $sucesso ? 'true' : 'false';

    echo "<script>
        alert({$mensagemJs});
        if ({$sucessoJs} && window.opener && !window.opener.closed) {
            window.opener.location.reload();
            window.close();
        } else if ({$sucessoJs}) {
            window.location.href = '../listagemcnh.php';
        } else if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '../importarcnh.php';
        }
    </script>";
    exit;
}

function resolverAutoloadPhpSpreadsheet(): string
{
    $candidatos = [
        __DIR__ . '/../vendor2/autoload.php',
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor2/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../../../servidor05/frotas/gestao/vendor2/autoload.php',
        __DIR__ . '/../../../servidor05/frotas/gestao/vendor/autoload.php',
        __DIR__ . '/../../../servidor05/frotas/vendor/autoload.php',
    ];

    foreach ($candidatos as $autoload) {
        if (is_file($autoload)) {
            return $autoload;
        }
    }

    return '';
}

function limparValorCnh($valor): string
{
    $valor = trim((string) $valor);

    if (strpos($valor, "'") === 0) {
        $valor = substr($valor, 1);
    }

    return trim($valor);
}

function normalizarDataCnh($valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    if (is_numeric($valor)) {
        $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float) $valor);
        return date('Y-m-d', $timestamp);
    }

    $valor = limparValorCnh($valor);

    if ($valor === '') {
        return '';
    }

    $formatos = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd/m/y', 'd-m-y'];
    foreach ($formatos as $formato) {
        $data = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
        if ($data instanceof DateTimeImmutable) {
            return $data->format('Y-m-d');
        }
    }

    return $valor;
}

function normalizarSuspensaCnh($valor): int
{
    $valor = strtoupper(trim((string) $valor));
    $valor = strtr($valor, ['Ã' => 'A', 'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Õ' => 'O', 'Ó' => 'O', 'Ô' => 'O']);

    return in_array($valor, ['SIM', 'S', '1'], true) ? 1 : 0;
}

function registrarLogImportacaoCnh(mysqli $conn, string $databaseName, string $matriculaAutor): void
{
    $sql = "
        INSERT INTO `{$databaseName}`.`tblog` (data_e_hora, acao, matricula, mat_autor, tipo, placa)
        VALUES (?, 'Importou cnh em lote', '', ?, 'cadastro', '')
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
    responderImportacaoCnh('Requisição inválida.');
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    responderImportacaoCnh('Não foi possível conectar ao banco configurado.');
}

$autoload = resolverAutoloadPhpSpreadsheet();
if ($autoload === '') {
    responderImportacaoCnh('Biblioteca PhpSpreadsheet não encontrada para processar a planilha.');
}
require_once $autoload;

if (empty($_FILES['arquivo']['name']) || !isset($_FILES['arquivo']['tmp_name'])) {
    responderImportacaoCnh('Selecione uma planilha para importar.');
}

if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    responderImportacaoCnh('Não foi possível receber o arquivo enviado.');
}

$extensao = strtolower((string) pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
if (!in_array($extensao, ['xls', 'xlsx'], true)) {
    responderImportacaoCnh('Envie uma planilha nos formatos .xls ou .xlsx.');
}

if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
    responderImportacaoCnh('Biblioteca PhpSpreadsheet carregada sem a classe IOFactory.');
}

$matriculaAutor = trim((string) ($_POST['matr_autor'] ?? $_SESSION['matricula'] ?? $_SESSION['usuario'] ?? ''));

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['arquivo']['tmp_name']);
$linhas = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

$inseridos = 0;
$atualizados = 0;
$ignorados = 0;

foreach ($linhas as $indice => $linha) {
    $matriculaCondutor = limparValorCnh($linha[0] ?? '');

    if ($matriculaCondutor === '' || strcasecmp($matriculaCondutor, 'Matrícula') === 0 || strcasecmp($matriculaCondutor, 'Matricula') === 0) {
        $ignorados++;
        continue;
    }

    $numCnh = limparValorCnh($linha[1] ?? '');
    $validadeCnh = normalizarDataCnh($linha[2] ?? '');
    $ufCnh = strtoupper(limparValorCnh($linha[3] ?? ''));
    $categoria = strtoupper(limparValorCnh($linha[4] ?? ''));
    $pontos = (int) preg_replace('/\D/', '', limparValorCnh($linha[5] ?? '0'));
    $consulta = normalizarDataCnh($linha[6] ?? '');
    $suspensa = normalizarSuspensaCnh($linha[7] ?? '');

    $sqlExiste = "SELECT 1 FROM `{$databaseName}`.`tbcnh` WHERE matricula = ? LIMIT 1";
    $stmtExiste = mysqli_prepare($conn, $sqlExiste);
    if (!$stmtExiste) {
        responderImportacaoCnh('Não foi possível verificar a CNH da matrícula ' . $matriculaCondutor . '.');
    }

    mysqli_stmt_bind_param($stmtExiste, 's', $matriculaCondutor);
    mysqli_stmt_execute($stmtExiste);
    $resultadoExiste = mysqli_stmt_get_result($stmtExiste);
    $jaExiste = $resultadoExiste && mysqli_num_rows($resultadoExiste) > 0;
    if ($resultadoExiste) {
        mysqli_free_result($resultadoExiste);
    }
    mysqli_stmt_close($stmtExiste);

    if ($jaExiste) {
        $sqlAtualizar = "
            UPDATE `{$databaseName}`.`tbcnh`
            SET numcnh = ?, validade = ?, uf = ?, categoria = ?, pontos = ?, suspensa = ?, consulta = ?
            WHERE matricula = ?
        ";
        $stmtAtualizar = mysqli_prepare($conn, $sqlAtualizar);
        if (!$stmtAtualizar) {
            responderImportacaoCnh('Não foi possível preparar a atualização da matrícula ' . $matriculaCondutor . '.');
        }

        mysqli_stmt_bind_param($stmtAtualizar, 'ssssiiss', $numCnh, $validadeCnh, $ufCnh, $categoria, $pontos, $suspensa, $consulta, $matriculaCondutor);
        $executou = mysqli_stmt_execute($stmtAtualizar);
        $erro = mysqli_stmt_error($stmtAtualizar);
        mysqli_stmt_close($stmtAtualizar);

        if (!$executou) {
            responderImportacaoCnh('Não foi possível atualizar a matrícula ' . $matriculaCondutor . ': ' . $erro);
        }

        $atualizados++;
        continue;
    }

    $docVazio = '';
    $sqlInserir = "
        INSERT INTO `{$databaseName}`.`tbcnh` (
            numcnh, validade, uf, categoria, matricula, pontos, consulta, suspensa,
            doc1, doc2, politicauso, termocombust, contratoagregamento, termorescisao, ultrecibo
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmtInserir = mysqli_prepare($conn, $sqlInserir);
    if (!$stmtInserir) {
        responderImportacaoCnh('Não foi possível preparar a inclusão da matrícula ' . $matriculaCondutor . '.');
    }

    mysqli_stmt_bind_param(
        $stmtInserir,
        'sssssisisssssss',
        $numCnh,
        $validadeCnh,
        $ufCnh,
        $categoria,
        $matriculaCondutor,
        $pontos,
        $consulta,
        $suspensa,
        $docVazio,
        $docVazio,
        $docVazio,
        $docVazio,
        $docVazio,
        $docVazio,
        $docVazio
    );
    $executou = mysqli_stmt_execute($stmtInserir);
    $erro = mysqli_stmt_error($stmtInserir);
    mysqli_stmt_close($stmtInserir);

    if (!$executou) {
        responderImportacaoCnh('Não foi possível importar a matrícula ' . $matriculaCondutor . ': ' . $erro);
    }

    $inseridos++;
}

registrarLogImportacaoCnh($conn, $databaseName, $matriculaAutor);

$avisoArquivo = '';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    $avisoArquivo = ' Não foi possível criar a pasta para arquivar a planilha.';
} else {
    $dataArquivo = str_replace([' ', '-', ':'], '', date('Y-m-d H:i:s'));
    $nomeArquivo = 'cnh-' . $dataArquivo . '.' . $extensao;
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $uploadDir . $nomeArquivo)) {
        $avisoArquivo = ' Os dados foram gravados, mas não foi possível arquivar a planilha enviada.';
    }
}

responderImportacaoCnh(
    "Importado com sucesso. Inseridos: {$inseridos}. Atualizados: {$atualizados}. Linhas ignoradas: {$ignorados}." . $avisoArquivo,
    true
);
