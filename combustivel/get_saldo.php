<?php
header('Content-Type: application/json');
session_start();
require_once '../control/conecta.php';

$matricula_tec = $_POST['matricula'];

$sql = "SELECT 
            COALESCE(
                h.valor_atual,
                s.saldo,
                s.kmorcsem,
                s.orcsemanal
            ) as saldo_atual,
            h.operacao as ultima_operacao,
            h.data as data_ultima_operacao,
            CASE 
                WHEN h.valor_atual IS NOT NULL THEN 'historico_combustivel'
                ELSE 'tbsaldo'
            END as fonte
        FROM 
            tbsaldo s
        LEFT JOIN (
            SELECT 
                valor_atual,
                operacao,
                data,
                matricula
            FROM 
                historico_combustivel 
            WHERE 
                matricula = ?
                AND data >= DATE_SUB(CURDATE(), INTERVAL (DAYOFWEEK(CURDATE()) - 2) DAY)
                AND data < DATE_ADD(DATE_SUB(CURDATE(), INTERVAL (DAYOFWEEK(CURDATE()) - 2) DAY), INTERVAL 7 DAY)
            ORDER BY 
                data DESC, id DESC 
            LIMIT 1
        ) h ON h.matricula = s.matricula
        WHERE 
            s.matricula = ?
        ORDER BY 
            s.data DESC 
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'ss', $matricula_tec, $matricula_tec);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dados = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// REMOVA O var_dump() E USE json_encode()
// var_dump($dados); <-- REMOVA ESTA LINHA

// Verifica se encontrou dados
if ($dados && isset($dados['saldo_atual'])) {
    $response = [
        'success' => true,
        'saldo_atual' => floatval($dados['saldo_atual']),
        'saldo_formatado' => 'R$ ' . number_format($dados['saldo_atual'], 2, ',', '.'),
        'ultima_operacao' => $dados['ultima_operacao'],
        'data_ultima_operacao' => $dados['data_ultima_operacao'],
        'fonte' => $dados['fonte']
    ];
} else {
    $response = [
        'success' => false,
        'message' => 'Saldo não encontrado para a matrícula: ' . $matricula_tec
    ];
}

// RETORNA COMO JSON
echo json_encode($response);

// $response = ['success' => false];

// $sql = "SELECT v.unidade, v.placa 
//         FROM tbfuncionario b
//         LEFT JOIN tbveiculo v ON b.matricula = v.matcond
//         WHERE b.matricula = ?";

// // Convertido para MySQLi
// $stmt = mysqli_prepare($conexao05, $sql);
// mysqli_stmt_bind_param($stmt, 's', $matricula_tec);
// mysqli_stmt_execute($stmt);
// $result = mysqli_stmt_get_result($stmt);
// $data = mysqli_fetch_assoc($result);
// mysqli_stmt_close($stmt);


// if ($data && !empty($data['placa'])) {
//     $unidade = $data['unidade'];
//     if($unidade != "RJ" && $unidade != "ES" && $unidade != ""){
//         $codempresa = 2;
//     }else{
//         $codempresa = 1;
//     }
//     $placa = $data['placa'];
//     $saldo_formatado = consultacartao($codempresa, $placa);

//     if ($saldo_formatado && $saldo_formatado != 'Sem cartão vinculado') {
//         $saldo_numerico = str_replace('R$ ', '', $saldo_formatado);
//         $saldo_numerico = str_replace('.', '', $saldo_numerico);
//         $saldo_numerico = str_replace(',', '.', $saldo_numerico);
//         $response = [
//             'success' => true,
//             'saldo_formatado' => $saldo_formatado,
//             'saldo_numerico' => floatval($saldo_numerico),
//             'placa' => $placa,
//             'codempresa' => $codempresa
//         ];
//     } else {
//         $response = ['success' => true, 'saldo_formatado' => 'R$ 0,00', 'saldo_numerico' => 0, 'placa' => $placa];
//     }
// } else {
//     $response = ['success' => true, 'saldo_formatado' => 'Sem veículo/placa', 'saldo_numerico' => 0];
// }

// echo json_encode($response);