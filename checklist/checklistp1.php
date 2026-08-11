<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
autofrotaInit();
$placa = strtoupper(trim((string) ($_POST['placa'] ?? $_GET['placa'] ?? '')));
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="FFA Infraestrutura">
  <link rel="icon" type="image/png" href="../src/images/favicon.png">
  <title>Checklist - Passo 1</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
  <style>
    .checklist-container { max-width: 1000px; }
    .checklist-section { border: 0; box-shadow: 0 .125rem .5rem rgba(0, 0, 0, .08); }
    .checklist-section .card-header { background: #fff; }
    .required { color: #dc3545; }
  </style>
</head>
<body>
  <?php autofrotaMenu(); ?>

  <main class="container-fluid checklist-container px-4 pb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 pb-2">
      <div>
        <h1 class="h2 mb-1">Vistoria do veículo</h1>
        <p class="text-muted mb-0">Checklist · Passo 1</p>
      </div>
      <a class="btn btn-outline-secondary" href="./checklistinicio.php">
        <i class="fas fa-arrow-left me-1"></i> Voltar
      </a>
    </div>

    <form id="checklist-p1-form">
      <section class="card checklist-section my-4">
        <div class="card-header py-3"><h2 class="h5 mb-0"><i class="fas fa-car me-2 text-success"></i>Informações do veículo</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4">
            <label class="form-label" for="placa">Placa</label>
            <input class="form-control text-uppercase" id="placa" name="placa" value="<?= htmlspecialchars($placa, ENT_QUOTES, 'UTF-8') ?>" readonly>
          </div>
          <div class="col-md-5">
            <label class="form-label" for="modelo">Modelo</label>
            <input class="form-control" id="modelo" name="modelo" placeholder="Modelo do veículo">
          </div>
          <div class="col-md-3">
            <label class="form-label" for="ano">Ano</label>
            <input class="form-control" id="ano" name="ano" inputmode="numeric" placeholder="0000">
          </div>
        </div>
      </section>

      <section class="card checklist-section mb-4">
        <div class="card-header py-3"><h2 class="h5 mb-0"><i class="fas fa-id-card me-2 text-success"></i>Informações do condutor</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label" for="cpf">CPF <span class="required">*</span></label><input class="form-control" id="cpf" name="cpf" placeholder="000.000.000-00" required></div>
          <div class="col-md-8"><label class="form-label" for="nome">Nome <span class="required">*</span></label><input class="form-control" id="nome" name="nome" placeholder="Nome completo" required></div>
          <div class="col-md-4"><label class="form-label" for="matricula">Matrícula</label><input class="form-control" id="matricula" name="matricula"></div>
          <div class="col-md-4"><label class="form-label" for="cnh">CNH</label><input class="form-control" id="cnh" name="cnh"></div>
          <div class="col-md-4"><label class="form-label" for="validade-cnh">Validade da CNH</label><input class="form-control" type="date" id="validade-cnh" name="validade_cnh"></div>
        </div>
      </section>

      <section class="card checklist-section mb-4">
        <div class="card-header py-3"><h2 class="h5 mb-0"><i class="fas fa-clipboard-check me-2 text-success"></i>Informações da vistoria</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label" for="tipo">Tipo <span class="required">*</span></label><select class="form-select" id="tipo" name="tipo" required><option value="" selected disabled>Selecione</option><option>Recebimento</option><option>Devolução</option><option>Periódica</option></select></div>
          <div class="col-md-4"><label class="form-label" for="data-vistoria">Data <span class="required">*</span></label><input class="form-control" type="date" id="data-vistoria" name="data_vistoria" required></div>
          <div class="col-md-4"><label class="form-label" for="hora-vistoria">Hora <span class="required">*</span></label><input class="form-control" type="time" id="hora-vistoria" name="hora_vistoria" required></div>
          <div class="col-md-4"><label class="form-label" for="estado-geral">Estado geral <span class="required">*</span></label><select class="form-select" id="estado-geral" name="estado_geral" required><option value="" selected disabled>Selecione</option><option>Ótimo</option><option>Bom</option><option>Regular</option><option>Ruim</option><option>Péssimo</option></select></div>
          <div class="col-md-4"><label class="form-label" for="hodometro">Hodômetro <span class="required">*</span></label><div class="input-group"><input class="form-control" type="number" min="0" id="hodometro" name="hodometro" required><span class="input-group-text">km</span></div></div>
          <div class="col-md-4"><label class="form-label" for="tanque">Nível do tanque <span class="required">*</span></label><select class="form-select" id="tanque" name="tanque" required><option value="" selected disabled>Selecione</option><option>Reserva</option><option>1/4</option><option>1/2</option><option>3/4</option><option>Cheio</option></select></div>
        </div>
      </section>

      <section class="card checklist-section mb-4">
        <div class="card-header py-3"><h2 class="h5 mb-0"><i class="fas fa-screwdriver-wrench me-2 text-success"></i>Itens da vistoria</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label" for="teto">Teto</label><select class="form-select" id="teto" name="teto"><option selected>OK</option><option>Não OK</option></select></div>
          <div class="col-md-4"><label class="form-label" for="frente">Frente</label><select class="form-select" id="frente" name="frente"><option selected>OK</option><option>Não OK</option></select></div>
          <div class="col-md-4"><label class="form-label" for="laterais">Laterais</label><select class="form-select" id="laterais" name="laterais"><option selected>OK</option><option>Não OK</option></select></div>
          <div class="col-md-4"><label class="form-label" for="traseira">Traseira</label><select class="form-select" id="traseira" name="traseira"><option selected>OK</option><option>Não OK</option></select></div>
          <div class="col-md-4"><label class="form-label" for="pneus">Pneus</label><select class="form-select" id="pneus" name="pneus"><option selected>OK</option><option>Não OK</option></select></div>
          <div class="col-md-4"><label class="form-label" for="interior">Interior</label><select class="form-select" id="interior" name="interior"><option selected>OK</option><option>Não OK</option></select></div>
          <div class="col-12"><label class="form-label" for="observacoes">Observações</label><textarea class="form-control" id="observacoes" name="observacoes" rows="3" placeholder="Descreva avarias ou outras observações"></textarea></div>
        </div>
      </section>

      <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>Esta etapa apresenta somente o front-end. Os dados preenchidos ainda não serão gravados.</div>
      <div class="d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="./checklistinicio.php">Cancelar</a>
        <button class="btn btn-success px-4" type="submit">Continuar <i class="fas fa-arrow-right ms-1"></i></button>
      </div>
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    document.getElementById('checklist-p1-form').addEventListener('submit', function (event) {
      event.preventDefault();
    });
  </script>
</body>
</html>