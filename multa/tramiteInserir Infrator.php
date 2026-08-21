<?php
// Defina o tempo de vida da sessão para 1 hora
$lifetime = 3600; // 1 hora em segundos

// Configurar os parâmetros do cookie da sessão
session_set_cookie_params($lifetime);

// Iniciar a sessão
session_start();

$usuariof = $_SESSION['usuario'];
$perfil = $_SESSION['perfil'];
$nome = $_SESSION['nome'];
$tipo = $_SESSION['tipo'];
$nome = utf8_encode($nome);

if ($perfil == null) {
  echo "<script language='javascript' type='text/javascript'> window.alert('Você deve logar para ter acesso.');window.location=\"../frotas/index.php\";
   </script>";
}

$_SESSION['usuario'] = $usuariof;
$_SESSION['perfil'] = $perfil;
$_SESSION['nome'] = $nome;
$_SESSION['tipo'] = $tipo;

include "conecta.php";
include "conecta2.php";
header("Content-type: text/html; charset=utf-8");

$link = "$_SERVER[REQUEST_URI]";
if (strstr($link, '=') == false) {

  $id = $_POST['id'];
} else {
  $idaux = explode("=", $link);
  $id = $idaux[1];
}

/*
$idaux = explode("=", $link);
$id = $idaux[1];*/

$placa = $_POST['placa'];
$id = $_POST['id'];

//$id=2759;
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, , shrink-to-fit=no" />
  <meta name="description" content="FFA" />
  <meta name="author" content="FFA" />
  <link rel="icon" type="image/png" href="src/images/favicon.png" />

  <title> Inserir Infrator </title>

  <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
  <link href="src/css/styles.css" rel="stylesheet" />
  <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>

  <!-- Vendor CSS
  <link href="src/vendor/select2/select2.min.css" rel="stylesheet" media="all">
  <link href="src/vendor/datepicker/daterangepicker.css" rel="stylesheet" media="all">-->

  <!-- Bootstrap core CSS 
  <link href="src/index_files/bootstrap.css" rel="stylesheet">-->

  <!-- Custom styles for this template -->
  <!--<script src="src/index_files/ie-emulation-modes-warning.js.download"></script>
  <script type='text/javascript' src='src/vendor/jquery/jquery-3.2.1.min.js'></script>
  <script type='text/javascript' src='src/vendor/jquery/jquery.min.js'></script>-->

  <!--link Material Symbols - simbolos do input-->
  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />

  <!-- jquery e masks -->
  <script src='https://code.jquery.com/jquery-3.6.0.min.js'
    integrity='sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=' crossorigin='anonymous'></script>

  <script type="text/javascript"
    src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.15/jquery.mask.min.js"></script>

  <!--data tables - css e js-->
  <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
  <link href="src/css/styles.css" rel="stylesheet" />
  <!--<script type="text/javascript" src='https://code.jquery.com/jquery-3.5.1.js'></script>-->
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.css">
  <script type="text/javascript" charset="utf8"
    src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.js"></script>

</head>

<body class="sb-nav-fixed vsc-initialized sb-sidenav-toggled">
  <!--nav-->
  <?php include "./menu.php"; ?>

  <div id="layoutSidenav_content">

    <main style="width: 100%;" class="mb-2">

      <div class="container-fluid px-4" style="width: 80%;">
        <h1 class="h1 pt-2 pb-2"> Inserir Infrator </h1>

        <!-- div formulario-->
        <div class="shadow text-center p-3 mb-5 bg-body rounded">
          <p>Indique o real infrator. Se for o funcionário abaixo, confirme os dados. Se for outro funcionário diverso
            do que está abaixo, corrija os dados. </p>

          <?php
          $sql = "SELECT placa, dtinfra, nome, matricula, cpf, autoinfra FROM bdfrota.tbmovidatramite where idtbmovidatramite= '$id';";
          $resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
          $row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

          $placa = $row['placa'];
          $dtinfrab = $row['dtinfra'];
          $nomecond = $row['nome'];
          $matriculacond = $row['matricula'];
          $cpf = $row['cpf'];
          $autoinfra = $row['autoinfra'];
          $idmulta = $row['idmulta'];

          if ($nomecond == '' || $nomecond == ' ') {
            //$sqla1 = "SELECT matricula, nome, dataassoc, datadissoc FROM bdfrota.tbcondutor where placaassoc = '$placa' AND dataassoc BETWEEN '0000-00-00' AND '$dtinfrab' and datadissoc BETWEEN '0000-00-00' AND '$dtinfrab'";
            $sqla1 = "SELECT matricula, nome, dataassoc, datadissoc FROM bdfrota.tbcondutor where placaassoc = '$placa' AND dataassoc<='$dtinfrab' and datadissoc>='$dtinfrab' AND datadissoc<>'0000-00-00 00:00:00' ";

            $resultadoa = mysqli_query($conexao, $sqla1) or die(mysqli_error($conexao));

            if (mysqli_num_rows($resultadoa) <= 0) {
              $nomecond = $row['nome'];
              $matriculacond = $row['matricula'];
            } else {
              $rowa = mysqli_fetch_array($resultadoa, MYSQLI_BOTH);
              $nomecond = $rowa['nome'];
              $matriculacond = $rowa['matricula'];
            }
          }

          $sqla = "SELECT cpf, status FROM bdcorp.tbfuncionario WHERE matricula = '$matriculacond'";
          $resultadoa = mysqli_query($conexao, $sqla) or die(mysqli_error($conexao));
          $dados = mysqli_fetch_array($resultadoa, MYSQLI_BOTH);
          $cpf = $dados["cpf"];
          $statuscond = utf8_encode($dados["status"]);

          if (!empty($idmulta)) {
            $sql3 = "SELECT etapa FROM bdfrota.tbmulta WHERE idtbmulta = '$idmulta';";
          } else {
            $sql3 = "SELECT etapa FROM bdfrota.tbmulta WHERE autoinfracao='$autoinfra' AND placa='$placa';";
          }
          $resultado3 = mysqli_query($conexao, $sql3) or die(mysqli_error($conexao));
          $row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
          $etapaatual = $row3['etapa'];

          //if($statuscond<>'Demitido'){
          print "
          <form name='tramite1' method='post' action='/multas/control/tramiteIRFcontrol2.php'>
            <input name='placa' id='placa' type='hidden' value='$placa'>
            <input name='placa' id='autoinfra' type='hidden' value='$autoinfra'>
            <input name='id' id='id' type='hidden' value='$id'>
            <input name='mat_autor' type='hidden' value='$usuariof'>

            
            <div class='d-flex justify-content-start'>
              <div class='ms-1 col-2'>            
                <label class='form-label'> Matricula: </label>
                <input name='matricula' id='matricula' type='text' class='form-control' onkeypress='return event.charCode >= 48 && event.charCode <= 57' maxlength='6' value='$matriculacond' > 
              </div>

              <div class='ms-2 col-5'>
                <label class='form-label'> Nome: <span style='color: red;'>*</span> </label>
                <input name='nome' id='nome' type='text' class='form-control' value='$nomecond' required>
              </div>

              <div class='ms-2 col-2'>
                <label class='form-label'> CPF: <span style='color: red;'>*</span> </label>
                <input name='cpf' id='cpf' type='text' class='form-control' value='$cpf' required>
              </div>

              <div class='ms-1 col-2'>            
                <label class='form-label'> Status do condutor: </label>
                <input name='statusaniel' id='statusaniel' type='text' class='form-control' value='$statuscond'> 
              </div>
            </div>

            <div class='mt-3 ms-2'>
              <input class='btn btn-success' type='submit' value='Confirmar Infrator'>
            </div>
          </form>";
          //}          
          ?>
        </div>


        <div class="mt-2">
          <p style='color: red; font-size: 12px;'>* Campos obrigatórios.</p>
        </div>

        <!-- div tabela-->
        <div style="width: 100%;" class="shadow p-1 mb-5 bg-body rounded">
          <table id="tabela1" class="table">


            <thead>
              <tr>
                <th>Status</th>
                <th>Placa do Veiculo</th>
                <th>TIPO</th>
                <th>Nº do Auto</th>
                <th>Data da Infração</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Quantidade de Parcelas</th>
                <th>Valor das Parcelas</th>
              </tr>
            </thead>
            <tbody>
              <?php
              //echo "ID = $id";
              //$sql = "SELECT tramite, placa, gravidade, autoinfra, datainfra, dtvenc, valor FROM bdfrota.tbsmultas where placa='$placa'; ";
              //$sql = "SELECT tramite, placa, gravidade, autoinfra, datainfra, dtvenc, valor FROM bdfrota.tbsmultas where id='$id'; ";
              
              $sql = "SELECT tmt.dtcons, tmt.placa, tmt.gravidade, tmt.autoinfra, tmt.dtinfra, tmt.dtvenc, tmt.valor, tm.numparcelas, tm.valparcelas, tmt.tramite, tmt.nome, tmt.matricula FROM bdfrota.tbmovidatramite tmt join bdfrota.tbmulta tm on tmt.idmulta = tm.idtbmulta where idtbmovidatramite='$id'";
              $resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
              $row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

              $gravidade = utf8_encode($row['gravidade']);

              $datainfra = $row[4];
              $datainfra = explode(" ", $datainfra);
              $timeinfra = $datainfra[1];
              $datainfra = explode("-", $datainfra[0]);
              $datainfra2 = $datainfra[2] . '/' . $datainfra[1] . '/' . $datainfra[0] . " " . $timeinfra;

              $valor = str_replace(".", ",", $row[6]);
              $valorParcelas = str_replace(".", ",", $row[8]);

              $dtvenc = $row[5];
              $dtvenc = explode(" ", $dtvenc);
              $dtvenc = $dtvenc[0];
              $dtvenc = explode("-", $dtvenc);
              $dtvenc2 = $dtvenc[2] . '/' . $dtvenc[1] . '/' . $dtvenc[0];
              if ($dtvenc2 == '00/00/0000') {
                $dtvenc3 = '-';
              } else {
                $dtvenc3 = $dtvenc2;
              }

              print "
                  <tr>
                    <td>$row[0]</td>
                    <td>$row[1]</td>
                    <td>$gravidade</td>
                    <td>$row[3]</td>
                    <td>$datainfra2</td>
                    <td>$dtvenc3</td>
                    <td>R$ $valor</td>
                    <td>$row[7]</td>
                    <td>R$ $valorParcelas</td>
                  </tr>";
              ?>
            </tbody>
          </table>
        </div>

        <!--div atualizar por etapa-->
        <div style="width: 100%;" class="shadow p-3 mb-5 bg-body rounded">
          <form action='./control/atualizaretapa.php' method='post'>
            <h5>Atualizar etapa:</h5>

            <!--<p class="p-2">Caso esta multa esteja em uma etapa mais avançada na NextFlet, atualize selecionando a etapa abaixo (Esta edição é opcional):</p> trâmite por-->
            <?php print "<input type='hidden' name='idtbmovidatramite' value='$id'> 
              <input name='placa' id='placa' type='hidden' value='$placa'>
              <input name='mat_autor' id='mat_autor' type='hidden' value='$usuariof'>"; ?>
            <div class='col-8'>
              <select class="form-select" name='etapa1'>
                <option value="">Selecione...</option>
                <?php
                $sql4 = "SELECT DISTINCT(etapa) FROM bdfrota.tbaetapamulta WHERE idtbaetapamulta not in('7','3') ORDER BY etapa;";
                $resultado4 = mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));

                while ($row4 = mysqli_fetch_array($resultado4, MYSQLI_ASSOC)) {
                  $etapa = utf8_encode($row4['etapa']);
                  $selected = ($etapa == $etapaatual) ? "selected=\"selected\"" : null;

                  if ($etapa <> '') {
                    print "<option value='$etapa' $selected>$etapa</option>";
                  }

                }
                ?>
              </select>

            </div>


            <div class="p-1">
              <button class='btn btn-success' type='submit'>Atualizar Etapa</button>
            </div>

          </form>
        </div>

        <!-- botão voltar a pagina inicial -->
        <div class="mt-5 text-center">
          <a href="/multas/multasfrota.php">
            <button class='btn btn-secondary'>Voltar para página inicial</button>
          </a>
        </div>
      </div>
    </main>

    <footer class="py-4 bg-light mt-auto">
      <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between small">
          <div class="text-muted">Copyright &copy; FFA Infraestrutura</div>
          <!--<div>
                <a href="#">Privacy Policy</a>
                &middot;
                <a href="#">Terms &amp; Conditions</a>
            </div>-->
        </div>
      </div>
    </footer>

  </div>
  <!--</div>div para fechar a div que fica aberta no menu.php-->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
    crossorigin="anonymous"></script>
  <script src="src/js/scripts.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>


  <script type='text/javascript' src='/var/www/html/presenca/src/vendor/bootstrap/js/bootstrap.min.js'></script>

  <script>window.jQuery || document.write('<script src="src/vendor/jquery/jquery.min.js"><\/script>')</script>

  <script type="text/javascript">
    $(document).ready(function () {
      /*$('#tabela1').DataTable({
         order:[0, 'desc']
      });*/
      $('#cpf').mask('000.000.000-00');
    });
  </script>

</body>

</html>