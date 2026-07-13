<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();
$conn = $GLOBALS['conn'] ?? null;
$databaseName = $GLOBALS['databaseName'] ?? 'bdautofrotas';
$databaseCorp = $GLOBALS['databaseCorp'] ?? 'bdcorp';

header('Content-Type: application/json; charset=utf-8');
if (!$conn instanceof mysqli) {
    echo json_encode(['erro' => 'Conexão indisponível'], JSON_UNESCAPED_UNICODE);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

$where = ['1=1'];
$types = '';
$params = [];
foreach ([['matricula', 'j.matricula_tecnico'], ['placa', 'j.placa']] as [$post, $col]) {
    $valor = trim((string) ($_POST[$post] ?? ''));
    if ($valor !== '') {
        $where[] = "$col LIKE ?";
        $types .= 's';
        $params[] = '%' . $valor . '%';
    }
}
$tipoAcao = trim((string) ($_POST['tipo_acao'] ?? ''));
if ($tipoAcao !== '') { $where[] = 'j.tipo_acao = ?'; $types .= 'i'; $params[] = (int) $tipoAcao; }
$dataInicio = trim((string) ($_POST['data_inicio'] ?? ''));
if ($dataInicio !== '') { $where[] = 'DATE(j.data_hora) >= ?'; $types .= 's'; $params[] = $dataInicio; }
$dataFim = trim((string) ($_POST['data_fim'] ?? ''));
if ($dataFim !== '') { $where[] = 'DATE(j.data_hora) <= ?'; $types .= 's'; $params[] = $dataFim; }

$sql = "SELECT j.id, j.matricula_autor, DATE_FORMAT(j.data_hora, '%d/%m/%Y %H:%i:%s') AS data_hora_formatada,
               j.tipo_acao, j.justificativa, j.matricula_tecnico, j.placa, j.valor, f.nome AS nome_autor
          FROM `{$databaseName}`.`tbjustificativa` j
     LEFT JOIN `{$databaseCorp}`.`tbfuncionario` f ON j.matricula_autor = f.matricula
         WHERE " . implode(' AND ', $where) . " ORDER BY j.data_hora DESC LIMIT 500";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) { echo json_encode(['erro' => mysqli_error($conn)], JSON_UNESCAPED_UNICODE); exit; }
if ($types !== '') { mysqli_stmt_bind_param($stmt, $types, ...$params); }
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dados = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
mysqli_stmt_close($stmt);
echo json_encode($dados, JSON_UNESCAPED_UNICODE);
