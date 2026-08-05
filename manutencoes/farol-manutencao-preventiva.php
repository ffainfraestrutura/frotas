<?php
// Farol Manutenção Preventiva migrado do legado 3/frotas/gestao/farolmanutencaopreventiva.php.
// Funcionamento original: lista veículos ativos, compara o hodômetro atual do veículo com a última MP
// registrada em tbmanprev e classifica o farol em verde/amarelo/vermelho/cinza.
// Tabelas utilizadas: tbveiculo, tbmanprev, tbfornecedor, tbvelstatus (schema AutoFrota) e tbfuncionario (schema corporativo).
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

date_default_timezone_set('America/Sao_Paulo');

$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? '');
$matriculaLogada = (string) (($autofrotaSessao['matricula'] ?? '') !== '' ? $autofrotaSessao['matricula'] : ($_SESSION['usuario'] ?? ''));
$perfilLogado = (string) ($autofrotaSessao['perfil'] ?? '');
$matriculasSemPermissaoEdicao = ['160030', '410109', '501285', '410039', '411425', '003931'];
$podeEditar = $perfilLogado === '4' && !in_array($matriculaLogada, $matriculasSemPermissaoEdicao, true);
$linhasFarol = [];
$erroConsulta = '';

function farolData(?string $data): string
{
    $data = trim((string) ($data ?? ''));
    if ($data === '' || $data === '0000-00-00' || $data === '0000-00-00 00:00:00') {
        return '';
    }

    $timestamp = strtotime($data);
    return $timestamp ? date('d/m/Y', $timestamp) : $data;
}

function farolBotaoPost(string $action, array $campos, string $icone, string $titulo): string
{
    $inputs = '';
    foreach ($campos as $nome => $valor) {
        $inputs .= '<input type="hidden" name="' . esc($nome) . '" value="' . esc((string) $valor) . '">';
    }

    return '<form method="post" action="' . esc($action) . '" target="_blank" class="d-inline">'
        . $inputs
        . '<button style="border: none; background-color: transparent;" type="submit" title="' . esc($titulo) . '">'
        . '<span class="material-symbols-outlined">' . esc($icone) . '</span>'
        . '</button></form>';
}

function farolClassificar($hodometroAtual, $hodometroUltimaManutencao, string $statusUltimaManutencao): array
{
    if (strtoupper(trim($statusUltimaManutencao)) === 'ABERTO') {
        return ['AGENDADA', 'table-secondary'];
    }

    $diferenca = (int) $hodometroAtual - (int) $hodometroUltimaManutencao;
    if ($diferenca >= 11000) {
        return ['PERDEU A MANUTENÇÃO', 'table-danger'];
    }
    if ($diferenca >= 9000) {
        return ['ENVIAR PARA MANUTENÇÃO PREVENTIVA', 'table-warning'];
    }

    return ['EM INTERVALO', 'table-success'];
}

if (!($conn instanceof mysqli) || $databaseName === '' || $databaseCorp === '') {
    $erroConsulta = 'Não foi possível conectar ao banco configurado.';
} else {
    $sqlFarol = "
        SELECT
            vei.placa,
            vei.matcond,
            vei.hodometro AS hodometro_atual,
            vei.dtdevolucaoloc,
            vei.uf,
            vei.basegestao,
            func.nome AS nome_condutor,
            func.ccusto,
            forn.fantasia AS locadora,
            stat.status AS status_veiculo,
            man.dataocorrencia AS ultima_manutencao,
            man.hodometro AS hodometro_ultima_manutencao,
            man.observacao AS observacao_ultima_manutencao,
            man.status AS status_ultima_manutencao,
            agendada.dataagendamento AS data_ultimo_agendamento_aberto
        FROM `{$databaseName}`.`tbveiculo` vei
        LEFT JOIN `{$databaseCorp}`.`tbfuncionario` func
            ON func.matricula = vei.matcond
        LEFT JOIN `{$databaseName}`.`tbfornecedor` forn
            ON forn.idtbfornecedor = vei.idlocador
        LEFT JOIN `{$databaseName}`.`tbvelstatus` stat
            ON stat.idtbastatusvel = vei.statusvel
        LEFT JOIN `{$databaseName}`.`tbmanprev` man
            ON man.idtbmanprev = (
                SELECT MAX(man2.idtbmanprev)
                FROM `{$databaseName}`.`tbmanprev` man2
                WHERE man2.placa = vei.placa
                  AND man2.tipo = 'MP'
            )
        LEFT JOIN `{$databaseName}`.`tbmanprev` agendada
            ON agendada.idtbmanprev = (
                SELECT man3.idtbmanprev
                FROM `{$databaseName}`.`tbmanprev` man3
                WHERE man3.placa = vei.placa
                  AND man3.tipo = 'MP'
                  AND man3.status = 'ABERTO'
                ORDER BY man3.dataagendamento DESC, man3.idtbmanprev DESC
                LIMIT 1
            )
        WHERE vei.placa NOT IN ('ABC112', 'ABC1122', 'ABC1245')
          AND vei.placa <> ''
          AND vei.status = '1'
          AND COALESCE(vei.tipoposse, '') NOT IN ('AGREGADO', 'TERCEIRO', 'PARTICULAR')
        ORDER BY vei.placa ASC
    ";

    $resultado = mysqli_query($conn, $sqlFarol);
    if ($resultado) {
        while ($linha = mysqli_fetch_assoc($resultado)) {
            [$statusFarol, $classeFarol] = farolClassificar(
                $linha['hodometro_atual'] ?? 0,
                $linha['hodometro_ultima_manutencao'] ?? 0,
                (string) ($linha['status_ultima_manutencao'] ?? '')
            );
            $diferenca = (int) ($linha['hodometro_atual'] ?? 0) - (int) ($linha['hodometro_ultima_manutencao'] ?? 0);
            $placa = (string) ($linha['placa'] ?? '');
            $infoPlaca = $podeEditar ? farolBotaoPost('../veiculos/dadosplaca.php', ['placa' => $placa], 'info', 'Histórico Placa') : '';
            $acao = $podeEditar ? farolBotaoPost('cadastrar-manutencao-preventiva.php', ['placa' => $placa, 'matr_autor' => $matriculaLogada], 'edit_note', 'Cadastrar Manutenção') : '';

            $linhasFarol[] = [
                'classe' => $classeFarol,
                'placa' => esc($placa) . $infoPlaca,
                'condutor' => esc($linha['nome_condutor'] ?? ''),
                'ccusto' => esc($linha['ccusto'] ?? ''),
                'ultima_manutencao' => esc(farolData($linha['ultima_manutencao'] ?? '')),
                'hodometro_atual' => esc((string) ($linha['hodometro_atual'] ?? '0')) . ' km',
                'hodometro_ultima_manutencao' => esc((string) ($linha['hodometro_ultima_manutencao'] ?? '0')) . ' km',
                'diferenca' => esc((string) $diferenca) . ' km',
                'locadora' => esc($linha['locadora'] ?? ''),
                'devolucao_locadora' => esc(farolData($linha['dtdevolucaoloc'] ?? '')),
                'status' => esc($statusFarol),
                'acao' => $acao,
                'ultimo_agendamento_aberto' => esc(farolData($linha['data_ultimo_agendamento_aberto'] ?? '')),
                'observacao_ultima_manutencao' => esc($linha['observacao_ultima_manutencao'] ?? ''),
                'uf' => esc($linha['uf'] ?? ''),
                'basegestao' => esc($linha['basegestao'] ?? ''),
                'status_veiculo' => esc($linha['status_veiculo'] ?? ''),
            ];
        }
        mysqli_free_result($resultado);
    } else {
        $erroConsulta = mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>AutoFrota - Farol Manutenção Preventiva</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>
    <style>
        body { background: #ffffff; color: #000000; font-size: 12px; }
        .page-title { font-size: 24px; font-weight: 600; margin: 0 0 10px; }
        .legend-dot { display: inline-block; width: 14px; height: 14px; border-radius: 50%; margin-right: 5px; vertical-align: -2px; }
        table.dataTable thead th { color: #000000; font-size: 12px; vertical-align: bottom; }
        .dataTables_wrapper { font-size: 12px; }
        .table td, .table th { vertical-align: middle; white-space: nowrap; }
        .table td:nth-child(13) { white-space: normal; min-width: 240px; }
    </style>
</head>
<body class="sb-nav-fixed">
    <?php autofrotaMenu(); ?>
    <div id="layoutSidenav_content">
        <main class="page-wrapper py-3">
            <div class="container-fluid px-3">
                <h1 class="page-title">Farol Manutenção Preventiva</h1>
                <p class="mb-2">Acompanhe o intervalo de manutenção preventiva por veículo: verde até 8.999 km, amarelo entre 9.000 e 10.999 km, vermelho a partir de 11.000 km e cinza quando existe MP aberta/agendada.</p>
                <div class="d-flex flex-wrap gap-3 mb-3">
                    <span><span class="legend-dot bg-success"></span>Em intervalo</span>
                    <span><span class="legend-dot bg-warning"></span>Enviar para manutenção</span>
                    <span><span class="legend-dot bg-danger"></span>Perdeu a manutenção</span>
                    <span><span class="legend-dot bg-secondary"></span>Agendada</span>
                </div>
                <?php if ($erroConsulta !== ''): ?>
                    <div class="alert alert-warning" role="alert"><?= esc($erroConsulta) ?></div>
                <?php endif; ?>
                <div class="card mb-3">
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="filtroUf">UF</label>
                                <select class="form-select form-select-sm filtro-farol" id="filtroUf" data-column="13">
                                    <option value="">Todas</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="filtroStatusFarol">Status</label>
                                <select class="form-select form-select-sm filtro-farol" id="filtroStatusFarol" data-column="9">
                                    <option value="">Todos</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label mb-1" for="filtroStatusVeiculo">Status do veículo</label>
                                <select class="form-select form-select-sm filtro-farol" id="filtroStatusVeiculo" data-column="15">
                                    <option value="">Todos</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <section style="width: 100%; overflow-x: auto;">
                    <table id="tabelaFarol" class="table table-striped" style="width: 100%;">
                        <thead><tr><th>Placa</th><th>Condutor</th><th>Centro de Custo</th><th>Última Manutenção</th><th>Hodômetro Atual</th><th>Últ. Man. Hodômetro</th><th>Diferença</th><th>Locadora</th><th>Dev. para locadora</th><th>Status</th><th>Cadastrar manutenção</th><th>Data últ. agend. em aberto</th><th>Obs. últ. manutenção</th><th>UF</th><th>Base de gestão</th><th>Status do veículo</th></tr></thead>
                        <tbody>
                            <?php foreach ($linhasFarol as $linha): ?>
                                <tr class="<?= esc($linha['classe']) ?>"><td><?= $linha['placa'] ?></td><td><?= $linha['condutor'] ?></td><td><?= $linha['ccusto'] ?></td><td><?= $linha['ultima_manutencao'] ?></td><td><?= $linha['hodometro_atual'] ?></td><td><?= $linha['hodometro_ultima_manutencao'] ?></td><td><?= $linha['diferenca'] ?></td><td><?= $linha['locadora'] ?></td><td><?= $linha['devolucao_locadora'] ?></td><td><?= $linha['status'] ?></td><td class="text-center"><?= $linha['acao'] ?></td><td><?= $linha['ultimo_agendamento_aberto'] ?></td><td><?= $linha['observacao_ultima_manutencao'] ?></td><td><?= $linha['uf'] ?></td><td><?= $linha['basegestao'] ?></td><td><?= $linha['status_veiculo'] ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </section>
            </div>
        </main>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function(event) { event.preventDefault(); document.body.classList.toggle('sb-sidenav-toggled'); });
$(document).ready(function() {
    var tabelaFarol = $('#tabelaFarol').DataTable({
        language: {
            decimal: '',
            emptyTable: 'Não há dados disponíveis na tabela',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ entradas',
            infoEmpty: 'Mostrando 0 a 0 de 0 entradas',
            infoFiltered: '(filtrado de _MAX_ entradas totais)',
            thousands: '.',
            lengthMenu: 'Mostrar _MENU_ entradas',
            loadingRecords: 'Carregando...',
            processing: '',
            search: 'Buscar:',
            zeroRecords: 'Nenhum registro correspondente encontrado',
            paginate: { first: 'Primeiro', last: 'Último', next: 'Próximo', previous: 'Anterior' }
        }
    });

    function popularFiltro($select) {
        var coluna = tabelaFarol.column(Number($select.data('column')));
        var valores = [];
        coluna.data().each(function(valor) {
            var texto = $('<div>').html(valor).text().trim();
            if (texto !== '' && valores.indexOf(texto) === -1) {
                valores.push(texto);
            }
        });
        valores.sort(function(a, b) { return a.localeCompare(b, 'pt-BR'); });
        valores.forEach(function(valor) {
            $select.append($('<option>', { value: valor, text: valor }));
        });
    }

    $('.filtro-farol').each(function() {
        var $select = $(this);
        popularFiltro($select);
        $select.on('change', function() {
            var valor = $.fn.dataTable.util.escapeRegex($(this).val());
            tabelaFarol.column(Number($(this).data('column'))).search(valor ? '^' + valor + '$' : '', true, false).draw();
        });
    });
});
</script>
</body>
</html>