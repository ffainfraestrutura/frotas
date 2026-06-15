<?php
$matriculaAutor = (string) ($_SESSION['matricula'] ?? $_SESSION['usuario'] ?? '');
$actionApagar = file_exists(__DIR__ . '/apagarveiculo.php') ? 'apagarveiculo.php' : './control/apagarveiculo.php';
?>

<div class="modal fade" id="apagarveic" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Apagar Registro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="p-3 modal-body">
        <form class="mt-1" method="post" action="<?= htmlspecialchars((string) ($actionApagar ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="idtbveiculo" id="idVeiculoApagar" value="">
          <input type="hidden" id="inputform" value="">
          <input type="hidden" name="matr_autor" value="<?= htmlspecialchars($matriculaAutor, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="placa" id="pc2" value="">

          <p class="mb-2 form-label">Apagar registro do veículo placa <strong id="placaApagar"></strong><strong id="pc" style="display:none;"></strong>?</p>

          <div class="mb-2">
            <input type="radio" id="sim" name="escolha" value="1" required>
            <label for="sim">Sim</label><br>
            <input type="radio" id="nao" name="escolha" value="0">
            <label for="nao">Não</label><br>
          </div>

          <button type="submit" class="mt-2 btn btn-success">Confirmar</button>
        </form>
      </div>

      <div class="modal-footer justify-content-center" id="modal-includejust">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var originalPopup = window.popupApagarVeiculo;
    window.popupApagarVeiculo = function (idtbveiculo, placa) {
      if (typeof originalPopup === 'function') {
        originalPopup(idtbveiculo, placa);
      }

      var idNovo = document.getElementById('idVeiculoApagar');
      if (idNovo) {
        idNovo.value = idtbveiculo || '';
      }

      var idLegado = document.getElementById('inputform');
      if (idLegado) {
        idLegado.value = idtbveiculo || '';
      }

      var placaNovo = document.getElementById('placaApagar');
      if (placaNovo) {
        placaNovo.textContent = placa || '';
      }

      var placaLegado = document.getElementById('pc');
      if (placaLegado) {
        placaLegado.textContent = placa || '';
      }

      var placaInput = document.getElementById('pc2');
      if (placaInput) {
        placaInput.value = placa || '';
      }
    };
  })();
</script>
