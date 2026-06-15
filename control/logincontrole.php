<?php
date_default_timezone_set('America/Sao_Paulo');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/conecta.php';
require_once __DIR__ . '/../func/log.php';

if (!$conn) {
    redirecionarAutofrota('login.php?erro=' . urlencode('Erro de conexão com o banco de dados.'));
}

$usuario = trim($_POST['login'] ?? '');
$senha = $_POST['pass'] ?? '';

if ($usuario === '' || $senha === '') {
    redirecionarAutofrota('login.php?erro=' . urlencode('Matrícula e senha são obrigatórias.'));
}

if (!preg_match('/^[0-9]+$/', $usuario)) {
    redirecionarAutofrota('login.php?erro=' . urlencode('A matrícula deve conter apenas números.'));
}

$usuarioEsc = mysqli_real_escape_string($conn, $usuario);

$sql = "SELECT usuario, senha, perfil FROM tbusuario WHERE usuario = '$usuarioEsc' LIMIT 1";
$resultado = mysqli_query($conn, $sql);

if (!$resultado) {
    mysqli_close($conn);
    redirecionarAutofrota('login.php?erro=' . urlencode('Erro ao validar credenciais.'));
}

if (mysqli_num_rows($resultado) === 0) {
    mysqli_close($conn);
    redirecionarAutofrota('login.php?erro=' . urlencode('Usuário não encontrado.'));
}

$row = mysqli_fetch_assoc($resultado);

if (($row['senha'] ?? '') !== $senha) {
    mysqli_close($conn);
    redirecionarAutofrota('login.php?erro=' . urlencode('Senha incorreta.'));
}

$nomeFuncionario = '';
$sqlNome = "SELECT nome FROM `{$banco}`.`tbfuncionario` WHERE matricula = ? LIMIT 1";
$stmtNome = mysqli_prepare($conn, $sqlNome);

if ($stmtNome) {
    mysqli_stmt_bind_param($stmtNome, 's', $usuario);
    mysqli_stmt_execute($stmtNome);
    $resultadoNome = mysqli_stmt_get_result($stmtNome);

    if ($resultadoNome && ($rowNome = mysqli_fetch_assoc($resultadoNome))) {
        $nomeFuncionario = trim((string) ($rowNome['nome'] ?? ''));
    }

    mysqli_stmt_close($stmtNome);
}

$_SESSION['usuario'] = $row['usuario'];
$_SESSION['nome'] = $nomeFuncionario !== '' ? $nomeFuncionario : $row['usuario'];
$_SESSION['matricula'] = $row['usuario'];
$_SESSION['perfil'] = $row['perfil'];
$_SESSION['logado'] = true;

registrarLogAcessoAutofrota($conn, (string) $row['usuario']);

mysqli_close($conn);
if($row['perfil'] == 12) {
    header('Location: ../multa/multasdp.php');
    exit;
}
redirecionarAutofrota('index.php');