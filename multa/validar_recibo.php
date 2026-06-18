<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
session_start();

$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

// Verificar se o usuário está logado
if (!isset($_SESSION['usuario']) || $_SESSION['usuario'] == null) {
  echo "<script language='javascript' type='text/javascript'> window.alert('Você deve logar para ter acesso.');window.location=\"./index.php\";</script>";
  exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$usuarioLogado = (string) ($_SESSION['usuario'] ?? '');
$tipoUsuario = (string) ($_SESSION['tipo'] ?? '');

$dadosMulta = [];
$statusCondutor = '';
$recibo = '';

function esc(?string $valor): string
{
  return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function formatarDataHora(?string $valor): string
{
  if (empty($valor) || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
    return '-';
  }

  $data = date_create($valor);
  return $data ? date_format($data, 'd/m/Y H:i:s') : (string) $valor;
}

function formatarData(?string $valor): string
{
  if (empty($valor) || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
    return '-';
  }

  $data = date_create($valor);
  return $data ? date_format($data, 'd/m/Y') : (string) $valor;
}

function limparNomeArquivo(string $nomeArquivo): string
{
  $nomeArquivo = trim($nomeArquivo);
  $nomeArquivo = str_replace('-%20', '-', $nomeArquivo);
  $nomeArquivo = str_replace('- ', '-', $nomeArquivo);
  return $nomeArquivo;
}

if ($conn instanceof mysqli && $id > 0) {
  mysqli_set_charset($conn, 'utf8mb4');

  // Buscar dados do recibo
  $sqlRecibo = "SELECT placa, autoinfra, recibo FROM tbmovidatramite WHERE idtbmovidatramite = ?";
  $stmtRecibo = mysqli_prepare($conn, $sqlRecibo);
  if ($stmtRecibo) {
    mysqli_stmt_bind_param($stmtRecibo, 'i', $id);
    mysqli_stmt_execute($stmtRecibo);
    $resRecibo = mysqli_stmt_get_result($stmtRecibo);
    $rowRecibo = mysqli_fetch_assoc($resRecibo);

    if ($rowRecibo) {
      $placa = (string) ($rowRecibo['placa'] ?? '');
      $autoinfra = (string) ($rowRecibo['autoinfra'] ?? '');
      $reciboPath = (string) ($rowRecibo['recibo'] ?? '');

      if (!empty($reciboPath)) {
        $caminho = pathinfo($reciboPath);
        $filename = limparNomeArquivo($caminho['filename'] ?? '');
        $recibo = ($caminho['dirname'] ?? '') . '/' . $filename . '.pdf';
      }
    }
    mysqli_stmt_close($stmtRecibo);
  }

  // Buscar dados da multa
  $sqlMulta = "
        SELECT 
            dtcons, 
            placa, 
            gravidade, 
            idmovida, 
            dtinfra, 
            dtvenc, 
            valor, 
            tramite, 
            autoinfra, 
            nome, 
            matricula 
        FROM 
            tbmovidatramite 
        WHERE 
            idtbmovidatramite = ?
    ";

  $stmtMulta = mysqli_prepare($conn, $sqlMulta);
  if ($stmtMulta) {
    mysqli_stmt_bind_param($stmtMulta, 'i', $id);
    mysqli_stmt_execute($stmtMulta);
    $resMulta = mysqli_stmt_get_result($stmtMulta);
    $rowMulta = mysqli_fetch_assoc($resMulta);

    if ($rowMulta) {
      $dadosMulta = [
        'dtcons' => formatarDataHora((string) ($rowMulta['dtcons'] ?? '')),
        'placa' => (string) ($rowMulta['placa'] ?? ''),
        'gravidade' => (string) ($rowMulta['gravidade'] ?? ''),
        'idmovida' => (string) ($rowMulta['idmovida'] ?? ''),
        'dtinfra' => formatarDataHora((string) ($rowMulta['dtinfra'] ?? '')),
        'dtvenc' => formatarData((string) ($rowMulta['dtvenc'] ?? '')),
        'valor' => (string) ($rowMulta['valor'] ?? '0'),
        'tramite' => (string) ($rowMulta['tramite'] ?? ''),
        'autoinfra' => (string) ($rowMulta['autoinfra'] ?? ''),
        'nome' => (string) ($rowMulta['nome'] ?? ''),
        'matricula' => (string) ($rowMulta['matricula'] ?? '')
      ];

      // Buscar status do funcionário
      if (!empty($dadosMulta['matricula'])) {
        $sqlStatus = "SELECT status FROM tbfuncionario WHERE matricula = ? LIMIT 1";
        $stmtStatus = mysqli_prepare($conn, $sqlStatus);
        if ($stmtStatus) {
          mysqli_stmt_bind_param($stmtStatus, 's', $dadosMulta['matricula']);
          mysqli_stmt_execute($stmtStatus);
          $resStatus = mysqli_stmt_get_result($stmtStatus);
          $rowStatus = mysqli_fetch_assoc($resStatus);
          $statusCondutor = (string) ($rowStatus['status'] ?? '');
          mysqli_stmt_close($stmtStatus);
        }
      }
    }
    mysqli_stmt_close($stmtMulta);
  }
}

// Formatar valor
$valorFormatado = 'R$ ' . number_format((float) str_replace(',', '.', $dadosMulta['valor'] ?? '0'), 2, ',', '.');
$tramite = $dadosMulta['tramite'] ?? '';
$link = $tramite . "2";

// Determinar o botão de voltar
$botaoVoltar = '';
if ($tipoUsuario == '4') {
  $botaoVoltar = '<a href="/multas/multasfrota.php"><button class="btn btn-secondary">Voltar para página inicial</button></a>';
} elseif ($tipoUsuario == '6') {
  $botaoVoltar = '<a href="/multas/multasdp.php"><button class="btn btn-secondary">Voltar para página inicial</button></a>';
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="FFA" />
  <meta name="author" content="FFA" />
  <link rel="icon" type="image/png" href="src/images/favicon.png" />
  <title>Validar Recibo</title>

  <link rel="stylesheet"
    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
  <link href="src/css/styles.css" rel="stylesheet" />
  <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>

  <style>
    body {
      background: #f8f9fc;
      color: #000000;
      font-size: 12px;
    }

    .page-wrapper {
      max-width: 100%;
      overflow-x: auto;
      padding: 20px;
    }

    .page-title {
      font-size: 24px;
      font-weight: 600;
      margin: 0 0 5px;
    }

    .card-shadow {
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      border-radius: 0.5rem;
      background: white;
      padding: 20px;
      margin-bottom: 20px;
    }

    .btn-primary {
      background-color: #0d6efd;
      border-color: #0d6efd;
    }

    .btn-primary:hover {
      background-color: #0b5ed7;
      border-color: #0a58ca;
    }

    .form-check-input:checked {
      background-color: #0d6efd;
      border-color: #0d6efd;
    }

    .table-container {
      overflow-x: auto;
    }

    #tabelaMulta {
      width: 100%;
    }

    #tabelaMultas thead th {
      color: #000000;
      font-size: 12px;
      vertical-align: bottom;
      white-space: nowrap;
      padding: 8px 6px;
    }

    #tabelaMultas tbody td {
      font-size: 11px;
      padding: 8px 6px;
      vertical-align: middle;
    }
  </style>
</head>

<body class="sb-nav-fixed">
  <?php autofrotaMenu(); ?>

  <div id="layoutSidenav_content">
    <main class="page-wrapper py-3">
      <h1 class="page-title text-center mb-4">Validar Recibo</h1>

      <!-- Cards de ação -->
      <div class="row justify-content-center g-4 mb-5">
        <!-- Card Visualizar Recibo -->
        <div class="col-md-5">
          <div class="card-shadow h-100 text-center">
            <?php if (!empty($recibo) && $id != 7977): ?>
              <p class="mb-3">Para visualizar o recibo referente a esta multa, clique no botão abaixo.</p>
              <a class="btn btn-primary" href="<?= esc($recibo) ?>" target="_blank">
                Abrir o Recibo
              </a>
              <a class="btn btn-primary" href="multasdp.php" target="_blank">
                Voltar
              </a>
            <?php elseif (!empty($recibo)): ?>
              <p class="mb-3">Para visualizar o recibo referente a esta multa, clique no botão abaixo.</p>
              <a class="btn btn-primary" href="<?= esc($recibo) ?>" target="_blank">
                Abrir o Recibo
              </a>
              <a class="btn btn-primary" href="multasdp.php" target="_blank">
                Voltar
              </a>
            <?php else: ?>
              <p class="mb-3">Para visualizar o recibo referente a esta multa, clique no botão abaixo.</p>
              <form action="./pdf/fpdf/reciboffaass.php" method="post" target="_blank">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-primary">Abrir o Recibo</button>
              </form>
              <a class="btn btn-primary" href="multasdp.php" target="_blank">
                Voltar
              </a>
            <?php endif; ?>
          </div>
        </div>

        <!-- Card Validar Recibo -->
        <div class="col-md-5">
          <div class="card-shadow h-100 text-center">
            <p> O desconto será feito?</p>
            <?php
            print "
            <form name='tramiteDF' method='post' action='../control/finalizar_multa.php'>
              <input type='hidden' name='id' value='$id'>
              <input type='hidden' name='mat_autor' value='$usuariof'>

              
              <div class='d-flex text-center justify-content-around mb-2'>
                <div class='form-check'>
                  <input class='form-check-input' type='radio' id='sim' name='dif1' value='sim' >
                  <label class='form-check-label' for='sim'> Sim </label>
                </div>

                <div class='form-check'>
                  <input class='form-check-input' type='radio' id='nao' name='dif1' value='nao' checked>
                  <label class='form-check-label' for='nao'>Não </label>
                </div>
              </div>

              <input class='btn btn-primary' type='submit' value='Enviar'>
            </form>";
            ?>
          </div>
        </div>
      </div>

      <!-- Tabela de dados da multa -->
      <div class="card-shadow">
        <div class="table-container">
          <table id="tabelaMultas" class="table table-striped">
            <thead>
              <tr>
                <th>Nome</th>
                <th>Matrícula</th>
                <th>Status do funcionário</th>
                <th>Data da Consulta</th>
                <th>Placa do Veículo</th>
                <th>Gravidade</th>
                <th>Nº do Auto</th>
                <th>Data da Infração</th>
                <th>Vencimento</th>
                <th>Valor</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($dadosMulta['nome'])): ?>
                <tr>
                  <td><?= esc($dadosMulta['nome']) ?></td>
                  <td><?= esc($dadosMulta['matricula']) ?></td>
                  <td><?= esc($statusCondutor) ?></td>
                  <td><?= esc($dadosMulta['dtcons']) ?></td>
                  <td><?= esc($dadosMulta['placa']) ?></td>
                  <td><?= esc($dadosMulta['gravidade']) ?></td>
                  <td><?= esc($dadosMulta['autoinfra']) ?></td>
                  <td><?= esc($dadosMulta['dtinfra']) ?></td>
                  <td><?= esc($dadosMulta['dtvenc']) ?></td>
                  <td><?= esc($valorFormatado) ?></td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Botão Voltar -->
      <div class="text-center mt-4">
        <?= $botaoVoltar ?>
      </div>
    </main>

    <footer class="py-4 bg-light mt-auto">
      <div class="container-fluid px-4">
        <div class="d-flex align-items-center justify-content-between small">
          <div class="text-muted">Copyright &copy; FFA Infraestrutura</div>
        </div>
      </div>
    </footer>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="src/js/scripts.js"></script>

  <script>
    $(document).ready(function () {
      $('#tabelaMultas').DataTable({
        paging: false,
        ordering: false,
        info: false,
        searching: false,
        language: {
          emptyTable: "Nenhum dado encontrado"
        }
      });
    });
  </script>
</body>

</html>