<?php
// control/retirar_saldo.php
date_default_timezone_set('America/Sao_Paulo');
session_start();
require_once 'conecta.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['matricula'])) {
    header('Location: ../combustivel/retirada/index.php?error=Usuário não logado');
    exit;
}

// Verifica permissão (não pode ser técnico)
if ($_SESSION['perfil'] == 0) {
    header('Location: ../combustivel/retirada/index.php?error=Perfil sem permissão para retirada de saldo.');
    exit;
}

// Pega os dados do formulário
$matricula_tecnico = $_POST['matricula_tecnico'] ?? '';
$valor = str_replace(',', '.', str_replace('.', '', $_POST['valor_retirada'] ?? '0'));
$valor = floatval($valor);
$matricula_autor = $_SESSION['matricula'];
$perfil_autor = $_SESSION['perfil'];

// Validações
if (empty($matricula_tecnico) || $valor <= 0) {
    header('Location: ../combustivel/retirada/index.php?error=Dados inválidos');
    exit;
}

// Função para buscar o saldo atual e KM projetado
function getDadosAtuais($conn, $matricula)
{
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

// Busca dados atuais do técnico
$dados_tecnico = getDadosAtuais($conn, $matricula_tecnico);
$saldo_atual = $dados_tecnico['saldo'];
$kmorcsem_atual = $dados_tecnico['kmorcsem'];

// Verifica se o técnico tem saldo suficiente
if ($saldo_atual < $valor) {
    header('Location: ../combustivel/retirada/index.php?error=Saldo insuficiente (R$ ' . number_format($saldo_atual, 2, ',', '.') . ')');
    exit;
}

// Calcula novo saldo do técnico
$novo_saldo = $saldo_atual - $valor;

// Calcula novo KM projetado (regra de 3)
if ($saldo_atual > 0 && $kmorcsem_atual > 0) {
    $novo_kmorcsem = ($novo_saldo * $kmorcsem_atual) / $saldo_atual;
} else {
    $novo_kmorcsem = 0;
}
$novo_kmorcsem = round($novo_kmorcsem, 2);

// ==============================================
// BUSCA O RESPONSÁVEL BASEADO NO PERFIL DO AUTOR
// ==============================================
$matricula_responsavel = '';
$id_responsavel = 0;
$tipo_responsavel = '';
$saldo_atual_responsavel = 0;

switch ($perfil_autor) {
    case 2: // Coordenador
        $sql = "SELECT matricula, idtbcoordenador as id, valor as orcrecebido FROM bdcorp.tbcoord WHERE matricula = ?";
        $tipo_responsavel = 'coordenador';
        $tabela = 'bdcorp.tbcoord';
        $campo_id = 'idtbcoordenador';
        break;

    case 3: // Gerente
        $sql = "SELECT matricula, idtbgerente as id, valor as orcrecebido FROM bdcorp.tbgerente WHERE matricula = ?";
        $tipo_responsavel = 'gerente';
        $tabela = 'bdcorp.tbgerente';
        $campo_id = 'idtbgerente';
        break;

    case 4: // Frota - vai para coordenador
        $sql = "SELECT matricula, idtbcoordenador as id, valor as orcrecebido FROM bdcorp.tbcoord WHERE matricula = ?";
        $tipo_responsavel = 'coordenador';
        $tabela = 'bdcorp.tbcoord';
        $campo_id = 'idtbcoordenador';
        break;

    case 10: // Diretor
        $sql = "SELECT matricula, id as id, valor as orcrecebido FROM bdcorp.tbdiretor WHERE matricula = ?";
        $tipo_responsavel = 'diretor';
        $tabela = 'bdcorp.tbdiretor';
        $campo_id = 'id';
        break;

    default:
        header('Location: ../combustivel/retirada/index.php?error=Perfil não autorizado para retirada.');
        exit;
}

// Busca os dados do responsável
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 's', $matricula_autor);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dados_responsavel = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$dados_responsavel) {
    header('Location: ../combustivel/retirada/index.php?error=Responsável não encontrado para o perfil ' . $perfil_autor);
    exit;
}

$matricula_responsavel = $dados_responsavel['matricula'];
$id_responsavel = $dados_responsavel['id'];
$saldo_atual_responsavel = floatval($dados_responsavel['orcrecebido'] ?? 0);
$novo_saldo_responsavel = $saldo_atual_responsavel + $valor;



// Inicia transação
mysqli_begin_transaction($conn);

try {
    // 1. INSERE REGISTRO DE RETIRADA NO HISTÓRICO DO TÉCNICO
    $sql_historico = "INSERT INTO historico_combustivel 
                      (valor, matricula, operacao, matricula_autor, valor_anterior, valor_atual, acao, data) 
                      VALUES (?, ?, 'retirada', ?, ?, ?, 'remocao', NOW())";

    $stmt = mysqli_prepare($conn, $sql_historico);
    mysqli_stmt_bind_param($stmt, 'dssdd', $valor, $matricula_tecnico, $matricula_autor, $saldo_atual, $novo_saldo);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 2. ATUALIZA O SALDO DO TÉCNICO NO tbsaldo
    $sql_update_tecnico = "UPDATE tbsaldo SET 
                            totalextra = ?,
                            kmorcsem = ?
                          WHERE matricula = ?";
    $stmt = mysqli_prepare($conn, $sql_update_tecnico);
    mysqli_stmt_bind_param($stmt, 'dds', $novo_saldo, $novo_kmorcsem, $matricula_tecnico);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 3. ATUALIZA O orcrecebido DO RESPONSÁVEL
    $sql_update_responsavel = "UPDATE $tabela SET valor = ? WHERE $campo_id = ?";
    $stmt = mysqli_prepare($conn, $sql_update_responsavel);
    mysqli_stmt_bind_param($stmt, 'di', $novo_saldo_responsavel, $id_responsavel);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // 4. REGISTRA NO HISTÓRICO DO RESPONSÁVEL
    $sql_historico_resp = "INSERT INTO historico_combustivel 
                            (valor, matricula, operacao, matricula_autor, valor_anterior, valor_atual, acao, data) 
                            VALUES (?, ?, 'adicao', ?, ?, ?, 'remocao', NOW())";

    $stmt = mysqli_prepare($conn, $sql_historico_resp);
    mysqli_stmt_bind_param($stmt, 'dssdd', $valor, $matricula_responsavel, $matricula_autor, $saldo_atual_responsavel, $novo_saldo_responsavel);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // Confirma transação
    mysqli_commit($conn);

    $tipo_nome = ucfirst($tipo_responsavel);
    header('Location: ../combustivel/retirada/index.php?success=Retirada de R$ ' . number_format($valor, 2, ',', '.') .
        ' realizada com sucesso! Saldo adicionado ao ' . $tipo_nome . ' ' . $matricula_responsavel);
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    header('Location: ../combustivel/retirada/index.php?error=' . urlencode('Erro ao realizar retirada: ' . $e->getMessage()));
    exit;
}
?>