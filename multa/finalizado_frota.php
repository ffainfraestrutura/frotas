<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
include "../func/funcoes.php";
$autofrotaSessao = autofrotaInit();
$idmulta = $_GET['id'];

$sql = "
    SELECT
        m.placa,
        m.autoinfracao,
        m.codigom,
        m.descricaoinfra,
        m.etapa,
        m.orgao,
        m.datalimitecond,
        m.valtotal,
        t.nome,
        t.matricula,
        t.cpf,
        t.gravidade,
        t.tramite,
        t.locadora,
        t.dtcons,
        t.dtinfra,
        t.parecer,
        t.parecerpor,
        t.parecerdp,
        t.parecerpordp,
        t.dtdesconto,
        t.idtbmovidatramite
        FROM `{$databaseName}`.tbmulta m
        LEFT JOIN `{$databaseName}`.tbmovidatramite t
        ON t.placa = m.placa
        AND t.autoinfra = m.autoinfracao
        WHERE t.idtbmovidatramite = ?
    ";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 's', $idmulta);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$dados = mysqli_fetch_assoc($result);

if (!$dados) {
    echo "<div class='alert alert-danger'>Registro não encontrado!</div>";
    exit;
}

// Variáveis para preencher o formulário
$nome_infrator = htmlspecialchars($dados['nome'] ?? '');
$matricula_infrator = htmlspecialchars($dados['matricula'] ?? '');
$cpf_infrator = htmlspecialchars($dados['cpf'] ?? '');
$placa = htmlspecialchars($dados['placa'] ?? '');
$id = htmlspecialchars($idmulta ?? '');
$usuariof = $_SESSION['matricula'] ?? '';

// Carrega os colaboradores ativos
$colaboradores = [];
$sqlColab = "SELECT matricula, nome, cpf FROM bdaniel.tbfuncionario WHERE status = 'Ativo' ORDER BY nome";
$resultColab = mysqli_query($conn, $sqlColab);
while ($row = mysqli_fetch_assoc($resultColab)) {
    $colaboradores[] = [
        'matricula' => $row['matricula'],
        'nome' => $row['nome'],
        'cpf' => $row['cpf']
    ];
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Editar Infrator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
</head>

<body>
    <?php autofrotaMenu(); ?>
    <main class="container py-4" style="max-width: 1100px;">
        <h1 class="h3 mb-4">Editar Infrator</h1>

        <form method="POST" action="../control/editarinfrator.php">
            <section class="card shadow-sm mb-4">
                <div class="card-body">
                    <p>Caso o infrator indicado abaixo não esteja correto, corrija os dados.</p>

                    <!-- Campos hidden -->
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <input type="hidden" name="placa" value="<?php echo $placa; ?>">
                    <input type="hidden" name="mat_autor" value="<?php echo $usuariof; ?>">

                    <div class="row g-3">
                        <div class="col-12 col-md-6 col-lg-5">
                            <label class="form-label" for="nome_colaborador">Nome do colaborador <span
                                    class="text-danger">*</span></label>
                            <select class="form-select" name="nome_colaborador" id="nome_colaborador" required>
                                <option value="">Selecione o colaborador...</option>
                                <?php foreach ($colaboradores as $colaborador): ?>
                                    <option value="<?= htmlspecialchars($colaborador['nome']) ?>"
                                        data-matricula="<?= htmlspecialchars($colaborador['matricula']) ?>"
                                        data-cpf="<?= htmlspecialchars($colaborador['cpf']) ?>"
                                        <?= (strcasecmp(trim($colaborador['nome']), trim($nome_infrator)) == 0) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($colaborador['nome']) ?> (<?= htmlspecialchars($colaborador['matricula']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Matrícula</label>
                            <input type="text" class="form-control" name="matricula" id="matricula"
                                value="<?php echo $matricula_infrator; ?>" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">CPF <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="cpf" id="cpf"
                                value="<?php echo $cpf_infrator; ?>" required readonly>
                        </div>

                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <button type="submit" name="acao" value="confirmar" class="btn btn-success">Confirmar
                                Infrator</button>
                            <a class='btn btn-primary'
                                href="../control/editarinfrator.php?terceiro=True&id=<?= $idmulta ?>">
                                Alterar Infrator Para Terceiro
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </form>

        <p class="text-danger small">* Campos obrigatórios.</p>
        <a type="button" class="btn btn-secondary" href="multasfrota.php">Voltar para página inicial</a>
    </main>

    <!-- jQuery (necessário para o Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Inicializa o Select2
            $('#nome_colaborador').select2({
                theme: 'bootstrap-5',
                placeholder: 'Digite para buscar o colaborador...',
                allowClear: true,
                language: {
                    noResults: function() {
                        return "Nenhum colaborador encontrado";
                    },
                    searching: function() {
                        return "Buscando...";
                    }
                }
            });

            // Evento de mudança do select
            $('#nome_colaborador').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                
                if (selectedOption.val()) {
                    // Pega os valores dos atributos data
                    var matricula = selectedOption.data('matricula');
                    var cpf = selectedOption.data('cpf');
                    
                    // Atualiza os campos readonly com os valores corretos
                    $('#matricula').val(matricula);
                    $('#cpf').val(cpf);
                } else {
                    // Limpa os campos se nenhuma opção for selecionada
                    $('#matricula').val('');
                    $('#cpf').val('');
                }
            });
        });
    </script>
</body>

</html>