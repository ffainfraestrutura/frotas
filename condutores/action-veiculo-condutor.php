<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

//session_start();

// Recebe dados do formulário
$matricula = (string) ($_POST['matricula'] ?? '');
$placa = (string) ($_POST['placa'] ?? '');
$dataassoc = (string) ($_POST['dataassoc'] ?? '');
$matrAutor = (string) ($_POST['matr_autor'] ?? '');
$perfil = (string) ($_POST['perfil'] ?? '');

// Busca o nome do funcionário baseado na matrícula enviada
$sqlBuscaNome = "SELECT nome FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1";
$stmtNome = mysqli_prepare($conn, $sqlBuscaNome);

$nome = "";
if ($stmtNome) {
    mysqli_stmt_bind_param($stmtNome, 's', $matricula);
    mysqli_stmt_execute($stmtNome);
    $resNome = mysqli_stmt_get_result($stmtNome);
    if ($rowNome = mysqli_fetch_assoc($resNome)) {
        $nome = $rowNome['nome']; // Descobrimos o nome!
    }
    mysqli_stmt_close($stmtNome);
}


$redir = 'cadastro-veiculo-condutor.php?matricula=' . urlencode($matricula) . '&matr_autor=' . urlencode($matrAutor) . '&perfil=' . urlencode($perfil);

// Validações básicas
if ( $matricula === '' || $placa === '') {
    $_SESSION['msg_veiculo_condutor'] = 'Matrícula e placa são obrigatórios.';
    $_SESSION['msg_tipo_veiculo_condutor'] = 'danger';
    header('Location: ' . $redir);
    exit;
}

// Limpa a placa: remove espaços, hífens, etc. e converte para maiúsculas
$placa = strtoupper(preg_replace('/[^A-Z0-9]/', '', $placa));
if (strlen($placa) < 7) {
    $_SESSION['msg_veiculo_condutor'] = 'Placa inválida. Deve ter 7 caracteres alfanuméricos.';
    $_SESSION['msg_tipo_veiculo_condutor'] = 'danger';
    header('Location: ' . $redir);
    exit;
}

// Verifica se a placa já está associada a outro condutor ativo (que não seja o mesmo)
if (isset($conn) && $conn instanceof mysqli) {

    // 1. VALIDAÇÃO DA PLACA (Evitando duplicidade)
    $sqlCheck = "SELECT matricula 
                 FROM `{$databaseName}`.`tbcondutor` 
                 WHERE placaassoc = ? 
                   AND matricula != ? 
                   AND (datadissoc IS NULL OR datadissoc = CAST('0000-00-00 00:00:00' AS DATETIME))
                 LIMIT 1";
                 
    $stmtCheck = mysqli_prepare($conn, $sqlCheck);
    
    if ($stmtCheck) {
        mysqli_stmt_bind_param($stmtCheck, 'ss', $placa, $matricula);
        mysqli_stmt_execute($stmtCheck);
        $resCheck = mysqli_stmt_get_result($stmtCheck);
        
        if (mysqli_num_rows($resCheck) > 0) {
            $_SESSION['msg_veiculo_condutor'] = 'A placa ' . esc($placa) . ' já está associada a outro condutor.';
            $_SESSION['msg_tipo_veiculo_condutor'] = 'danger';
            mysqli_stmt_close($stmtCheck);
            header('Location: ' . $redir);
            exit;
        }
        mysqli_stmt_close($stmtCheck); 
    } // Fecha o if ($stmtCheck)

    // 2. NOVO INSERT (Substituindo o antigo UPDATE)
   // 2. NOVO INSERT (Atualizado com nome, ativo e statuscond)
    $sqlInsert = "INSERT INTO `{$databaseName}`.`tbcondutor` (matricula, nome, placaassoc, dataassoc, ativo, statuscond, datadissoc) 
                  VALUES (?, ?, ?, ?, 1, 'COM VEÍCULO VINCULADO', NULL)";

    $stmtInsert = mysqli_prepare($conn, $sqlInsert);

    if ($stmtInsert) {
        // Vincula as 3 variáveis correspondentes aos três '?' na ordem: matricula, nome e placa
        // 'sss' significa que os 3 parâmetros são strings
        mysqli_stmt_bind_param($stmtInsert, 'ssss', $matricula, $nome, $placa, $dataassoc);
        
        if (mysqli_stmt_execute($stmtInsert)) {
            $_SESSION['msg_veiculo_condutor'] = 'Veículo associado ao condutor com sucesso!';
            $_SESSION['msg_tipo_veiculo_condutor'] = 'success';
        } else {
            $_SESSION['msg_veiculo_condutor'] = 'Erro ao associar veículo: ' . mysqli_stmt_error($stmtInsert);
            $_SESSION['msg_tipo_veiculo_condutor'] = 'danger';
        }
        mysqli_stmt_close($stmtInsert);
    } else {
        $_SESSION['msg_veiculo_condutor'] = 'Erro na preparação da query de inserção: ' . mysqli_error($conn);
        $_SESSION['msg_tipo_veiculo_condutor'] = 'danger';
    }
} // <--- ESSA CHAVE FECHA O "if (isset($conn)...)" DA LINHA 42

// Redirecionamento final após sair dos blocos
header('Location: ' . $redir);
exit;
