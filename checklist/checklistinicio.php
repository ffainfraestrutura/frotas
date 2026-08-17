<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$conn = $autofrota['conn'] ?? null;
$databaseName = (string) ($autofrota['databaseName'] ?? '');
$veiculos = [];
$erroVeiculos = '';

if ($conn instanceof mysqli && preg_match('/^[a-zA-Z0-9_]+$/', $databaseName) === 1) {
    $sqlVeiculos = "SELECT DISTINCT placa FROM `{$databaseName}`.`tbveiculo` WHERE placa IS NOT NULL AND placa <> '' ORDER BY placa";
    $resultadoVeiculos = mysqli_query($conn, $sqlVeiculos);

    if ($resultadoVeiculos instanceof mysqli_result) {
        while ($veiculo = mysqli_fetch_assoc($resultadoVeiculos)) {
            $veiculos[] = $veiculo;
        }
        mysqli_free_result($resultadoVeiculos);
    } else {
        $erroVeiculos = 'Não foi possível carregar os veículos. Tente novamente.';
    }
} else {
    $erroVeiculos = 'Não foi possível conectar à base de veículos.';
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="FFA Infraestrutura">
  <link rel="icon" type="image/png" href="../src/images/favicon.png">
  <title>Iniciar vistoria</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
  <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
</head>
<body>
  <?php autofrotaMenu(); ?>

  <main class="mb-2" style="width: 100%;">
    <div class="container-fluid px-4" style="width: 80%;">
      <h1 class="h1 pt-3 pb-2 text-center">Checklist</h1>
      <?php if (($_GET['excluido'] ?? '') === '1'): ?><div class="alert alert-success"><i class="fas fa-check-circle me-1"></i>Checklist pendente excluído. Você já pode iniciar uma nova vistoria.</div><?php endif; ?>
      <?php if (($_GET['erro'] ?? '') === 'manutencao_aberta'): ?><div class="alert alert-danger"><i class="fas fa-ban me-1"></i>Veículo em manutenção aberta. Vistoria não autorizada.</div><?php endif; ?>
      <p class="text-center">Selecione uma placa para iniciar uma vistoria ou selecione uma para continuar um checklist pendente.</p>
      <div class="row justify-content-center">
        <form method="post" action="./control/iniciar-checklist.php" class="col-md-8">
          <div class="row align-items-center mb-3 m-auto justify-content-center" style="max-width: 500px;">
            <label class="col-auto col-form-label pe-2" for="placa">
              <i class="fas fa-car me-1"></i> Placa:
            </label>
            <div class="col">
              <select name="placa" id="placa" class="form-select" required>
                <option value="" selected disabled>Selecione uma opção</option>
                <?php foreach ($veiculos as $veiculo): ?>
                  <?php $placa = strtoupper(trim((string) ($veiculo['placa'] ?? ''))); ?>
                  <option value="<?= htmlspecialchars($placa, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($placa, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="col-12 text-center">
            <button class="btn btn-success px-5" type="submit">OK</button>
          </div>
        </form>
      </div>

      <?php if ($erroVeiculos !== ''): ?>
        <div class="mt-4 alert alert-danger" role="alert">
          <i class="fas fa-exclamation-triangle me-1"></i><?= htmlspecialchars($erroVeiculos, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <div class="mt-4 alert alert-info" role="alert">
        <i class="fas fa-info-circle"></i>
        <strong>Informação:</strong>
        <ul class="mb-0 mt-2">
          <li>Se você não possui uma assinatura digital cadastrada, não poderá realizar a vistoria. É necessário que o vistoriador possua assinatura cadastrada para poder assinar o relatório.</li>
        </ul>
      </div>
    </div>
  </main>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    $(function () {
      $('#placa').select2({
        theme: 'bootstrap-5',
        placeholder: 'Digite ou selecione uma placa',
        width: '100%',
        language: {
          noResults: function () { return 'Nenhuma placa encontrada'; },
          searching: function () { return 'Buscando...'; }
        }
      });
    });
  </script>
</body>
</html>