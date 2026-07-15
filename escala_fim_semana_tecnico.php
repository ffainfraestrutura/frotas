<?php
require_once __DIR__ . '/includes/autofrota_common.php';

date_default_timezone_set('America/Sao_Paulo');
$autofrotaSessao = autofrotaInit();

$nome = (string) ($autofrotaSessao['usuario'] ?? $_SESSION['nome'] ?? $_SESSION['usuario'] ?? 'Usuário');
$matricula = trim((string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? ''));
$perfil = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');
$databaseAutofrota = trim((string) (($GLOBALS['databaseName'] ?? '') !== '' ? $GLOBALS['databaseName'] : ($autofrotaSessao['databaseName'] ?? 'bdautofrotas')));
$databaseCorp = trim((string) (($GLOBALS['databaseCorp'] ?? '') !== '' ? $GLOBALS['databaseCorp'] : ($autofrotaSessao['databaseCorp'] ?? 'bdcorp')));

/** @var mysqli|null $conn */
$conn = $autofrotaSessao['conn'] ?? null;

if ($perfil === '' || $perfil === '0') {
    echo "<script>alert('Apenas coordenadores têm acesso'); window.location='index.php';</script>";
    exit;
}

function escEscalaFimSemana($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

function semanaEscalaFimSemana(string $data): string
{
    $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sab'];
    $timestamp = strtotime($data);
    return $timestamp ? $dias[(int) date('w', $timestamp)] : '';
}

function nomeTecnicoEscala(mysqli $conn, string $databaseCorp, string $matricula): string
{
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(f.nome, u.usuario) AS nome FROM `{$databaseCorp}`.`tbusuario` u LEFT JOIN `{$databaseCorp}`.`tbfuncionario` f ON f.matricula = u.matricula WHERE u.matricula = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $linha = $resultado ? mysqli_fetch_assoc($resultado) : [];
    mysqli_stmt_close($stmt);
    return trim((string) ($linha['nome'] ?? $matricula));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$conn instanceof mysqli || $databaseAutofrota === '') {
        echo json_encode(['success' => false, 'msg' => 'Conexão indisponível']);
        exit;
    }

    $segunda = strtotime('monday this week');
    $sabado = date('Y-m-d', strtotime('+5 days', $segunda));
    $domingo = date('Y-m-d', strtotime('+6 days', $segunda));
    $diasFimSemana = [$sabado, $domingo];

    if (isset($_POST['limpar'])) {
        $stmtLimpar = mysqli_prepare($conn, "DELETE FROM `{$databaseAutofrota}`.`tbescala` WHERE DATE(dia) IN (?, ?)");
        mysqli_stmt_bind_param($stmtLimpar, 'ss', $sabado, $domingo);
        $okLimpar = mysqli_stmt_execute($stmtLimpar);
        $erroLimpar = mysqli_stmt_error($stmtLimpar);
        mysqli_stmt_close($stmtLimpar);
        echo json_encode(['success' => $okLimpar, 'msg' => $erroLimpar]);
        exit;
    }

    $escalasPost = json_decode((string) ($_POST['escalas'] ?? '[]'), true);
    if (!is_array($escalasPost)) {
        echo json_encode(['success' => false, 'msg' => 'Payload inválido']);
        exit;
    }

    mysqli_begin_transaction($conn);
    $ok = true;
    $erro = '';

    $stmtLimpar = mysqli_prepare($conn, "DELETE FROM `{$databaseAutofrota}`.`tbescala` WHERE DATE(dia) IN (?, ?)");
    mysqli_stmt_bind_param($stmtLimpar, 'ss', $sabado, $domingo);
    $ok = mysqli_stmt_execute($stmtLimpar);
    $erro = mysqli_stmt_error($stmtLimpar);
    mysqli_stmt_close($stmtLimpar);

    if ($ok) {
        $stmtInserir = mysqli_prepare($conn, "INSERT INTO `{$databaseAutofrota}`.`tbescala` (nome, matricula, mes, ano, dia, status, semana) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($escalasPost as $escala) {
            $matriculaTecnico = trim((string) ($escala['matricula'] ?? ''));
            $dia = substr((string) ($escala['dia'] ?? ''), 0, 10);
            $status = (string) ((int) ($escala['status'] ?? 1));

            if ($matriculaTecnico === '' || !in_array($dia, $diasFimSemana, true)) {
                continue;
            }

            $nomeTecnico = nomeTecnicoEscala($conn, $databaseCorp, $matriculaTecnico);
            $mes = (int) date('m', strtotime($dia));
            $ano = (int) date('Y', strtotime($dia));
            $semana = semanaEscalaFimSemana($dia);
            mysqli_stmt_bind_param($stmtInserir, 'ssiisss', $nomeTecnico, $matriculaTecnico, $mes, $ano, $dia, $status, $semana);
            if (!mysqli_stmt_execute($stmtInserir)) {
                $ok = false;
                $erro = mysqli_stmt_error($stmtInserir);
                break;
            }
        }
        mysqli_stmt_close($stmtInserir);
    }

    if ($ok) {
        mysqli_commit($conn);
    } else {
        mysqli_rollback($conn);
    }

    echo json_encode(['success' => $ok, 'msg' => $erro]);
    exit;
}

$diaEncerramento = 5;
$horaEncerramento = 15;

if ($conn instanceof mysqli && $databaseAutofrota !== '') {
    $sqlConfigEscala = "SELECT dia_encerramento, hora_encerramento FROM `{$databaseAutofrota}`.`tbescala_configuracao` ORDER BY id DESC LIMIT 1";
    $resultConfigEscala = mysqli_query($conn, $sqlConfigEscala);

    if ($resultConfigEscala && mysqli_num_rows($resultConfigEscala) > 0) {
        $configEscala = mysqli_fetch_assoc($resultConfigEscala);
        $diaTabela = (int) ($configEscala['dia_encerramento'] ?? $diaEncerramento);
        $horaTabela = (int) ($configEscala['hora_encerramento'] ?? $horaEncerramento);

        if ($diaTabela >= 0 && $diaTabela <= 6) {
            $diaEncerramento = $diaTabela;
        }

        if ($horaTabela >= 0 && $horaTabela <= 23) {
            $horaEncerramento = $horaTabela;
        }
    }
}

$diasSemana = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
$diaEncerramentoTexto = $diasSemana[$diaEncerramento];
$horarioEncerramentoTexto = sprintf('%02d:00', $horaEncerramento);

$idtbcoordenadorlogado = 0;
$tecnicosData = [];
$segunda = strtotime('monday this week');
$sabado = date('Y-m-d', strtotime('+5 days', $segunda));
$domingo = date('Y-m-d', strtotime('+6 days', $segunda));

if ($conn instanceof mysqli && $databaseCorp !== '' && $databaseAutofrota !== '') {
    $stmtCoord = mysqli_prepare($conn, "SELECT c.idtbcoordenador FROM `{$databaseCorp}`.`tbcoord` c INNER JOIN `{$databaseCorp}`.`tbfuncionario` f ON c.matricula = f.matricula WHERE c.matricula = ? AND f.status = 'Ativo' LIMIT 1");
    mysqli_stmt_bind_param($stmtCoord, 's', $matricula);
    mysqli_stmt_execute($stmtCoord);
    $resultadoCoord = mysqli_stmt_get_result($stmtCoord);
    $dadosCoord = $resultadoCoord ? mysqli_fetch_assoc($resultadoCoord) : [];
    mysqli_stmt_close($stmtCoord);
    $idtbcoordenadorlogado = (int) ($dadosCoord['idtbcoordenador'] ?? 0);

    if ($idtbcoordenadorlogado > 0) {
        $stmtSup = mysqli_prepare($conn, "SELECT idtbsupervisor FROM `{$databaseCorp}`.`tbsupervisor` WHERE idtbcoordenador = ?");
        mysqli_stmt_bind_param($stmtSup, 'i', $idtbcoordenadorlogado);
        mysqli_stmt_execute($stmtSup);
        $resultadoSup = mysqli_stmt_get_result($stmtSup);
        $listaIdsSups = [];
        while ($row = mysqli_fetch_assoc($resultadoSup)) {
            $listaIdsSups[] = (int) $row['idtbsupervisor'];
        }
        mysqli_stmt_close($stmtSup);

        if (!empty($listaIdsSups)) {
            $listaIdsSupsStr = implode(',', $listaIdsSups);
            $sqlEscalas = "SELECT matricula, DATE(dia) AS dia, status FROM `{$databaseAutofrota}`.`tbescala` WHERE DATE(dia) IN ('{$sabado}','{$domingo}')";
            $resultEscalas = mysqli_query($conn, $sqlEscalas);
            $escalas = [];
            while ($rowEscala = $resultEscalas ? mysqli_fetch_assoc($resultEscalas) : null) {
                if ($rowEscala === null) {
                    break;
                }
                $escalas[trim((string) $rowEscala['matricula'])][$rowEscala['dia']] = $rowEscala['status'];
            }

            $sqlTecnicos = "SELECT DISTINCT u.matricula, f.nome, u.idtbsupervisor
                              FROM `{$databaseCorp}`.`tbusuario` u
                              INNER JOIN `{$databaseCorp}`.`tbfuncionario` f ON u.matricula = f.matricula
                             WHERE (u.perfil = 0 OR u.perfil = '0')
                               AND f.status = 'Ativo'
                               AND f.cargo IS NOT NULL
                               AND f.cargo NOT REGEXP '^(SUPERVISOR|GERENTE|ANALISTA|CADISTA|MONITOR|ASSISTENTE|ENGENHEIRO|COMPRADOR|COORD|RH|RECEPCIONISTA|PROGRAMADOR|PORTEIRO|PROJETISTA|VIGIA|DIRETOR|APRENDIZ|ALMOXARIFE|AUXILIAR ADM|AUXILIAR DE FROTA|AUXILIAR DE ALMOX|AUDITOR DE LOG)'
                               AND EXISTS (
                                   SELECT 1
                                     FROM `{$databaseAutofrota}`.`tbveiculo` v
                                    WHERE v.matcond = u.matricula
                                      AND v.placa IS NOT NULL
                                      AND v.placa <> ''
                                    LIMIT 1
                               )
                               AND u.idtbsupervisor IN ({$listaIdsSupsStr})
                             ORDER BY f.nome";
            $resultTecnicos = mysqli_query($conn, $sqlTecnicos);

            while ($tecnico = $resultTecnicos ? mysqli_fetch_assoc($resultTecnicos) : null) {
                if ($tecnico === null) {
                    break;
                }

                $matriculaTec = trim((string) $tecnico['matricula']);
                $idSupervisor = (int) ($tecnico['idtbsupervisor'] ?? 0);
                $nomeSupervisor = 'Sem supervisor';

                if ($idSupervisor > 0) {
                    $sqlSupNome = "SELECT f.nome FROM `{$databaseCorp}`.`tbsupervisor` s INNER JOIN `{$databaseCorp}`.`tbfuncionario` f ON s.matricula = f.matricula WHERE s.idtbsupervisor = {$idSupervisor}";
                    $resultSupNome = mysqli_query($conn, $sqlSupNome);
                    if ($rowSup = $resultSupNome ? mysqli_fetch_assoc($resultSupNome) : null) {
                        $nomeSupervisor = (string) $rowSup['nome'];
                    }
                }

                $tecnicosData[] = [
                    'matricula' => $matriculaTec,
                    'nome' => (string) $tecnico['nome'],
                    'supervisor' => $nomeSupervisor,
                    'sabado_status' => $escalas[$matriculaTec][$sabado] ?? 0,
                    'domingo_status' => $escalas[$matriculaTec][$domingo] ?? 0,
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Escala Fim de Semana - Técnicos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #ffffff 0%,  #ffffff); color:  #ffffff !important; }
        .card-escala { border-left: 5px solid #212529; box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .sabado { background-color: #e3f2fd !important; }
        .domingo { background-color: #f5edf1 !important; }
        .status-ativo { background: linear-gradient(45deg, #d4edda, #c3e6cb) !important; border: 2px solid  #d4edda !important; }
        .status-reserva { background: linear-gradient(45deg, #fff3cd, #ffeaa7) !important; border: 2px solid #ffc107 !important; }
        .supervisor-tag { background: linear-gradient(45deg, #6c757d, #5a6268); border-radius: 20px; padding: 6px 12px; font-size: 0.85em; color: white !important; font-weight: 500; }
        .card-header { background: linear-gradient(45deg, #212529, #343a40) !important; color: white !important; }
        .table-dark th { background: linear-gradient(45deg, #343a40, #495057) !important; color: white !important; }
        .btn-salvar-flutuante { position: fixed; bottom: 30px; right: 30px; z-index: 9999; padding: 20px 40px; font-size: 18px; border-radius: 50px; box-shadow: 0 8px 25px rgba(33, 37, 41, 0.35); background: linear-gradient(45deg, #212529, #343a40) !important; border: none; color: white !important; font-weight: bold; transition: all 0.3s ease; animation: pulse 2s infinite; }
        .btn-salvar-flutuante:hover { transform: scale(1.1) translateY(-5px); box-shadow: 0 12px 35px rgba(33, 37, 41, 0.5); }
        @keyframes pulse { 0%, 100% { box-shadow: 0 8px 25px rgba(33, 37, 41, 0.35); } 50% { box-shadow: 0 8px 35px rgba(33, 37, 41, 0.55); } }
        .btn-salvar-flutuante.hidden { display: none; }
    </style>
</head>
<body>
    <div class="container-fluid py-5">
        <div class="card shadow-lg mb-5 card-escala">
            <div class="card-header py-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-0 fw-bold text-white">
                            <i class="fas fa-calendar-week me-3"></i>
                            Escala Fim de Semana
                            <span class="badge bg-light text-dark ms-2 fs-6"><?= count($tecnicosData) ?> Técnicos</span>
                        </h3>
                        <small class="text-white-50">
                            📅 Sábado <?= date('d/m/Y', strtotime($sabado)) ?> | Domingo <?= date('d/m/Y', strtotime($domingo)) ?>
                            <span class="badge bg-warning text-dark ms-2" style="font-size: 1rem;">🔒 Fecha: <?= escEscalaFimSemana(ucfirst($diaEncerramentoTexto)) ?> <?= escEscalaFimSemana($horarioEncerramentoTexto) ?></span>
                            <span class="badge bg-success ms-2" style="font-size: 1rem;">
                                <?php $totalEscalados = 0; foreach ($tecnicosData as $t) { if ($t['sabado_status'] || $t['domingo_status']) { $totalEscalados++; } } echo $totalEscalados; ?> escalados
                            </span>
                        </small>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <button class="btn btn-outline-light btn-lg border-white" onclick="location.reload()"><i class="fas fa-sync-alt me-2"></i>Atualizar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-lg card-escala">
            <div class="card-body p-0">
                <table id="tabelaEscala" class="table table-hover mb-0" style="width:100%">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 35%;"><i class="fas fa-user me-1"></i>Técnico</th>
                            <th style="width: 25%;"><i class="fas fa-users-cog me-1"></i>Supervisor</th>
                            <th style="width: 20%; text-align: center;">📅 Sábado</th>
                            <th style="width: 20%; text-align: center;">📅 Domingo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tecnicosData as $tecnico): ?>
                        <tr>
                            <td class="fw-bold ps-3 align-middle">
                                <div><?= escEscalaFimSemana($tecnico['nome']) ?></div>
                                <small class="text-muted d-block"><?= escEscalaFimSemana($tecnico['matricula']) ?></small>
                            </td>
                            <td class="text-center align-middle"><span class="supervisor-tag"><?= escEscalaFimSemana($tecnico['supervisor']) ?></span></td>
                            <td class="text-center sabado align-middle <?= $tecnico['sabado_status'] ? ($tecnico['sabado_status'] == 1 ? 'status-ativo' : 'status-reserva') : '' ?>">
                                <div class="form-check form-switch d-flex justify-content-center align-items-center">
                                    <input class="form-check-input escala-checkbox mx-2" type="checkbox" data-matricula="<?= escEscalaFimSemana($tecnico['matricula']) ?>" data-dia="<?= escEscalaFimSemana($sabado) ?>" data-status="<?= escEscalaFimSemana($tecnico['sabado_status'] ?: 1) ?>" <?= $tecnico['sabado_status'] ? 'checked' : '' ?> onchange="toggleStatus(event, this)">
                                    <label class="form-check-label fw-bold fs-6 mb-0"><?= $tecnico['sabado_status'] == 1 ? '✅ ESCALADO' : ($tecnico['sabado_status'] == 2 ? '⏳ RESERVA' : '❌ NÃO') ?></label>
                                </div>
                            </td>
                            <td class="text-center domingo align-middle <?= $tecnico['domingo_status'] ? ($tecnico['domingo_status'] == 1 ? 'status-ativo' : 'status-reserva') : '' ?>">
                                <div class="form-check form-switch d-flex justify-content-center align-items-center">
                                    <input class="form-check-input escala-checkbox mx-2" type="checkbox" data-matricula="<?= escEscalaFimSemana($tecnico['matricula']) ?>" data-dia="<?= escEscalaFimSemana($domingo) ?>" data-status="<?= escEscalaFimSemana($tecnico['domingo_status'] ?: 1) ?>" <?= $tecnico['domingo_status'] ? 'checked' : '' ?> onchange="toggleStatus(event, this)">
                                    <label class="form-check-label fw-bold fs-6 mb-0"><?= $tecnico['domingo_status'] == 1 ? '✅ ESCALADO' : ($tecnico['domingo_status'] == 2 ? '⏳ RESERVA' : '❌ NÃO') ?></label>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tecnicosData)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-5"><i class="fas fa-users fa-4x mb-4 opacity-50"></i><h4>Nenhum técnico vinculado aos seus supervisores</h4><p class="lead">Vincule supervisores e técnicos primeiro</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light border-top">
                <div class="row align-items-center">
                    <div class="col-md-6"><small class="text-muted"><i class="fas fa-lightbulb me-2 text-warning"></i>✅ Escalados carregados automaticamente | 🔄 Marque/desmarque para editar | 💾 Clique "Salvar"</small></div>
                    <div class="col-md-6 text-md-end"><small class="text-muted"><i class="fas fa-search me-2"></i>Ctrl+F rápido | <i class="fas fa-sort me-2"></i>Ordenar</small></div>
                </div>
            </div>
        </div>
    </div>

    <button id="btnSalvarFlutuante" class="btn-salvar-flutuante" onclick="salvarTodos()"><i class="fas fa-save me-2"></i>Salvar Escalas</button>

    <script>
    $(document).ready(function() {
        $('#tabelaEscala').DataTable({ paging: false, searching: false, info: false, lengthChange: false, order: [[0, 'asc']], columnDefs: [{ targets: [2,3], orderable: false }], language: { url: 'https://cdn.datatables.net/plug-ins/1.13.1/i18n/pt-BR.json' } });
        verificarHorario();
        setInterval(verificarHorario, 60000);
    });

    const DIA_ENCERRAMENTO = <?= (int) $diaEncerramento ?>;
    const HORA_ENCERRAMENTO = <?= (int) $horaEncerramento ?>;

    function passouDoDiaEncerramento(diaAtual, diaLimite) {
        if (diaLimite === 0) { return diaAtual >= 1 && diaAtual <= 6; }
        return diaAtual > diaLimite || diaAtual === 0;
    }

    function verificarHorario() {
        const agora = new Date();
        const diaSemana = agora.getDay();
        const horas = agora.getHours();
        const btnSalvar = document.getElementById('btnSalvarFlutuante');
        const bloqueado = (diaSemana === DIA_ENCERRAMENTO && horas >= HORA_ENCERRAMENTO) || passouDoDiaEncerramento(diaSemana, DIA_ENCERRAMENTO);
        if (bloqueado) { btnSalvar.classList.add('hidden'); } else { btnSalvar.classList.remove('hidden'); }
    }

    function toggleStatus(event, checkbox) {
        let td = $(checkbox).closest('td');
        let isChecked = checkbox.checked;
        let statusClass = isChecked ? (checkbox.dataset.status == 1 ? 'status-ativo' : 'status-reserva') : '';
        td.removeClass('status-ativo status-reserva').addClass(statusClass);
        let label = $(checkbox).next('label');
        if (isChecked) { label.text(checkbox.dataset.status == 1 ? '✅ ESCALADO' : '⏳ RESERVA'); label.removeClass('text-muted').addClass('text-success fw-bold'); }
        else { label.text('❌ NÃO'); label.removeClass('text-success').addClass('text-muted'); }
    }

    function salvarTodos() {
        let escalas = [];
        let totalMarcados = 0;
        $('.escala-checkbox:checked').each(function() {
            escalas.push({ matricula: $(this).data('matricula'), dia: $(this).data('dia'), status: $(this).data('status') || 1 });
            totalMarcados++;
        });

        if (totalMarcados === 0) {
            if (confirm('Nenhum técnico escalado. Deseja limpar todas as escalas?')) {
                if (confirm('CONFIRME: Limpar TODAS as escalas do fim de semana?')) {
                    $.post(window.location.href, {limpar: true}, function(resp) { alert('✅ Escalas limpas!'); location.reload(); }, 'json');
                }
            }
            return;
        }

        let preview = `⚠️ Salvar ${totalMarcados} escala(s)?\n\n`;
        escalas.forEach((e, i) => { preview += `${i+1}. ${e.matricula} - ${e.dia} (${e.status == 1 ? 'ESCALADO' : 'RESERVA'})\n`; });

        if (confirm(preview)) {
            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {escalas: JSON.stringify(escalas)},
                dataType: 'json',
                success: function(resp) {
                    if (resp.success) { alert(`✅ SALVO!\n${totalMarcados} escalas atualizadas com sucesso`); location.reload(); }
                    else { alert('❌ Erro: ' + (resp.msg || 'Erro desconhecido')); }
                },
                error: function() { alert('❌ Erro de conexão.'); }
            });
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<!-- telas e consultas relacionadas
SELECT * FROM bdautofrotas.tbescala;
SELECT * FROM bdautofrotas.tbescala_configuracao;
-->