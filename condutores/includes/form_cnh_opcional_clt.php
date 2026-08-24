<?php
$cnh = isset($cnh) && is_array($cnh) ? $cnh : [];
$ufs = isset($ufs) && is_array($ufs) ? $ufs : [];
if ($ufs === [] && isset($conn) && $conn instanceof mysqli && function_exists('buscarUfsPortal')) {
    $ufs = buscarUfsPortal($conn);
}
$valorCnh = static function (string $campo) use ($cnh): string {
    return (string) ($cnh[$campo] ?? '');
};
?>
<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-id-card me-2"></i>CNH do colaborador</h6>
    </div>
    <div class="card-body">
        <!-- <p class="small text-muted">A CNH somente será salva quando o número for informado. Os dados permanecem armazenados em <code>tbcnh</code>.</p> -->
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label" for="cnh_numero">Número da CNH</label>
                <input class="form-control" id="cnh_numero" name="cnh_numero" inputmode="numeric" pattern="[0-9]*" maxlength="12" value="<?= esc($valorCnh('numcnh')) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="cnh_validade">Validade</label>
                <input class="form-control" type="date" id="cnh_validade" name="cnh_validade" value="<?= esc(formatarDataPortal($valorCnh('validade'), 'Y-m-d')) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="cnh_uf">UF de emissão</label>
                <select class="form-select" id="cnh_uf" name="cnh_uf" required>
                    <option value="">Selecione</option>
                    <?php foreach ($ufs as $ufCnh): ?>
                        <option value="<?= esc($ufCnh) ?>" <?= $valorCnh('uf') === $ufCnh ? 'selected' : '' ?>><?= esc($ufCnh) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="cnh_categoria">Categoria</label>
                <input class="form-control text-uppercase" id="cnh_categoria" name="cnh_categoria" maxlength="5" value="<?= esc($valorCnh('categoria')) ?>" required>
            </div>
            <div class="col-md-1">
                <label class="form-label" for="cnh_pontos">Pontos</label>
                <input class="form-control" id="cnh_pontos" name="cnh_pontos" inputmode="numeric" pattern="[0-9]*" maxlength="3" value="<?= esc($valorCnh('pontos')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="cnh_suspensa">Suspensa</label>
                <select class="form-select" id="cnh_suspensa" name="cnh_suspensa">
                    <option value="0" <?= $valorCnh('suspensa') !== '1' ? 'selected' : '' ?>>Não</option>
                    <option value="1" <?= $valorCnh('suspensa') === '1' ? 'selected' : '' ?>>Sim</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="cnh_consulta">Consulta ao DETRAN</label>
                <input class="form-control" type="date" id="cnh_consulta" name="cnh_consulta" value="<?= esc(formatarDataPortal($valorCnh('consulta'), 'Y-m-d')) ?>" required>
            </div>
        </div>
    </div>
</div>