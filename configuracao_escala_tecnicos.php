<?php
require_once __DIR__ . '/includes/autofrota_common.php';

date_default_timezone_set('America/Sao_Paulo');
$autofrotaSessao = autofrotaInit();

$perfil = (string) ($autofrotaSessao['perfil'] ?? $_SESSION['perfil'] ?? '');
$matricula = (string) ($autofrotaSessao['matricula'] ?? $_SESSION['matricula'] ?? '');
$databaseAutofrota = trim((string) (($GLOBALS['databaseName'] ?? '') !== '' ? $GLOBALS['databaseName'] : ($autofrotaSessao['databaseName'] ?? 'bdautofrotas')));

/** @var mysqli|null $conn */
$conn = $autofrotaSessao['conn'] ?? null;

if ($perfil !== '4') {
    echo "<script>alert('Apenas perfil 4 pode configurar o encerramento da escala'); window.parent.postMessage('fecharModal', '*');</script>";
    exit;
}

function escConfigEscala($valor): string
{
    return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
}

$diasSemana = [
    0 => 'domingo',
    1 => 'segunda-feira',
    2 => 'terça-feira',
    3 => 'quarta-feira',
    4 => 'quinta-feira',
    5 => 'sexta-feira',
    6 => 'sábado',
];

$mensagemSucesso = '';
$mensagemErro = '';
$idConfiguracao = 0;
$diaEncerramento = 5;
$horaEncerramento = 15;
$configSalva = isset($_GET['salvo']) && $_GET['salvo'] === '1';

if ($conn instanceof mysqli && $databaseAutofrota !== '') {
    $sqlAtual = "SELECT id, dia_encerramento, hora_encerramento FROM `{$databaseAutofrota}`.`tbescala_configuracao` ORDER BY id DESC LIMIT 1";
    $resultadoAtual = mysqli_query($conn, $sqlAtual);
    if ($resultadoAtual && mysqli_num_rows($resultadoAtual) > 0) {
        $configAtual = mysqli_fetch_assoc($resultadoAtual);
        $idConfiguracao = (int) ($configAtual['id'] ?? 0);
        $diaEncerramento = (int) ($configAtual['dia_encerramento'] ?? $diaEncerramento);
        $horaEncerramento = (int) ($configAtual['hora_encerramento'] ?? $horaEncerramento);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $novoDia = (int) ($_POST['dia_encerramento'] ?? -1);
    $novaHora = (int) ($_POST['hora_encerramento'] ?? -1);

    if (!$conn instanceof mysqli || $databaseAutofrota === '') {
        $mensagemErro = 'Conexão com o banco indisponível.';
    } elseif ($novoDia < 0 || $novoDia > 6 || $novaHora < 0 || $novaHora > 23) {
        $mensagemErro = 'Informe um dia da semana e uma hora de encerramento válidos.';
    } else {
        if ($idConfiguracao > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE `{$databaseAutofrota}`.`tbescala_configuracao` SET dia_encerramento = ?, hora_encerramento = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'iii', $novoDia, $novaHora, $idConfiguracao);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO `{$databaseAutofrota}`.`tbescala_configuracao` (dia_encerramento, hora_encerramento) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ii', $novoDia, $novaHora);
        }

        if ($stmt && mysqli_stmt_execute($stmt)) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?salvo=1');
            exit;
        } else {
            $mensagemErro = $stmt ? mysqli_stmt_error($stmt) : mysqli_error($conn);
        }

        if ($stmt) {
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurar Encerramento da Escala</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; font-size: 13px; }
        .config-card { border-top: 5px solid #212529; box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
        .card-header { background: linear-gradient(45deg, #212529, #343a40); color: #fff; }
        .btn-dark { background: #212529; border-color: #212529; }
        .btn-dark:hover { background: #343a40; border-color: #343a40; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-7">
                <div class="card config-card">
                    <div class="card-header py-3">
                        <h4 class="mb-0"><i class="fas fa-gear me-2"></i>Configurar encerramento da escala</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($mensagemSucesso !== ''): ?>
                            <div class="alert alert-success"><?= escConfigEscala($mensagemSucesso) ?></div>
                        <?php endif; ?>
                        <?php if ($mensagemErro !== ''): ?>
                            <div class="alert alert-danger"><?= escConfigEscala($mensagemErro) ?></div>
                        <?php endif; ?>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Essa configuração define até qual dia e horário os agendamentos da escala de fim de semana podem ser alterados.
                        </div>

                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label fw-bold" for="dia_encerramento">Dia de encerramento</label>
                                <select class="form-select form-select-lg" id="dia_encerramento" name="dia_encerramento" required>
                                    <?php foreach ($diasSemana as $valor => $texto): ?>
                                        <option value="<?= (int) $valor ?>" <?= $diaEncerramento === (int) $valor ? 'selected' : '' ?>><?= escConfigEscala(ucfirst($texto)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold" for="hora_encerramento">Hora de encerramento</label>
                                <select class="form-select form-select-lg" id="hora_encerramento" name="hora_encerramento" required>
                                    <?php for ($hora = 0; $hora <= 23; $hora++): ?>
                                        <option value="<?= $hora ?>" <?= $horaEncerramento === $hora ? 'selected' : '' ?>><?= sprintf('%02d:00', $hora) ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>

                            <div class="d-flex flex-column flex-md-row gap-2 justify-content-end">
                                <button type="button" class="btn btn-outline-secondary btn-lg" onclick="window.parent.postMessage('fecharModal', '*')">
                                    <i class="fas fa-times me-2"></i>Fechar
                                </button>
                                <button type="submit" class="btn btn-dark btn-lg">
                                    <i class="fas fa-save me-2"></i>Salvar configuração
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-muted">
                        Configuração atual: <?= escConfigEscala(ucfirst($diasSemana[$diaEncerramento] ?? 'sexta-feira')) ?> às <?= escConfigEscala(sprintf('%02d:00', $horaEncerramento)) ?>.
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($configSalva): ?>
        window.parent.postMessage('recarregarPagina', '*');
        <?php endif; ?>
    </script>
</body>
</html>
<!-- tabela utilizada: bdautofrotas.tbescala_configuracao (id, dia_encerramento, hora_encerramento) -->
