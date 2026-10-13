<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../control/conecta.php';

// Pega JSON
$input = json_decode(file_get_contents("php://input"), true);

$placa = $input['placa'] ?? null;
$faixa_km = $input['faixa_km'] ?? null;
$data = $input['data'] ?? null;
$perfil = $input['perfil'] ?? null; // Espera receber 2 ou 4

// Validação básica
if (!$placa || !$faixa_km || !$data || !in_array($perfil, [2, 4])) {
    echo json_encode([
        "success" => false,
        "message" => "Dados inválidos ou perfil não informado"
    ]);
    exit;
}

// Sanitização
$placa = mysqli_real_escape_string($conn, $placa);
$faixa_km = mysqli_real_escape_string($conn, $faixa_km);
$data = mysqli_real_escape_string($conn, $data);

// Define qual coluna será alterada com base no perfil
// Perfil 2 = data_agendada_gerencia | Perfil 4 = data_agendada_frota
$coluna_data = ($perfil == 2) ? 'data_agendada_gerencia' : 'data_agendada_frota';

// Verifica se já existe registro
$sql_check = "
    SELECT id 
    FROM tb_agendamento_manutencao 
    WHERE placa = '$placa' 
    AND faixa_km = '$faixa_km'
";

$result_check = mysqli_query($conn, $sql_check);

if (mysqli_num_rows($result_check) > 0) {
    // UPDATE dinâmico baseado no perfil
    $sql = "
        UPDATE tb_agendamento_manutencao 
        SET $coluna_data = '$data'
        WHERE placa = '$placa' 
        AND faixa_km = '$faixa_km'
    ";
} else {
    // INSERT dinâmico baseado no perfil
    $sql = "
        INSERT INTO tb_agendamento_manutencao 
        (placa, faixa_km, $coluna_data)
        VALUES ('$placa', '$faixa_km', '$data')
    ";
}

// Executa
if (mysqli_query($conn, $sql)) {
    echo json_encode([
        "success" => true,
        "message" => "Data salva com sucesso"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Erro ao salvar",
        "erro_sql" => mysqli_error($conn)
    ]);
}
