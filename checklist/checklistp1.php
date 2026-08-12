<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
$placa = strtoupper(trim((string) ($_POST['placa'] ?? $_GET['placa'] ?? '')));
$veiculo = [];
if ($placa !== '' && $con instanceof mysqli && preg_match('/^[A-Za-z0-9_]+$/', $databaseName) === 1) {
  $stmtVeiculo = mysqli_prepare($con, "SELECT placa, modelo, anofabr, unidade, basegestao, statusvel, matcond FROM `{$databaseName}`.`tbveiculo` WHERE placa = ? LIMIT 1");
  if ($stmtVeiculo) {
    mysqli_stmt_bind_param($stmtVeiculo, 's', $placa);
    mysqli_stmt_execute($stmtVeiculo);
    $veiculo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtVeiculo)) ?: [];
  }
}
$valorVeiculo = static fn(string $campo): string => htmlspecialchars((string) ($veiculo[$campo] ?? ''), ENT_QUOTES, 'UTF-8');
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

    <form id="checklist-p1-form" method="post" action="./control/checklistp1.php">
      <section class="card checklist-section my-4">
        <div class="card-header py-3"><h2 class="h5 mb-0"><i class="fas fa-car me-2 text-success"></i>Informações do veículo</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4">
            <label class="form-label" for="placa">Placa</label>
            <input class="form-control text-uppercase" id="placa" name="placa" value="<?= htmlspecialchars($placa, ENT_QUOTES, 'UTF-8') ?>" readonly>
          </div>
          <div class="col-md-5">
            <label class="form-label" for="modelo">Modelo</label>
            <input class="form-control" id="modelo" name="modelo" placeholder="Modelo do veículo" value="<?= $valorVeiculo('modelo') ?>" readonly>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="ano">Ano</label>
            <input class="form-control" id="ano" name="anofabricacao" inputmode="numeric" placeholder="0000" value="<?= $valorVeiculo('anofabr') ?>" readonly>
          </div>
          <div class="col-md-4"><label class="form-label" for="unidade">Unidade / base de gestão</label><input class="form-control" id="unidade" name="unidade" value="<?= $valorVeiculo('basegestao') ?: $valorVeiculo('unidade') ?>" readonly></div>
        </div>
      </section>

      <section class="card checklist-section mb-4">
        <div class="card-header py-3"><h2 class="h5 mb-0"><i class="fas fa-id-card me-2 text-success"></i>Informações do condutor</h2></div>
        <div class="card-body row g-3">
          <div class="col-12"><div class="alert alert-secondary py-2 mb-0"><i class="fas fa-magnifying-glass me-1"></i>Informe o CPF ou a matrícula e clique em <strong>Buscar</strong> para carregar os dados do condutor.</div></div>
          <div class="col-md-4"><label class="form-label" for="cpf">CPF</label><input class="form-control" id="cpf" name="cpf" placeholder="000.000.000-00"></div>
          <div class="col-md-4"><label class="form-label" for="matricula">Matrícula</label><input class="form-control" id="matricula" name="matricula"></div>
          <div class="col-md-4 d-flex align-items-end"><button class="btn btn-outline-success w-100" id="buscar-condutor" type="button"><i class="fas fa-search me-1"></i>Buscar condutor</button></div>
          <div class="col-12 d-none" id="mensagem-condutor" role="alert"></div>
          <div class="col-md-8"><label class="form-label" for="nome">Nome</label><input class="form-control" id="nome" name="nome" placeholder="Nome completo"></div>
          <div class="col-md-4"><label class="form-label" for="centrocusto">Centro de custo</label><input class="form-control" id="centrocusto" name="centrocusto"></div>
          <div class="col-md-4"><label class="form-label" for="cnh">CNH</label><input class="form-control" id="cnh" name="cnh"></div>
          <div class="col-md-4"><label class="form-label" for="categoria-cnh">Categoria da CNH</label><input class="form-control" id="categoria-cnh" name="categoriacnh"></div>
          <div class="col-md-4"><label class="form-label" for="validade-cnh">Validade da CNH</label><input class="form-control" type="date" id="validade-cnh" name="validadecnh"></div>
        </div>
      </section>

      <section class="card checklist-section mb-4">
        <div class="card-header py-3"><h2 class="h5 mb-0"><i class="fas fa-clipboard-check me-2 text-success"></i>Informações da vistoria</h2></div>
        <div class="card-body row g-3">
          <div class="col-md-4"><label class="form-label" for="vistoriador">Vistoriador</label><input class="form-control" id="vistoriador" name="vistoriador" value="<?= htmlspecialchars((string) ($autofrota['usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly><input type="hidden" name="matrvistoriador" value="<?= htmlspecialchars((string) ($autofrota['matricula'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
          <div class="col-md-4"><label class="form-label" for="tipo">Tipo</label><select class="form-select" id="tipo" name="tipo"><option value="" selected disabled>Selecione</option><option value="2">Recebimento</option><option value="3">Devolução</option><option value="1">Periódica</option></select></div>
          <div class="col-md-4"><label class="form-label" for="data-vistoria">Data</label><input class="form-control" type="date" id="data-vistoria" name="datavistoria"></div>
          <div class="col-md-4"><label class="form-label" for="hora-vistoria">Hora</label><input class="form-control" type="time" id="hora-vistoria" name="horavistoria"></div>
          <div class="col-md-4"><label class="form-label" for="estado-geral">Estado geral</label><select class="form-select" id="estado-geral" name="estado"><option value="" selected disabled>Selecione</option><option>Ótimo</option><option>Bom</option><option>Regular</option><option>Ruim</option><option>Péssimo</option></select></div>
          <div class="col-md-4"><label class="form-label" for="hodometro">Hodômetro</label><div class="input-group"><input class="form-control" type="number" min="0" id="hodometro" name="hodometro"><span class="input-group-text">km</span></div></div>
          <div class="col-md-4"><label class="form-label" for="tanque">Nível do tanque</label><select class="form-select" id="tanque" name="niveltanque"><option value="" selected disabled>Selecione</option><option>Reserva</option><option>1/4</option><option>1/2</option><option>3/4</option><option>Cheio</option></select></div>
          <div class="col-md-4"><label class="form-label" for="statusveic">Status do veículo</label><input class="form-control" id="statusveic" name="statusveic" value="<?= $valorVeiculo('statusvel') ?>" readonly></div>
          <div class="col-md-4"><label class="form-label" for="avaria">Possui avaria?</label><select class="form-select" id="avaria" name="avaria"><option value="">Selecione</option><option value="1">Sim</option><option value="0">Não</option></select></div>
          <div class="col-md-4"><label class="form-label" for="documentacao">Documentação</label><select class="form-select" id="documentacao" name="documentacao"><option value="">Selecione</option><option value="ok">OK</option><option value="naook">Não OK</option></select></div>
        </div>
      </section>

      <section class="card checklist-section mb-4">
        <div class="card-header py-3"><h2 class="h5 mb-0"><i class="fas fa-screwdriver-wrench me-2 text-success"></i>Itens completos da vistoria</h2></div>
        <div class="card-body row g-3">
          <?php
          $gruposItens = [
            'Teto' => ['teto'=>'Teto','tetoesp'=>'Espelho do teto','tetoesq'=>'Teto esquerdo','tetodir'=>'Teto direito'],
            'Frente' => ['frente'=>'Frente','capo'=>'Capô','parabrisa'=>'Para-brisa','farolesq'=>'Farol esquerdo','faroldir'=>'Farol direito','parachoque'=>'Para-choque dianteiro','grade'=>'Grade'],
            'Lateral esquerda' => ['latesq'=>'Lateral esquerda','paralamaesq'=>'Para-lama esquerdo','retrovesq'=>'Retrovisor esquerdo','cxaresq'=>'Caixa de ar esquerda','ptdiantesq'=>'Porta dianteira esquerda','pttrasesq'=>'Porta traseira esquerda'],
            'Lateral direita' => ['latdir'=>'Lateral direita','paralamadir'=>'Para-lama direito','retrovdir'=>'Retrovisor direito','cxardir'=>'Caixa de ar direita','ptdiantdir'=>'Porta dianteira direita','pttrasdir'=>'Porta traseira direita'],
            'Traseira' => ['traseira'=>'Traseira','lantesq'=>'Lanterna esquerda','lantdir'=>'Lanterna direita','tmpmala'=>'Tampa do porta-malas','parachoquet'=>'Para-choque traseiro'],
            'Interior' => ['itinterno'=>'Itens internos','painel'=>'Painel','som'=>'Som','bancos'=>'Bancos','ilumint'=>'Iluminação interna','tmpbag'=>'Tampa do bagageiro','retrovint'=>'Retrovisor interno','tapetes'=>'Tapetes'],
            'Pneus e acessórios' => ['pneus'=>'Pneus','step'=>'Estepe','marcapneus'=>'Marca dos pneus','kitstep'=>'Kit estepe','calotas'=>'Calotas','bateria'=>'Bateria','safecar'=>'SafeCar','limpint'=>'Limpeza interna','limpext'=>'Limpeza externa'],
          ];
          foreach ($gruposItens as $grupo => $itens): ?>
            <div class="col-12"><h3 class="h6 border-bottom pb-2 mb-0"><?= htmlspecialchars($grupo, ENT_QUOTES, 'UTF-8') ?></h3></div>
            <?php foreach ($itens as $campo => $rotulo): ?>
              <div class="col-md-4"><label class="form-label" for="<?= $campo ?>"><?= htmlspecialchars($rotulo, ENT_QUOTES, 'UTF-8') ?></label><select class="form-select" id="<?= $campo ?>" name="<?= $campo ?>"><option value="ok" selected>OK</option><option value="naook">Não OK</option></select></div>
            <?php endforeach; ?>
          <?php endforeach; ?>
          <div class="col-12"><label class="form-label" for="observacoes">Observações</label><textarea class="form-control" id="observacoes" name="observacao" rows="3" placeholder="Descreva avarias ou outras observações"></textarea></div>
        </div>
      </section>

      <div class="alert alert-info"><i class="fas fa-info-circle me-1"></i>Ao continuar, os dados informados serão salvos.</div>
      <div class="d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="./checklistinicio.php">Cancelar</a>
        <button class="btn btn-success px-4" type="submit">Continuar <i class="fas fa-arrow-right ms-1"></i></button>
      </div>
    </form>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
  <script>
    const mostrarMensagemCondutor = function (texto, tipo) {
      const mensagem = document.getElementById('mensagem-condutor');
      mensagem.className = 'col-12 alert alert-' + tipo;
      mensagem.textContent = texto;
    };

    const buscarCondutor = async function () {
      const botao = document.getElementById('buscar-condutor');
      const parametros = new URLSearchParams({
        cpf: document.getElementById('cpf').value,
        matricula: document.getElementById('matricula').value
      });
      botao.disabled = true;
      mostrarMensagemCondutor('Buscando condutor...', 'info');
      try {
        const resposta = await fetch('./control/buscar-condutor.php?' + parametros.toString(), { headers: { Accept: 'application/json' } });
        const resultado = await resposta.json();
        if (!resposta.ok || !resultado.ok) throw new Error(resultado.message || 'Condutor não encontrado.');
        const condutor = resultado.condutor;
        document.getElementById('cpf').value = condutor.cpf || document.getElementById('cpf').value;
        document.getElementById('matricula').value = condutor.matricula || '';
        document.getElementById('nome').value = condutor.nome || '';
        document.getElementById('centrocusto').value = condutor.ccusto || '';
        document.getElementById('cnh').value = condutor.cnh || '';
        document.getElementById('categoria-cnh').value = condutor.categoriacnh || '';
        document.getElementById('validade-cnh').value = condutor.validadecnh || '';
        ['cpf', 'matricula', 'nome', 'centrocusto', 'cnh', 'categoria-cnh', 'validade-cnh'].forEach(function (campo) {
          document.getElementById(campo).readOnly = true;
        });
        botao.classList.add('d-none');
        mostrarMensagemCondutor('Dados do condutor carregados com sucesso.', 'success');
      } catch (erro) {
        mostrarMensagemCondutor(erro.message, 'warning');
      } finally {
        botao.disabled = false;
      }
    };
    document.getElementById('buscar-condutor').addEventListener('click', buscarCondutor);
    <?php if (!empty($veiculo['matcond'])): ?>
      document.getElementById('matricula').value = <?= json_encode((string) $veiculo['matcond']) ?>;
      buscarCondutor();
    <?php endif; ?>
  </script>

</body>
</html>