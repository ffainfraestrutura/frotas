<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();

$conn = $GLOBALS['conn'] ?? null;
$databaseName = $GLOBALS['databaseName'] ?? 'bdautofrotas';
$databaseCorp = $GLOBALS['databaseCorp'] ?? 'bdcorp';

if (!$conn instanceof mysqli) {
    http_response_code(500);
    exit('Conexão com o banco indisponível.');
}

$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '0');
if ($perfilLogado === '0' || $perfilLogado === '') {
    http_response_code(403);
    exit('Sem permissão.');
}

mysqli_set_charset($conn, 'utf8mb4');

function escSaldo($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function moedaSaldo($valor): string
{
    return number_format((float) $valor, 2, ',', '.');
}

function inicioSemanaSaldo(): string
{
    return date('Y-m-d', strtotime(date('N') === '1' ? 'today' : 'last monday'));
}

function buscarLinhasSaldo(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException(mysqli_error($conn));
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function lerFiltroSaldo(string $nome, string $padrao = ''): string
{
    if (isset($_GET[$nome])) {
        return trim((string) $_GET[$nome]);
    }
    if (isset($_POST[$nome])) {
        return trim((string) $_POST[$nome]);
    }
    return $padrao;
}

$ccustoFiltro = lerFiltroSaldo('ccusto', 'td');
$cargoFiltro = lerFiltroSaldo('cargo', 'td');
$filialFiltro = lerFiltroSaldo('filial', 'td');
$matriculaFiltro = lerFiltroSaldo('mattec');
$placaFiltro = strtoupper(lerFiltroSaldo('placatec'));
$inicioSemana = inicioSemanaSaldo();

$centrosCusto = buscarLinhasSaldo($conn, "SELECT DISTINCT COALESCE(ccusto, 'Sem centro de custo') AS ccusto FROM `{$databaseCorp}`.`tbfuncionario` WHERE (ccusto LIKE '%IHS%' OR ccusto LIKE '%CLARO%' OR ccusto LIKE '%Alloha%' OR ccusto LIKE '%Controle e Eficiência Operacional%' OR ccusto IS NULL) ORDER BY ccusto");
$cargos = buscarLinhasSaldo($conn, "SELECT DISTINCT cargo FROM `{$databaseCorp}`.`tbfuncionario` WHERE status <> 'demitido' AND cargo <> '' AND cargo NOT REGEXP '^[0-9]+$' ORDER BY cargo");
$filiais = buscarLinhasSaldo($conn, "SELECT DISTINCT unidade FROM `{$databaseName}`.`tbveiculo` WHERE unidade <> '' ORDER BY unidade");

$veiculos = [];
$temFiltro = $ccustoFiltro !== 'td' || $cargoFiltro !== 'td' || $filialFiltro !== 'td' || $matriculaFiltro !== '' || $placaFiltro !== '';
if ($temFiltro) {
    $where = ["COALESCE(v.visivel, 1) = 1", "COALESCE(v.status, 1) = 1", "COALESCE(f.status, '') <> 'demitido'", "COALESCE(v.statusvel, '') NOT IN ('INDISPONÍVEL', 'SINISTRO/MANUTENÇÃO', 'EM DESMOBILIZAÇÃO', 'RESERVADO: PROJETO')"];
    $types = 's';
    $params = [$inicioSemana];
    if ($ccustoFiltro !== 'td') { $where[] = "COALESCE(f.ccusto, 'Sem centro de custo') = ?"; $types .= 's'; $params[] = $ccustoFiltro; }
    if ($cargoFiltro !== 'td') { $where[] = 'f.cargo = ?'; $types .= 's'; $params[] = $cargoFiltro; }
    if ($filialFiltro !== 'td') { $where[] = 'v.unidade = ?'; $types .= 's'; $params[] = $filialFiltro; }
    if ($matriculaFiltro !== '') { $where[] = 'f.matricula = ?'; $types .= 's'; $params[] = $matriculaFiltro; }
    if ($placaFiltro !== '') { $where[] = 'v.placa = ?'; $types .= 's'; $params[] = $placaFiltro; }

    $sqlVeiculos = "SELECT v.placa, v.unidade, COALESCE(f.matricula, '') AS matricula, COALESCE(f.nome, '') AS nome,
                           COALESCE(supervisor.nome, '') AS supervisor_nome, COALESCE(f.cargo, '') AS cargo,
                           COALESCE(f.ccusto, '') AS ccusto, COALESCE(sa.idtbsaldo, 0) AS idtbsaldo,
                           COALESCE(sa.saldo, sa.saldo_real_calculado, sa.valoraplicado, 0) AS saldo_cartao,
                           COALESCE(sa.orcsemanal, 0) AS orcsemanal, COALESCE(sa.totalextra, 0) AS totalextra,
                           COALESCE(sa.valoraplicado, sa.saldo, 0) AS valoraplicado, COALESCE(sa.kmproj, sa.kmorcsem, 0) AS kmproj
                      FROM `{$databaseName}`.`tbveiculo` v
                 LEFT JOIN `{$databaseCorp}`.`tbfuncionario` f ON v.matcond = f.matricula
                 LEFT JOIN `{$databaseName}`.`tbsaldo` sa ON sa.matricula = v.matcond AND sa.data = ?
                 LEFT JOIN `{$databaseCorp}`.`tbusuario` u ON f.matricula = u.matricula
                 LEFT JOIN `{$databaseCorp}`.`tbsupervisor` s ON u.idtbsupervisor = s.idtbsupervisor
                 LEFT JOIN `{$databaseCorp}`.`tbusuario` supervisor ON s.matricula = supervisor.matricula
                     WHERE " . implode(' AND ', $where) . "
                  ORDER BY f.nome, v.placa";
    $veiculos = buscarLinhasSaldo($conn, $sqlVeiculos, $types, $params);
}

$mensagemRetorno = (string) ($_SESSION['rel_saldo_msg'] ?? '');
unset($_SESSION['rel_saldo_msg']);
$alertaDetalhesSaldo = (string) ($_SESSION['rel_saldo_alert_detalhes'] ?? '');
unset($_SESSION['rel_saldo_alert_detalhes']);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Inserir Saldo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="src/css/styles.css" rel="stylesheet" />
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
</head>
<body class="sb-nav-fixed">
<?php autofrotaMenu(); ?>
<div id="layoutSidenav_content">
<main class="container-fluid px-4 py-3">
    <h1 class="h3 mb-3">Inserir Saldo</h1>
    <?php if ($mensagemRetorno !== ''): ?>
        <div class="alert <?= stripos($mensagemRetorno, 'sucesso') !== false ? 'alert-success' : 'alert-warning' ?>" role="alert"><?= escSaldo($mensagemRetorno) ?></div>
    <?php endif; ?>
    <div class="alert alert-secondary">Informe ao menos um filtro para carregar os veículos. A referência de saldo é a semana iniciada em <strong><?= escSaldo(date('d/m/Y', strtotime($inicioSemana))) ?></strong>.</div>
    <form id="formFiltrosSaldo" method="get" class="card card-body mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-3"><label class="form-label">Centro de Custo</label><select class="form-select" name="ccusto"><option value="td">Todos</option><?php foreach ($centrosCusto as $item): $v=(string)$item['ccusto']; ?><option value="<?= escSaldo($v) ?>" <?= $ccustoFiltro===$v?'selected':'' ?>><?= escSaldo($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label">Cargo</label><select class="form-select" name="cargo"><option value="td">Todos</option><?php foreach ($cargos as $item): $v=(string)$item['cargo']; ?><option value="<?= escSaldo($v) ?>" <?= $cargoFiltro===$v?'selected':'' ?>><?= escSaldo($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Estado</label><select class="form-select" name="filial"><option value="td">Todos</option><?php foreach ($filiais as $item): $v=(string)$item['unidade']; ?><option value="<?= escSaldo($v) ?>" <?= $filialFiltro===$v?'selected':'' ?>><?= escSaldo($v) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Matrícula</label><input class="form-control" name="mattec" value="<?= escSaldo($matriculaFiltro) ?>"></div>
            <div class="col-md-2"><label class="form-label">Placa</label><input class="form-control text-uppercase" name="placatec" value="<?= escSaldo($placaFiltro) ?>"></div>
            <div class="col-12"><button class="btn btn-success" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button> <a class="btn btn-outline-secondary" href="inicio.php">Voltar</a> <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalJustificativas"><i class="fa-solid fa-list"></i> Histórico de Justificativas</button></div>
        </div>
    </form>
    <div class="card mb-4"><div class="card-body table-responsive">
        <table id="datatablesSimple" class="table table-bordered table-hover align-middle">
            <thead><tr><th>Placa</th><th>Matrícula</th><th>Nome</th><th>Supervisor</th><th>Cargo</th><th>Centro de Custo</th><th>Saldo Cartão</th><th>Cota Inicial</th><th>Total Cota Extra</th><th>Total Cota Recebida</th><th>Tipo de Ação</th><th>Justificativa</th></tr></thead>
            <tbody>
            <?php if (!$temFiltro): ?><tr><td colspan="12" class="text-center text-muted">Use os filtros para pesquisar.</td></tr><?php endif; ?>
            <?php foreach ($veiculos as $row): $formId='form_'.preg_replace('/[^a-zA-Z0-9_]/','_', (string)$row['placa']); $justId='just_'.$formId; ?>
                <tr>
                    <td><?= escSaldo($row['placa']) ?></td><td><?= escSaldo($row['matricula']) ?></td><td class="text-nowrap"><?= escSaldo($row['nome']) ?></td><td><?= escSaldo($row['supervisor_nome']) ?></td><td><?= escSaldo($row['cargo']) ?></td><td><?= escSaldo($row['ccusto']) ?></td>
                    <td>R$ <?= moedaSaldo($row['saldo_cartao']) ?></td><td>R$ <?= moedaSaldo($row['orcsemanal']) ?></td><td>R$ <?= moedaSaldo($row['totalextra']) ?></td><td>R$ <?= moedaSaldo($row['valoraplicado']) ?></td>
                    <td style="min-width:220px"><?php $semSaldoSemanal = (int) ($row['idtbsaldo'] ?? 0) <= 0; ?><form id="<?= escSaldo($formId) ?>" action="control/remanejamentofrota.php" method="post" onsubmit="return validarFormulario('<?= escSaldo($justId) ?>')"><input type="hidden" name="matriculatec" value="<?= escSaldo($row['matricula']) ?>"><input type="hidden" name="idtbsaldo" value="<?= escSaldo($row['idtbsaldo']) ?>"><input type="hidden" name="placa" value="<?= escSaldo($row['placa']) ?>"><input type="hidden" name="saldoatual" value="<?= moedaSaldo($row['saldo_cartao']) ?>"><input type="hidden" name="unidade" value="<?= escSaldo($row['unidade']) ?>"><div class="form-check"><input class="form-check-input" type="radio" name="tipoacao" value="1" required><label class="form-check-label">Adicionar Saldo</label></div><div class="form-check mb-2"><input class="form-check-input" type="radio" name="tipoacao" value="0"><label class="form-check-label">Remover Saldo</label></div><div class="input-group input-group-sm"><input class="form-control" name="valor" placeholder="Valor" required><button class="btn btn-outline-success" type="submit">&gt;</button></div><?php if ($semSaldoSemanal): ?><small class="text-warning d-block mt-1">Sem saldo semanal. Ao enviar, será usado o último saldo disponível.</small><?php endif; ?></form></td>
                    <td style="min-width:280px"><textarea id="<?= escSaldo($justId) ?>" class="form-control form-control-sm" name="justificativa" rows="3" form="<?= escSaldo($formId) ?>" maxlength="500" required placeholder="Justificativa obrigatória (mín. 10 caracteres)"></textarea></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <button id="btnExportExcel" type="button" class="btn btn-success mt-2">Gerar Excel</button>
    </div></div>
</main>
</div>
<div class="modal fade" id="modalJustificativas" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Histórico de Justificativas de Saldo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-2 mb-3"><div class="col-md-3"><input id="filtroMatricula" class="form-control" placeholder="Matrícula"></div><div class="col-md-3"><input id="filtroPlaca" class="form-control" placeholder="Placa"></div><div class="col-md-2"><select id="filtroTipoAcao" class="form-select"><option value="">Todas</option><option value="1">Adição</option><option value="0">Remoção</option></select></div><div class="col-md-2"><input id="filtroDataInicio" type="date" class="form-control"></div><div class="col-md-2"><input id="filtroDataFim" type="date" class="form-control"></div><div class="col-12 text-end"><button class="btn btn-primary" onclick="buscarJustificativas()">Buscar</button></div></div><div class="table-responsive" style="max-height:500px"><table class="table table-striped table-bordered"><thead><tr><th>N°</th><th>Data/Hora</th><th>Tipo</th><th>Placa</th><th>Mat. Técnico</th><th>Valor</th><th>Mat. Autor</th><th>Justificativa</th></tr></thead><tbody id="corpoTabelaJustificativas"><tr><td colspan="8" class="text-center text-muted">Utilize os filtros acima.</td></tr></tbody></table></div></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
<?php if ($alertaDetalhesSaldo !== ''): ?>
alert(<?= json_encode($alertaDetalhesSaldo, JSON_UNESCAPED_UNICODE) ?>);
<?php endif; ?>
const houveRetornoRemanejamento = <?= $mensagemRetorno !== '' ? 'true' : 'false' ?>;
const storageFiltroSaldo = 'rel_saldo_veiculos_filtros';
const queryAtual = window.location.search.replace(/^\?/, '');
if (queryAtual) {
    localStorage.setItem(storageFiltroSaldo, queryAtual);
} else if (houveRetornoRemanejamento) {
    const querySalva = (localStorage.getItem(storageFiltroSaldo) || '').trim();
    if (querySalva) {
        window.location.replace('relatorio-saldo-veiculos.php?' + querySalva);
    }
}

function validarFormulario(id){const v=(document.getElementById(id)?.value||'').trim(); if(v.length<10){alert('Informe uma justificativa com pelo menos 10 caracteres.'); return false;} return true;}
document.getElementById('btnExportExcel').addEventListener('click',()=>{const html='\ufeff'+document.getElementById('datatablesSimple').outerHTML; const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([html],{type:'application/vnd.ms-excel;charset=utf-8'})); a.download='rel_saldo_veiculos.xls'; a.click();});
function buscarJustificativas(){ $.post('control/buscar_justificativas.php',{matricula:$('#filtroMatricula').val(),placa:$('#filtroPlaca').val(),tipo_acao:$('#filtroTipoAcao').val(),data_inicio:$('#filtroDataInicio').val(),data_fim:$('#filtroDataFim').val()},function(dados){let html=''; if(!Array.isArray(dados)||!dados.length){html='<tr><td colspan="8" class="text-center text-muted">Nenhum registro encontrado</td></tr>';} else {dados.forEach(function(i){html+='<tr><td>'+i.id+'</td><td>'+i.data_hora_formatada+'</td><td>'+(i.tipo_acao==1?'Adição':'Remoção')+'</td><td>'+i.placa+'</td><td>'+i.matricula_tecnico+'</td><td>R$ '+Number(i.valor||0).toLocaleString('pt-BR',{minimumFractionDigits:2})+'</td><td>'+i.matricula_autor+'</td><td>'+i.justificativa+'</td></tr>';});} $('#corpoTabelaJustificativas').html(html);},'json').fail(function(xhr){$('#corpoTabelaJustificativas').html('<tr><td colspan="8" class="text-center text-danger">Erro ao buscar justificativas.</td></tr>');});}
document.getElementById('modalJustificativas').addEventListener('shown.bs.modal', buscarJustificativas);
</script>
</body></html>
