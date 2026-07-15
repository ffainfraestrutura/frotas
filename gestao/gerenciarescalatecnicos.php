<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

date_default_timezone_set('America/Sao_Paulo');

$autofrotaSessao = autofrotaInit();
$nome1 = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'Usuário');
$usuariof = (string) ($_SESSION['usuario'] ?? $nome1);
$matricula1 = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? ($_GET['mat'] ?? ''));
$perfil = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? ($_GET['perfil'] ?? ''));
$tipo = (string) ($_SESSION['tipo'] ?? '');
$databaseAutofrota = trim((string) (($GLOBALS['databaseName'] ?? '') !== '' ? $GLOBALS['databaseName'] : ($autofrotaSessao['databaseName'] ?? 'bdautofrotas')));

/** @var mysqli|null $conn */
$conn = $autofrotaSessao['conn'] ?? null;

$_SESSION['perfil'] = $perfil;
$_SESSION['matricula'] = $matricula1;
$_SESSION['nome'] = $nome1;
$_SESSION['usuario'] = $usuariof;
$_SESSION['tipo'] = $tipo;

if ($perfil === '0' || $perfil === '') {
    echo "<script>alert('Você não tem permissão para acessar esta página'); window.location='../index.php';</script>";
    exit;
}

function escEscalaLegado($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$diaEncerramento = 5;
$horarioEncerramento = 15;

if ($conn instanceof mysqli && $databaseAutofrota !== '') {
    $sqlHorario = "SELECT dia_encerramento, hora_encerramento FROM `{$databaseAutofrota}`.`tbescala_configuracao` ORDER BY id DESC LIMIT 1";
    $resultadoHorario = mysqli_query($conn, $sqlHorario);

    if ($resultadoHorario && mysqli_num_rows($resultadoHorario) > 0) {
        $linhaHorario = mysqli_fetch_assoc($resultadoHorario);
        $diaTabela = (int) ($linhaHorario['dia_encerramento'] ?? $diaEncerramento);
        $horaTabela = (int) ($linhaHorario['hora_encerramento'] ?? $horarioEncerramento);

        if ($diaTabela >= 0 && $diaTabela <= 6) {
            $diaEncerramento = $diaTabela;
        }

        if ($horaTabela >= 0 && $horaTabela <= 23) {
            $horarioEncerramento = $horaTabela;
        }
    }
}

$diasSemana = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$diaEncerramentoTexto = $diasSemana[$diaEncerramento];
$horarioEncerramentoTexto = sprintf('%02d:00', $horarioEncerramento);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="FFA" />
  <meta name="author" content="FFA" />
  <link rel="icon" type="image/png" href="../src/images/favicon.png"/>

  <title> Gerenciar Escala Fim de Semana </title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
  <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" />
  <link href="../src/css/styles.css" rel="stylesheet" />
  <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
  <script type="text/javascript" src="https://code.jquery.com/jquery-3.7.0.js"></script>

  <style type="text/css">
    body{ font-size: 12px; }
    .btn{ font-size: 12px; }
    .modal-fullscreen-custom { max-width: 95%; max-height: 95%; margin: 2.5%; }
    .modal-fullscreen-custom .modal-content { height: 90vh; }
    .modal-fullscreen-custom .modal-body { overflow-y: auto; max-height: calc(90vh - 120px); }
    .modal-fullscreen-custom iframe { width: 100%; height: 100%; border: none; }
    .modal-header .btn-close { font-size: 0 !important; width: 50px !important; height: 50px !important; padding: 0 !important; opacity: 1 !important; background: transparent !important; position: relative; margin: 0; }
    .modal-header .btn-close::before { content: "×"; font-size: 60px !important; font-weight: bold; color: #000; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); line-height: 1; }
    .modal-header .btn-close:hover { opacity: 0.7 !important; transform: scale(1.1); background: transparent !important; }
    .modal-header .btn-close:hover::before { color: #6c757d; }
    .card-header.bg-primary { background: linear-gradient(45deg, #212529, #343a40) !important; }
    .btn-primary { background-color: #212529 !important; border-color: #212529 !important; }
    .btn-primary:hover, .btn-primary:focus { background-color: #343a40 !important; border-color: #343a40 !important; }
    .card.shadow { border-top: 4px solid #212529; }
  </style>
</head>
<body class="sb-nav-fixed vsc-initialized sb-sidenav-toggled autofrota-top-simple">
  <?php autofrotaMenu(); ?>

  <div id="layoutSidenav_content">
    <main style="width: 100%;" class="mb-2">
      <div class="container-fluid" style="width: 100%;">
        <h1 class="h1 pt-2 pb-2"> Gerenciar Escala Fim de Semana </h1>

        <div class="d-flex justify-content-center align-items-center" style="min-height: 60vh;">
          <div class="card mb-4 shadow" style="width: 600px;">
            <div class="card-header bg-primary text-white">
              <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Escala de Técnicos</h5>
            </div>
            <div class="card-body p-4 text-center">
              <div class="mb-4">
                <label class="form-label fw-bold"><i class="fas fa-user-tie me-2"></i>Coordenador</label>
                <input type="text" class="form-control form-control-lg text-center" value="<?= escEscalaLegado($nome1) ?>" readonly style="background-color: #e9ecef;">
                <small class="text-muted">Matrícula: <?= escEscalaLegado($matricula1) ?></small>
              </div>

              <div class="alert alert-info mb-4">
                <i class="fas fa-calendar-week me-2"></i>
                <strong>Semana:</strong> A escala sempre exibe o próximo fim de semana (sábado e domingo)
              </div>

              <div class="alert alert-warning mb-4" id="avisoHorario" style="display: none;">
                <h6 class="mb-1"><i class="fas fa-clock me-2"></i><strong>Atenção!</strong></h6>
                <p class="mb-0 small">As edições da semana são encerradas na <strong><?= escEscalaLegado($diaEncerramentoTexto) ?></strong>, às <strong><?= escEscalaLegado($horarioEncerramentoTexto) ?></strong>.</p>
              </div>

              <div class="d-grid">
                <button type="button" class="btn btn-primary btn-lg" onclick="abrirEscala()" id="btnAbrir">
                  <i class="fas fa-calendar-check me-2"></i> Abrir Gerenciamento de Escala
                </button>
              </div>

              <div class="alert alert-danger mt-4" id="avisoEncerrado" style="display: none;">
                <h5 class="mb-2"><i class="fas fa-lock me-2"></i>Edições Encerradas</h5>
                <p class="mb-0">⏰ Agendamentos de escala encerrados. Aguarde até segunda-feira para realizar novos agendamentos</p>
              </div>
            </div>
          </div>
        </div>
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

  <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-custom modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="importModalLabel">Gerenciar Escala Fim de Semana</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-0">
          <iframe id="iframeEscala" src="" title="Escala Fim de Semana" style="width: 100%; height: 75vh; border: none;"></iframe>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script src="../src/js/scripts.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
  <script>window.jQuery || document.write('<script src="../src/vendor/jquery/jquery.min.js"><\/script>')</script>

  <script>
    const DIA_ENCERRAMENTO = <?= (int) $diaEncerramento ?>;
    const HORA_ENCERRAMENTO = <?= (int) $horarioEncerramento ?>;
    const DIAS_SEMANA = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];

    function passouDoDiaEncerramento(diaAtual, diaLimite) {
      if (diaLimite === 0) { return diaAtual >= 1 && diaAtual <= 6; }
      return diaAtual > diaLimite || diaAtual === 0;
    }

    function verificarHorario() {
      const agora = new Date();
      const diaSemana = agora.getDay();
      const horas = agora.getHours();
      const btnAbrir = document.getElementById('btnAbrir');
      const avisoEncerrado = document.getElementById('avisoEncerrado');
      const avisoHorario = document.getElementById('avisoHorario');
      const bloqueado = (diaSemana === DIA_ENCERRAMENTO && horas >= HORA_ENCERRAMENTO) || passouDoDiaEncerramento(diaSemana, DIA_ENCERRAMENTO);

      if (bloqueado) {
        btnAbrir.style.display = 'none';
        avisoEncerrado.style.display = 'block';
        avisoHorario.style.display = 'none';
      } else {
        btnAbrir.style.display = 'block';
        avisoEncerrado.style.display = 'none';
        avisoHorario.style.display = 'block';
      }
    }

    function abrirEscala() {
      document.getElementById('iframeEscala').src = '../escala_fim_semana_tecnico.php';
      document.getElementById('importModalLabel').textContent = 'Escala Fim de Semana - Técnicos';
      const modal = new bootstrap.Modal(document.getElementById('importModal'));
      modal.show();
    }

    document.getElementById('importModal').addEventListener('hidden.bs.modal', function () {
      document.getElementById('iframeEscala').src = '';
    });

    window.addEventListener('message', function(event) {
      if (event.data === 'fecharModal') {
        const modal = bootstrap.Modal.getInstance(document.getElementById('importModal'));
        if (modal) { modal.hide(); }
      }
    });

    document.addEventListener('DOMContentLoaded', function() {
      verificarHorario();
      setInterval(verificarHorario, 60000);
    });
  </script>
</body>
</html>
<!-- telas e consultas relacionadas
SELECT * FROM bdautofrotas.tbescala;
SELECT * FROM bdautofrotas.tbescala_configuracao;
-->