<?php
$hoje = date('Y-m-d H:i:s');
include_once "../func/log.php";

include("conecta.php");
header("Content-type: text/html; charset=utf-8");

$dif1 = addslashes($_POST['dif1']);
$id = $_POST['id'];
$mat_autor = $_POST['mat_autor'];

$sqla = "SELECT idmulta, autoinfra, placa, matricula FROM tbmovidatramite WHERE idtbmovidatramite ='$id' ";
$resultadoa = mysqli_query($conn, $sqla) or die(mysqli_error($conn));
$rowa = mysqli_fetch_array($resultadoa, MYSQLI_BOTH);
$idmulta = $rowa['idmulta'];
$placa = $rowa['placa'];
$autoinfra = $rowa['autoinfra'];
$matcond = $rowa['matricula'];

if ($dif1 == 'nao') {
    $sql = "UPDATE tbmovidatramite SET tdp = 'Motivo Não Desconto', dtdesconto = NOW() where idtbmovidatramite='$id'";
    $resultado = mysqli_query($conn, $sql) or die(mysqli_error($conn));

    if ($idmulta <> '') {
        $sql2 = "UPDATE tbmulta SET etapa = 'NÃO DESCONTADO' where idtbmulta = '$idmulta'";
        $resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
    } else {
        $sql2 = "UPDATE tbmulta SET etapa = 'NÃO DESCONTADO' where placa = '$placa' AND autoinfracao = '$autoinfra'";
        $resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
    }

    $datahora = $hoje;
    $acao = "Atualizou multa-$autoinfra-DP Finalizado-NAO DESCONTADO";
    $tipo = 'multa';
    $logresult = enviarlognovo($datahora, $acao, $matcond, $mat_autor, $tipo, $placa);
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8">
        <title>Processando...</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>

    <body>
        <script>
            Swal.fire({
                title: 'Não Descontado!',
                html: `Multa não será descontada!<br>
                   Auto de Infração: <?php echo $autoinfra; ?><br>
                   Placa: <?php echo $placa; ?>`,
                icon: 'warning',
                confirmButtonText: 'OK',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../multa/multasdp.php';
                }
            });
        </script>
    </body>

    </html>
    <?php
    exit;
} elseif ($dif1 == 'sim') {
    $sqlaux = "SELECT status FROM tbmovidatramite where idtbmovidatramite='$id'";
    $resultadoaux = mysqli_query($conn, $sqlaux) or die(mysqli_error($conn));
    $rowaux = mysqli_fetch_array($resultadoaux, MYSQLI_BOTH);
    $status = $rowaux['status'];

    if ($status > 3) {
        $sql = "UPDATE tbmovidatramite SET descontocol = 'sim', tdp = 'DP Finalizado', dtdesconto = NOW(), status = 5 where idtbmovidatramite = '$id'";
        $resultado = mysqli_query($conn, $sql) or die(mysqli_error($conn));

        if ($idmulta <> '') {
            $sql2 = "UPDATE tbmulta SET etapa = 'Finalizado DP' where idtbmulta = '$idmulta'";
            $resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
        } else {
            $sql2 = "UPDATE tbmulta SET etapa = 'Finalizado DP' where placa = '$placa' AND autoinfra = '$autoinfra'";
            $resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
        }
    } else {
        $sql = "UPDATE tbmovidatramite SET descontocol = 'sim', tdp = 'DP Finalizado', dtdesconto = NOW(), status = 3 where idtbmovidatramite = '$id'";
        $resultado = mysqli_query($conn, $sql) or die(mysqli_error($conn));

        if ($idmulta <> '') {
            $sql2 = "UPDATE tbmulta SET etapa = 'Finalizado DP' where idtbmulta = '$idmulta'";
            $resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
        } else {
            $sql2 = "UPDATE tbmulta SET etapa = 'Finalizado DP' where placa = '$placa' AND autoinfra = '$autoinfra'";
            $resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
        }
    }

    $datahora = $hoje;
    $acao = "Atualizou multa-$autoinfra-DP Finalizado-Finalizado DP";
    $tipo = 'multa';
    $logresult = enviarlognovo($datahora, $acao, $matcond, $mat_autor, $tipo, $placa);
    ?>
    <!DOCTYPE html>
    <html lang="pt-br">

    <head>
        <meta charset="UTF-8">
        <title>Processando...</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>

    <body>
        <script>
            Swal.fire({
                title: 'Sucesso!',
                html: `Desconto confirmado com sucesso!<br>
                   Auto de Infração: <?php echo $autoinfra; ?><br>
                   Placa: <?php echo $placa; ?>`,
                icon: 'success',
                confirmButtonText: 'OK',
                allowOutsideClick: false,
                allowEscapeKey: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '../multa/multasdp.php';
                }
            });
        </script>
    </body>

    </html>
    <?php
    exit;
}
?>