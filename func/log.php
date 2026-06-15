<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../control/conecta.php';

function getConexaoMysqli()
{
    global $conexao, $conn;

    if (isset($conexao) && $conexao instanceof mysqli) {
        return $conexao;
    }

    if (isset($conn) && $conn instanceof mysqli) {
        return $conn;
    }

    die('Erro: conexao mysqli nao encontrada.');
}

function enviarlog($datahora, $acao, $flag, $matricula, $mat_autor, $valor_ant, $valor_novo)
{
    $db = getConexaoMysqli();

    $sql = "
        INSERT INTO tblog (
            data_e_hora, acao, flag, matricula, mat_autor, valor_ant, valor_novo
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?
        )
    ";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        die('Erro ao preparar log: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ssissdd',
        $datahora,
        $acao,
        $flag,
        $matricula,
        $mat_autor,
        $valor_ant,
        $valor_novo
    );

    $resultado = mysqli_stmt_execute($stmt);

    if (!$resultado) {
        $erro = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        die('Erro ao gravar log: ' . $erro);
    }

    mysqli_stmt_close($stmt);
    return $resultado;
}


function registrarLogAcessoAutofrota(mysqli $db, string $matricula): void
{
    $sql = "
        INSERT INTO tblog (
            data_e_hora, acao, flag, matricula, mat_autor
        ) VALUES (
            ?, ?, ?, ?, ?
        )
    ";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        return;
    }

    $dataHora = date('Y-m-d H:i:s');
    $acao = 'Iniciou sessão no Portal AutoFrota';
    $flag = 0;
    $matriculaAutor = $matricula;

    mysqli_stmt_bind_param(
        $stmt,
        'ssiss',
        $dataHora,
        $acao,
        $flag,
        $matricula,
        $matriculaAutor
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function enviarlognovo($datahora, $acao, $matricula, $mat_autor, $tipo, $placa)
{
    $db = getConexaoMysqli();

    $sql = "
        INSERT INTO tblog (
            data_e_hora, acao, matricula, mat_autor, tipo, placa
        ) VALUES (
            ?, ?, ?, ?, ?, ?
        )
    ";

    $stmt = mysqli_prepare($db, $sql);

    if (!$stmt) {
        die('Erro ao preparar log: ' . mysqli_error($db));
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ssssss',
        $datahora,
        $acao,
        $matricula,
        $mat_autor,
        $tipo,
        $placa
    );

    $resultado = mysqli_stmt_execute($stmt);

    if (!$resultado) {
        $erro = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        die('Erro ao gravar log: ' . $erro);
    }

    mysqli_stmt_close($stmt);
    return $resultado;
}
?>
