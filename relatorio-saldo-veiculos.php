<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/autofrota_common.php';

/**
 * Consulta o cartao ativo de uma placa na TicketLog.
 *
 * A credencial e os codigos da integracao sao carregados da tabela
 * tbintegracao_ticketlog. Consulte docs/ticketlog-configuracao.md.
 *
 * @return array{sucesso: bool, saldo: ?float, numero_cartao: string, mensagem: string}
 */
function consultarSaldoTicketLogPorPlaca(mysqli $conn, string $databaseName, string $placa): array
{
    $placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($placa)) ?? '');

    if ($placa === '') {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'Placa não informada.'];
    }
    if (!function_exists('curl_init')) {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'Extensão cURL indisponível.'];
    }

    $configuracao = buscarConfiguracaoTicketLog($conn, $databaseName);
    if (!$configuracao['sucesso']) {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => $configuracao['mensagem']];
    }

    $credencial = $configuracao['basic_auth'];
    $codigoCliente = $configuracao['codigo_cliente'];
    $codigoProduto = $configuracao['codigo_produto'];

    $payload = json_encode([
        'codigoCliente' => $codigoCliente,
        'codigoProduto' => $codigoProduto,
        'placaVeiculo' => $placa,
        'situacaoCartao' => 'A',
        'ordem' => 'C',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $curl = curl_init('https://srv1.ticketlog.com.br/ticketlog-servicos/ebs/relatorioExtratoSimplificado/search');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credencial,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $resposta = curl_exec($curl);
    $erroCurl = curl_error($curl);
    $statusHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($resposta === false || $erroCurl !== '') {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'Falha de comunicação com a TicketLog.'];
    }

    $dados = json_decode($resposta, true);
    if ($statusHttp < 200 || $statusHttp >= 300 || !is_array($dados) || ($dados['sucesso'] ?? false) !== true) {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'A TicketLog recusou a consulta.'];
    }

    foreach (($dados['itens'] ?? []) as $item) {
        if (!is_array($item) || strtoupper((string) ($item['situacao'] ?? '')) !== 'A') {
            continue;
        }

        return [
            'sucesso' => true,
            'saldo' => isset($item['saldo']) ? (float) $item['saldo'] : 0.0,
            'numero_cartao' => (string) ($item['numeroCartao'] ?? ''),
            'mensagem' => '',
        ];
    }

    return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'Nenhum cartão ativo encontrado para a placa.'];
}

/**
 * @return array{sucesso: bool, basic_auth: string, codigo_cliente: int, codigo_produto: int, mensagem: string}
 */
function buscarConfiguracaoTicketLog(mysqli $conn, string $databaseName): array
{
    static $cache = [];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
        return ['sucesso' => false, 'basic_auth' => '', 'codigo_cliente' => 0, 'codigo_produto' => 0, 'mensagem' => 'Banco da configuração TicketLog inválido.'];
    }

    $cacheKey = spl_object_id($conn) . ':' . $databaseName;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = "SELECT basic_auth, codigo_cliente, codigo_produto
              FROM `{$databaseName}`.`tbintegracao_ticketlog`
             WHERE ativo = 1
             ORDER BY idtbintegracao_ticketlog DESC
             LIMIT 1";
    $resultado = mysqli_query($conn, $sql);
    if (!$resultado) {
        $cache[$cacheKey] = ['sucesso' => false, 'basic_auth' => '', 'codigo_cliente' => 0, 'codigo_produto' => 0, 'mensagem' => 'Configuração TicketLog indisponível.'];
        return $cache[$cacheKey];
    }

    $linha = mysqli_fetch_assoc($resultado) ?: [];
    mysqli_free_result($resultado);

    $basicAuth = trim((string) ($linha['basic_auth'] ?? ''));
    $codigoCliente = (int) ($linha['codigo_cliente'] ?? 0);
    $codigoProduto = (int) ($linha['codigo_produto'] ?? 0);
    if ($basicAuth === '' || $codigoCliente <= 0 || $codigoProduto <= 0) {
        $cache[$cacheKey] = ['sucesso' => false, 'basic_auth' => '', 'codigo_cliente' => 0, 'codigo_produto' => 0, 'mensagem' => 'Configuração TicketLog não cadastrada.'];
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = [
        'sucesso' => true,
        'basic_auth' => $basicAuth,
        'codigo_cliente' => $codigoCliente,
        'codigo_produto' => $codigoProduto,
        'mensagem' => '',
    ];

    return $cache[$cacheKey];
}

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

    foreach ($veiculos as &$veiculo) {
        $consultaTicketLog = consultarSaldoTicketLogPorPlaca($conn, $databaseName, (string) ($veiculo['placa'] ?? ''));
        $veiculo['saldo_cartao_api'] = $consultaTicketLog['saldo'];
        $veiculo['numero_cartao'] = $consultaTicketLog['numero_cartao'];
        $veiculo['erro_saldo_cartao'] = $consultaTicketLog['sucesso'] ? '' : $consultaTicketLog['mensagem'];
    }
    unset($veiculo);
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
                    <?php $saldoCartaoApi = $row['saldo_cartao_api'] ?? null; $erroSaldoCartao = (string) ($row['erro_saldo_cartao'] ?? ''); ?>
                    <td><?php if ($saldoCartaoApi !== null): ?><span title="Saldo consultado na TicketLog">R$ <?= moedaSaldo($saldoCartaoApi) ?></span><?php else: ?><span class="text-warning" title="<?= escSaldo($erroSaldoCartao) ?>">Indisponível</span><?php endif; ?></td><td>R$ <?= moedaSaldo($row['orcsemanal']) ?></td><td>R$ <?= moedaSaldo($row['totalextra']) ?></td><td>R$ <?= moedaSaldo($row['valoraplicado']) ?></td>
                    <td style="min-width:220px"><?php $semSaldoSemanal = (int) ($row['idtbsaldo'] ?? 0) <= 0; $consultaDisponivel = $saldoCartaoApi !== null && !empty($row['numero_cartao']); ?><form id="<?= escSaldo($formId) ?>" action="control/remanejamentofrota.php" method="post" onsubmit="return validarFormulario('<?= escSaldo($justId) ?>', this)"><input type="hidden" name="matriculatec" value="<?= escSaldo($row['matricula']) ?>"><input type="hidden" name="idtbsaldo" value="<?= escSaldo($row['idtbsaldo']) ?>"><input type="hidden" name="placa" value="<?= escSaldo($row['placa']) ?>"><input type="hidden" name="numeroCartao" value="<?= escSaldo($row['numero_cartao'] ?? '') ?>"><input type="hidden" name="saldoatual" value="<?= escSaldo($saldoCartaoApi ?? '') ?>"><input type="hidden" name="unidade" value="<?= escSaldo($row['unidade']) ?>"><input type="hidden" name="tipoacao" value="1"><div class="mb-2 fw-semibold text-success"><i class="fa-solid fa-plus-circle"></i> Adicionar Saldo</div><div class="input-group input-group-sm"><span class="input-group-text">R$</span><input type="number" class="form-control" name="valor" placeholder="0,01" min="0.01" step="0.01" inputmode="decimal" required><button class="btn btn-outline-success" type="submit" <?= $consultaDisponivel ? '' : 'disabled' ?>>Adicionar</button></div><small class="text-muted d-block mt-1">Valor mínimo: R$ 0,01. Remoção bloqueada.</small><?php if ($semSaldoSemanal): ?><small class="text-warning d-block mt-1">Sem saldo semanal. Ao enviar, será usado o último saldo disponível.</small><?php endif; ?></form></td>
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

function validarFormulario(id, form){const v=(document.getElementById(id)?.value||'').trim(); if(v.length<10){alert('Informe uma justificativa com pelo menos 10 caracteres.'); return false;} const campo=form.querySelector('[name="valor"]'); const valor=Number(campo?.value||0); if(!Number.isFinite(valor)||valor<0.01){alert('Informe um valor a partir de R$ 0,01.'); return false;} const botao=form.querySelector('button[type="submit"]'); if(botao){botao.disabled=true; botao.textContent='Processando...';} return true;}
document.getElementById('btnExportExcel').addEventListener('click',()=>{const html='\ufeff'+document.getElementById('datatablesSimple').outerHTML; const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([html],{type:'application/vnd.ms-excel;charset=utf-8'})); a.download='rel_saldo_veiculos.xls'; a.click();});
function buscarJustificativas(){ $.post('control/buscar_justificativas.php',{matricula:$('#filtroMatricula').val(),placa:$('#filtroPlaca').val(),tipo_acao:$('#filtroTipoAcao').val(),data_inicio:$('#filtroDataInicio').val(),data_fim:$('#filtroDataFim').val()},function(dados){let html=''; if(!Array.isArray(dados)||!dados.length){html='<tr><td colspan="8" class="text-center text-muted">Nenhum registro encontrado</td></tr>';} else {dados.forEach(function(i){html+='<tr><td>'+i.id+'</td><td>'+i.data_hora_formatada+'</td><td>'+(i.tipo_acao==1?'Adição':'Remoção')+'</td><td>'+i.placa+'</td><td>'+i.matricula_tecnico+'</td><td>R$ '+Number(i.valor||0).toLocaleString('pt-BR',{minimumFractionDigits:2})+'</td><td>'+i.matricula_autor+'</td><td>'+i.justificativa+'</td></tr>';});} $('#corpoTabelaJustificativas').html(html);},'json').fail(function(xhr){$('#corpoTabelaJustificativas').html('<tr><td colspan="8" class="text-center text-danger">Erro ao buscar justificativas.</td></tr>');});}
document.getElementById('modalJustificativas').addEventListener('shown.bs.modal', buscarJustificativas);
</script>
</body></html>