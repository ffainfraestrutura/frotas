<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$matriculaLogada = $autofrotaSessao['matricula'];
$perfilLogado = $autofrotaSessao['perfil'];
$conn = $autofrotaSessao['conn'] ?? null;
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');

// Função de escape
function esc(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

// Recupera matrícula do condutor (via GET ou POST)
$matricula = (string) ($_GET['matricula'] ?? $_POST['matricula'] ?? '');
$matrAutor = (string) ($_GET['matr_autor'] ?? $_POST['matr_autor'] ?? $matriculaLogada);
$perfil = (string) ($_GET['perfil'] ?? $_POST['perfil'] ?? $perfilLogado);

$condutor = null;
$placaAtual = '';
$dataAssocAtual = '';

// Se matrícula for fornecida, busca os dados atuais
if ($matricula !== '' && isset($conn) && $conn instanceof mysqli) {
    $sql = "SELECT matricula, nome, placaassoc, dataassoc 
            FROM `{$databaseName}`.`tbcondutor` 
            WHERE matricula = ? 
            ORDER BY idtbcondutor DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $matricula);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $condutor = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        if ($condutor) {
            $placaAtual = (string) ($condutor['placaassoc'] ?? '');
            $dataAssocAtual = $condutor['dataassoc'] ?? '';
            if ($dataAssocAtual && $dataAssocAtual !== '0000-00-00 00:00:00') {
                $dt = date_create($dataAssocAtual);
                $dataAssocAtual = $dt ? date_format($dt, 'Y-m-d\TH:i') : '';
            }
        }
    }
}

// Busca lista de todos os condutores para o select (apenas matrícula e nome)
$listaCondutores = [];
if (isset($conn) && $conn instanceof mysqli) {
    $sqlLista = "SELECT a.matricula, b.nome 
             FROM `{$databaseName}`.`tbcnh` AS a
             INNER JOIN `{$databaseName}`.`tbfuncionario` AS b ON a.matricula = b.matricula 
             GROUP BY a.matricula 
             ORDER BY b.nome ASC";
    $res = mysqli_query($conn, $sqlLista);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $listaCondutores[] = $row;
        }
        mysqli_free_result($res);
    }
}

// Mensagem de retorno (se houver)
$mensagem = '';
$tipoMensagem = 'info';
if (isset($_SESSION['msg_veiculo_condutor'])) {
    $mensagem = $_SESSION['msg_veiculo_condutor'];
    $tipoMensagem = $_SESSION['msg_tipo_veiculo_condutor'] ?? 'info';
    unset($_SESSION['msg_veiculo_condutor'], $_SESSION['msg_tipo_veiculo_condutor']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>AutoFrota - Associar Veículo a Condutor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,400,0,0" />
    <link rel="stylesheet" href="../src/autofrota-botoes.css">
    <style>
        body { background: #f8f9fa; font-size: 14px; }
        .form-container { max-width: 600px; margin: 40px auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .form-title { font-size: 22px; font-weight: 600; margin-bottom: 20px; }
        .btn-voltar { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <a href="listagem-condutor.php" class="btn btn-secondary btn-voltar">&larr; Voltar para listagem</a>
            <h1 class="form-title">Associar Veículo ao Condutor</h1>

            <?php if ($mensagem): ?>
                <div class="alert alert-<?= esc($tipoMensagem) ?> alert-dismissible fade show" role="alert">
                    <?= esc($mensagem) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
                </div>
            <?php endif; ?>

            <form action="action-veiculo-condutor.php" method="post">
                <!-- Campos ocultos -->
                <input type="hidden" name="matr_autor" value="<?= esc($matrAutor) ?>">
                <input type="hidden" name="perfil" value="<?= esc($perfil) ?>">

                <div class="mb-3">
                    <label for="matricula" class="form-label fw-bold">Condutor *</label>
                    <select class="form-select" name="matricula" id="matricula" required>
                        <option value="">Selecione o condutor</option>
                        <?php foreach ($listaCondutores as $c): ?>
                            <option value="<?= esc($c['matricula']) ?>" <?= $c['matricula'] === $matricula ? 'selected' : '' ?>>
                                <?= esc($c['matricula'] . ' - ' . $c['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="placa" class="form-label fw-bold">Placa do Veículo *</label>
                    <input type="text" class="form-control" name="placa" id="placa" 
                           value="<?= esc($placaAtual) ?>" 
                           placeholder="Ex: ABC-1234 ou ABC1A23" 
                           maxlength="8" required>
                    <small class="text-muted">Formato brasileiro (AAA-0000) ou Mercosul (AAA0A00).</small>
                </div>

                <div class="mb-3">
                    <label for="dataassoc" class="form-label fw-bold">Data de Associação</label>
                    <input type="datetime-local" class="form-control" name="dataassoc" id="dataassoc" 
                           value="<?= esc($dataAssocAtual) ?>">
                    <small class="text-muted">Deixe em branco para usar a data/hora atual.</small>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="listagem-condutor.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Máscara para placa: permite formato brasileiro e Mercosul
        document.getElementById('placa').addEventListener('input', function(e) {
            let valor = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            // Se já tem 7 caracteres, formata como Mercosul: AAA0A00 ou AAA0000? 
            // Vamos permitir ambos: se 7 caracteres, assume Mercosul; se 7, também pode ser brasileiro com hífen.
            // Para simplificar, apenas remove caracteres especiais.
            // O usuário pode digitar como quiser, depois validamos no backend.
            // Aplicar formatação básica para melhor visualização:
            // Se tiver 7 ou mais, tenta colocar hífen se for padrão brasileiro (3 letras + 4 números)
            // Exemplo: ABC1234 -> ABC-1234
            // Se for Mercosul: ABC1A23 -> ABC1A23 (sem hífen)
            // Para não complicar, deixamos o usuário digitar livre e validamos.
            // Apenas garantimos que não haja espaços.
            this.value = valor;
        });
    </script>
</body>
</html>
