<?php
date_default_timezone_set('America/Sao_Paulo');
include("../conecta.php");
include_once"../func/log.php";
session_start();

$hoje = date('Y-m-d H:i:s');
$id         = $_POST['id'] ?? '';
$matricula  = $_POST['matricula'] ?? '';
$nome       = $_POST['nome_colaborador'] ?? '';
$cpf        = $_POST['cpf'] ?? '';
$placa      = $_POST['placa'] ?? '';
$mat_autor  = $_SESSION['matricula'] ?? '';

// Validações
if(empty($id) || empty($matricula) || empty($nome) || empty($cpf)) {
    echo "<script>
        Swal.fire({
            icon: 'error',
            title: 'Erro!',
            text: 'Dados incompletos. Verifique todos os campos.',
            confirmButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                window.history.back();
            }
        });
    </script>";
    exit;
}

// Limpa o CPF
$cpf = str_replace([".", "-"], "", $cpf);

// Proteção contra SQL Injection
$id = mysqli_real_escape_string($conn, $id);
$matricula = mysqli_real_escape_string($conn, $matricula);
$nome = mysqli_real_escape_string($conn, $nome);
$cpf = mysqli_real_escape_string($conn, $cpf);
$placa = mysqli_real_escape_string($conn, $placa);
$mat_autor = mysqli_real_escape_string($conn, $mat_autor);

// Executa o UPDATE
$sql = "UPDATE tbmovidatramite SET nome = '$nome', cpf = '$cpf', matricula = '$matricula', tramite = 'Imprimir Recibo DP' WHERE idtbmovidatramite = '$id'";
$resultado = mysqli_query($conn, $sql);

if($resultado) {
    // Registra o log
    $datahora = $hoje;
    $acao = "editou infrator - idtbmovidatramite: $id";
    $tipo = 'multa';
    $logresult = enviarlognovo($datahora, $acao, $matricula, $mat_autor, $tipo, $placa);
    
    // Sucesso com SweetAlert
    echo "<!DOCTYPE html>
    <html>
    <head>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Dados do infrator editados com sucesso!',
                confirmButtonColor: '#3085d6',
                confirmButtonText: 'OK',
                timer: 3000
            }).then((result) => {
                if (result.isConfirmed || result.dismiss === Swal.DismissReason.timer) {
                    window.location.href = '../multa/multasfrota.php';
                }
            });
        </script>
    </body>
    </html>";
} else {
    // Erro com SweetAlert
    echo "<!DOCTYPE html>
    <html>
    <head>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                text: 'Erro ao atualizar: " . mysqli_error($conn) . "',
                confirmButtonColor: '#d33',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.history.back();
                }
            });
        </script>
    </body>
    </html>";
}
?>