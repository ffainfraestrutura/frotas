<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
autofrotaInit();
$placa = strtoupper(trim((string) ($_GET['placa'] ?? '')));
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="FFA Infraestrutura">
  <link rel="icon" type="image/png" href="../src/images/favicon.png">
  <title>Checklist - Passo 2</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
  <style>
    .checklist-container { max-width: 1000px; }
    .photo-card { border: 0; box-shadow: 0 .125rem .5rem rgba(0, 0, 0, .08); transition: transform .15s ease; }
    .photo-card:hover { transform: translateY(-2px); }
    .photo-preview { align-items: center; background: #f2f4f5; border: 2px dashed #adb5bd; border-radius: .5rem; color: #6c757d; display: flex; height: 145px; justify-content: center; overflow: hidden; }
    .photo-preview img { height: 100%; object-fit: cover; width: 100%; }
    .photo-input:focus + .photo-label { box-shadow: 0 0 0 .25rem rgba(25, 135, 84, .25); }
    .step-badge { letter-spacing: .03em; }
  </style>
</head>
<body>
  <?php autofrotaMenu(); ?>

  <main class="container-fluid checklist-container px-4 pb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 pb-2">
      <div>
        <h1 class="h2 mb-1">Fotos do veículo</h1>
        <p class="text-muted mb-0">Checklist · Passo 2 <?= $placa !== '' ? '· ' . htmlspecialchars($placa, ENT_QUOTES, 'UTF-8') : '' ?></p>
      </div>
      <a class="btn btn-outline-secondary" href="./checklistp1.php<?= $placa !== '' ? '?placa=' . rawurlencode($placa) : '' ?>">
        <i class="fas fa-arrow-left me-1"></i> Voltar
      </a>
    </div>

    <div class="alert alert-info my-4">
      <i class="fas fa-info-circle me-1"></i> Registre os mesmos ângulos disponíveis no checklist legado. Nesta etapa de front-end, as fotos selecionadas não serão enviadas nem gravadas.
    </div>

    <form id="checklist-p2-form">
      <div class="row g-3">
        <?php
        $fotos = [
          'frontal' => ['Frontal', 'fa-car'],
          'traseira' => ['Traseira', 'fa-car-rear'],
          'direita' => ['Lateral direita', 'fa-car-side'],
          'esquerda' => ['Lateral esquerda', 'fa-car-side'],
          'painel' => ['Painel', 'fa-gauge-high'],
          'selfie' => ['Selfie', 'fa-user'],
          'cnh' => ['CNH', 'fa-id-card'],
          'extra1' => ['Extra 1', 'fa-camera'],
          'extra2' => ['Extra 2', 'fa-camera'],
          'extra3' => ['Extra 3', 'fa-camera'],
        ];
        foreach ($fotos as $id => [$titulo, $icone]):
        ?>
          <div class="col-sm-6 col-lg-4">
            <section class="card photo-card h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <h2 class="h6 mb-0"><i class="fas <?= $icone ?> me-2 text-success"></i><?= $titulo ?></h2>
                  <span class="badge bg-light text-secondary step-badge">FOTO</span>
                </div>
                <div class="photo-preview mb-3" id="preview-<?= $id ?>">
                  <span class="text-center"><i class="fas fa-image fa-2x d-block mb-2"></i>Nenhuma foto</span>
                </div>
                <input class="photo-input visually-hidden" type="file" accept="image/*" capture="environment" id="<?= $id ?>-foto" name="<?= $id ?>">
                <label class="btn btn-outline-success w-100 photo-label" for="<?= $id ?>-foto"><i class="fas fa-camera me-1"></i> Tirar ou escolher foto</label>
              </div>
            </section>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="./checklistinicio.php">Cancelar</a>
        <button class="btn btn-success px-4" type="submit">Continuar <i class="fas fa-arrow-right ms-1"></i></button>
      </div>
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    document.querySelectorAll('.photo-input').forEach(function (input) {
      input.addEventListener('change', function () {
        const arquivo = input.files && input.files[0];
        if (!arquivo) return;
        const preview = document.getElementById('preview-' + input.name);
        const imagem = document.createElement('img');
        imagem.alt = 'Pré-visualização de ' + input.name;
        imagem.src = URL.createObjectURL(arquivo);
        imagem.onload = function () { URL.revokeObjectURL(imagem.src); };
        preview.replaceChildren(imagem);
      });
    });

    document.getElementById('checklist-p2-form').addEventListener('submit', function (event) {
      event.preventDefault();
    });
  </script>
</body>
</html>