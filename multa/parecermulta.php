<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$autofrotaSessao = autofrotaInit();
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
$id = trim((string) ($_POST['idtbmovidatramite'] ?? $_GET['idtbmovidatramite'] ?? ''));

function esc($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
function fdata($v)
{
    if (!$v || $v === '0000-00-00 00:00:00')
        return '-';
    $d = date_create($v);
    return $d ? $d->format('d/m/Y H:i:s') : $v;
}

$dados = [];
$etapas = [];
if ($conn instanceof mysqli && $databaseName !== '' && $id !== '') {
    mysqli_set_charset($conn, 'utf8mb4');
    $sql = "SELECT * FROM `{$databaseName}`.tbmovidatramite WHERE idtbmovidatramite = ? LIMIT 1";
    $st = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($st, 's', $id);
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $dados = $res ? (mysqli_fetch_assoc($res) ?: []) : [];
    mysqli_stmt_close($st);

    $resEt = mysqli_query($conn, "SELECT DISTINCT etapa FROM `{$databaseName}`.tbamultaetapa WHERE etapa <> '' ORDER BY etapa");
    if ($resEt) {
        while ($r = mysqli_fetch_assoc($resEt))
            $etapas[] = (string) $r['etapa'];
        mysqli_free_result($resEt);
    }
}


$ultimoParecer = '';

if (!empty($dados['idtbmovidatramite'])) {

    $sqlParecer = "
        SELECT parecer
        FROM {$databaseName}.tbmulta_parecer
        WHERE id_multa = ?
        ORDER BY data_parecer DESC
        LIMIT 1
    ";

    $stmtParecer = mysqli_prepare($conn, $sqlParecer);

    mysqli_stmt_bind_param(
        $stmtParecer,
        'i',
        $dados['idtbmovidatramite']
    );

    mysqli_stmt_execute($stmtParecer);

    $resParecer = mysqli_stmt_get_result($stmtParecer);

    if ($rowParecer = mysqli_fetch_assoc($resParecer)) {
        $ultimoParecer = $rowParecer['parecer'];
    }

    mysqli_stmt_close($stmtParecer);
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inserir Parecer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php autofrotaMenu(); ?>
    <main class="container py-4" style="max-width: 1100px;">
        <h1 class="h3 mb-4">Inserir Parecer</h1>
        <form method="post" action="../control/parecermulta.php">
            <input type="hidden" name="idtbmovidatramite" value="<?= esc($id) ?>">
            <div class="card mb-3">
                <div class="card-header">Informações da Multa</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-md-3"><label class="form-label">Status</label><input class="form-control"
                                disabled value="<?= esc($dados['tramite'] ?? '') ?>"></div>
                        <div class="col-md-2"><label class="form-label">Placa</label><input class="form-control"
                                disabled value="<?= esc($dados['placa'] ?? '') ?>"></div>
                        <div class="col-md-3"><label class="form-label">Nº Auto</label><input class="form-control"
                                disabled value="<?= esc($dados['autoinfra'] ?? '') ?>"></div>
                        <div class="col-md-2"><label class="form-label">Data Infração</label><input class="form-control"
                                disabled value="<?= esc(fdata($dados['dtinfra'] ?? '')) ?>"></div>
                        <div class="col-md-2"><label class="form-label">Valor</label><input class="form-control"
                                disabled value="<?= esc($dados['valor'] ?? '') ?>"></div>
                    </div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-body">
                    <label class="form-label">Atualizar trâmite por etapa (opcional)</label>
                    <select class="form-select mb-3" name="etapa1">
                        <option value="">Selecione...</option><?php foreach ($etapas as $e): ?>
                            <option value="<?= esc($e) ?>"><?= esc($e) ?></option><?php endforeach; ?>
                    </select>
                    <label class="form-label">Último Parecer</label><textarea class="form-control mb-3" rows="3"
                        disabled><?= esc($ultimoParecer) ?></textarea>
                    <label class="form-label">Novo Parecer <span class="text-danger">*</span></label><textarea
                        class="form-control" name="parecer" maxlength="200" rows="4" required></textarea>
                    <button class="btn btn-success mt-3" type="submit">Enviar Parecer</button>
                    <a class="btn btn-secondary mt-3" href="./multasfrota.php">Voltar</a>
                </div>
            </div>
        </form>
    </main>
</body>

</html>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>