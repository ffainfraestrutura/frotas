<?php
// control/remanejarsaldo.php
date_default_timezone_set('America/Sao_Paulo');
session_start();
require_once 'conecta.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['matricula'])) {
    header('Location: ../combustivel/remanejamento/index.php?error=Usuário não logado');
    exit;
}

// Pega os dados do formulário
$matricula_autor = $_POST['matricula_autor'] ?? '';
$matricula_origem = $_POST['matricula_origem'] ?? '';
$matricula_destino = $_POST['matricula_destino'] ?? '';
$valor = str_replace(',', '.', str_replace('.', '', $_POST['valor'] ?? '0'));
$valor = floatval($valor);

// Validações
if (empty($matricula_autor) || empty($matricula_origem) || empty($matricula_destino) || $valor <= 0) {
    header('Location: ../combustivel/remanejamento/index.php?error=Dados inválidos');
    exit;
}

if ($matricula_origem == $matricula_destino) {
    header('Location: ../combustivel/remanejamento/index.php?error=Origem e destino não podem ser iguais');
    exit;
}

// Função para buscar o saldo atual e KM projetado
function getDadosAtuais($conn, $matricula)
{
    // Busca no histórico da semana atual
    $sql = "SELECT valor_atual as saldo 
            FROM historico_combustivel 
            WHERE matricula = ? 
            ORDER BY data DESC, id DESC 
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $historico = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($historico && $historico['saldo'] !== null) {
        $saldo = floatval($historico['saldo']);
    } else {
        // Se não encontrou no histórico, busca no tbsaldo
        $sql = "SELECT saldo_real_calculado as saldo 
                FROM tbsaldo 
                WHERE matricula = ? 
                ORDER BY data DESC 
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $matricula);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $saldo_data = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        $saldo = $saldo_data ? floatval($saldo_data['saldo']) : 0;
    }

    // Busca o KM projetado atual no tbsaldo
    $sql_km = "SELECT kmorcsem 
               FROM tbsaldo 
               WHERE matricula = ? 
               ORDER BY data DESC 
               LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql_km);
    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $km_data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $kmorcsem = $km_data ? floatval($km_data['kmorcsem']) : 0;

    return [
        'saldo' => $saldo,
        'kmorcsem' => $kmorcsem
    ];
}

// Busca dados atuais de origem e destino
$dados_origem = getDadosAtuais($conn, $matricula_origem);
$dados_destino = getDadosAtuais($conn, $matricula_destino);

$saldo_atual_origem = $dados_origem['saldo'];
$saldo_atual_destino = $dados_destino['saldo'];
$kmorcsem_origem = $dados_origem['kmorcsem'];
$kmorcsem_destino = $dados_destino['kmorcsem'];

// Verifica se a origem tem saldo suficiente
if ($saldo_atual_origem < $valor) {
    header('Location: ../combustivel/remanejamento/index.php?error=Saldo insuficiente na origem (R$ ' . number_format($saldo_atual_origem, 2, ',', '.') . ')');
    exit;
}

// Calcula novos saldos
$novo_saldo_origem = $saldo_atual_origem - $valor;
$novo_saldo_destino = $saldo_atual_destino + $valor;

// ==============================================
// REGRA DE 3 PARA CALCULAR NOVO KM PROJETADO
// ==============================================
// Fórmula: (saldo_atual * kmorcsem) / novo_saldo = novo_kmorcsem
// Ou seja: saldo_real_calculado está para kmorcsem assim como novo_saldo está para novo_kmorcsem

// Para ORIGEM (diminuiu o saldo, então o KM projetado diminui proporcionalmente)
if ($saldo_atual_origem > 0 && $kmorcsem_origem > 0) {
    $novo_kmorcsem_origem = ($novo_saldo_origem * $kmorcsem_origem) / $saldo_atual_origem;
} else {
    $novo_kmorcsem_origem = 0;
}

// Para DESTINO (aumentou o saldo, então o KM projetado aumenta proporcionalmente)
if ($saldo_atual_destino > 0 && $kmorcsem_destino > 0) {
    $novo_kmorcsem_destino = ($novo_saldo_destino * $kmorcsem_destino) / $saldo_atual_destino;
} else {
    // Se destino não tinha saldo, o novo KM projetado é baseado no valor transferido
    // Usamos uma proporção base: 1 real = 1 km (ou outro valor que fizer sentido)
    // Ajuste conforme sua necessidade
    $novo_kmorcsem_destino = $kmorcsem_destino + ($valor * 1); // 1 real = 1 km
}

// Arredonda para 2 casas decimais
$novo_kmorcsem_origem = round($novo_kmorcsem_origem, 2);
$novo_kmorcsem_destino = round($novo_kmorcsem_destino, 2);

// Inicia transação
mysqli_begin_transaction($conn);

try {
    // 1. INSERE REGISTRO PARA ORIGEM (RETIRADA)
    $sql_origem = "INSERT INTO historico_combustivel 
                    (valor, matricula, operacao, matricula_autor, valor_anterior, valor_atual, acao, data) 
                    VALUES (?, ?, 'retirada', ?, ?, ?, 'remanejamento', NOW())";

    $stmt = mysqli_prepare($conn, $sql_origem);
    mysqli_stmt_bind_param($stmt, 'dssdd', $valor, $matricula_origem, $matricula_autor, $saldo_atual_origem, $novo_saldo_origem);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 2. INSERE REGISTRO PARA DESTINO (ADIÇÃO)
    $sql_destino = "INSERT INTO historico_combustivel 
                    (valor, matricula, operacao, matricula_autor, valor_anterior, valor_atual, acao, data) 
                    VALUES (?, ?, 'adicao', ?, ?, ?, 'remanejamento', NOW())";

    $stmt = mysqli_prepare($conn, $sql_destino);
    mysqli_stmt_bind_param($stmt, 'dssdd', $valor, $matricula_destino, $matricula_autor, $saldo_atual_destino, $novo_saldo_destino);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 3. ATUALIZA O tbsaldo COM O NOVO SALDO E NOVO KM PROJETADO
    // Atualiza origem
    $sql_update_origem = "UPDATE tbsaldo SET 
                            totalextra = totalextra - ?,
                            kmorcsem = ?
                          WHERE matricula = ?";
    $stmt = mysqli_prepare($conn, $sql_update_origem);
    mysqli_stmt_bind_param($stmt, 'dds', $valor, $novo_kmorcsem_origem, $matricula_origem);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Atualiza destino
    $sql_update_destino = "UPDATE tbsaldo SET 
                            totalextra = totalextra + ?,
                            kmorcsem = ?
                          WHERE matricula = ?";
    $stmt = mysqli_prepare($conn, $sql_update_destino);
    mysqli_stmt_bind_param($stmt, 'dds', $valor, $novo_kmorcsem_destino, $matricula_destino);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Confirma transação
    mysqli_commit($conn);

    header('Location: ../combustivel/remanejamento/index.php?success=Remanejamento realizado com sucesso!');
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    header('Location: ../combustivel/remanejamento/index.php?error=' . urlencode('Erro ao realizar remanejamento: ' . $e->getMessage()));
    exit;
}
?>