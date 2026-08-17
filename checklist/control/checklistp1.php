<?php
require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();
$con = $autofrota['conn'];
$databaseName = (string) ($autofrota['databaseName'] ?? '');
header('Content-Type: text/html; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !($con instanceof mysqli) || preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
    http_response_code(405);
    exit('Requisição inválida.');
}

$valor = static fn(string $campo): string => trim((string) ($_POST[$campo] ?? ''));
$placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $valor('placa')) ?? '');
$data = $valor('datavistoria');
$hora = $valor('horavistoria');
$datavistoria = ($data !== '' ? $data : date('Y-m-d')) . ' ' . ($hora !== '' ? $hora : date('H:i:s'));

$camposFormulario = [
    'nome','matricula','cpf','cnh','categoriacnh','validadecnh','placa','modelo','anofabricacao','unidade','centrocusto',
    'tipo','vistoriador','matrvistoriador','datavistoria','estado','avaria','hodometro','niveltanque','observacao','documentacao','statusveic',
    'teto','tetoesp','tetoesq','tetodir','frente','capo','parabrisa','farolesq','faroldir','parachoque','grade',
    'latesq','paralamaesq','retrovesq','cxaresq','ptdiantesq','pttrasesq','latdir','paralamadir','retrovdir','cxardir','ptdiantdir','pttrasdir',
    'traseira','lantesq','lantdir','tmpmala','parachoquet','itinterno','painel','som','bancos','ilumint','tmpbag','retrovint','tapetes',
    'pneus','step','marcapneus','kitstep','calotas','bateria','safecar','limpint','limpext'
];
$dadosFormulario = [];
foreach ($camposFormulario as $campo) {
    $dadosFormulario[$campo] = $valor($campo);
}
$dadosFormulario['cpf'] = preg_replace('/\D/', '', $dadosFormulario['cpf']) ?? '';
$dadosFormulario['placa'] = $placa;
$dadosFormulario['datavistoria'] = $datavistoria;
$dadosFormulario['vistoriador'] = $dadosFormulario['vistoriador'] ?: (string) ($autofrota['usuario'] ?? '');
$dadosFormulario['matrvistoriador'] = $dadosFormulario['matrvistoriador'] ?: (string) ($autofrota['matricula'] ?? '');


$statusOperacional = $dadosFormulario['statusveic'] === '36' ? '0' : '1';

$executar = static function (mysqli $con, string $sql, string $tipos = '', array $params = []): void {
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) throw new RuntimeException(mysqli_error($con));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
};

$buscarLinha = static function (mysqli $con, string $sql, string $tipos = '', array $params = []): array {
    $stmt = mysqli_prepare($con, $sql);
    if (!$stmt) throw new RuntimeException(mysqli_error($con));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
};

$registrarLogChecklist = static function (mysqli $con, string $dataHora, string $acao, string $matricula, string $matAutor, string $tipo, string $placa): void {
    $stmt = mysqli_prepare($con, 'INSERT INTO tblog (data_e_hora, acao, matricula, mat_autor, tipo, placa) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        error_log('[checklist-log] ' . mysqli_error($con));
        return;
    }
    mysqli_stmt_bind_param($stmt, 'ssssss', $dataHora, $acao, $matricula, $matAutor, $tipo, $placa);
    if (!mysqli_stmt_execute($stmt)) error_log('[checklist-log] ' . mysqli_stmt_error($stmt));
};

$campos = array_keys($dadosFormulario);
$dados = array_values($dadosFormulario);
$campos[] = 'statusreg';
$dados[] = '0';

mysqli_begin_transaction($con);
try {
    try {
        $manutencao = $buscarLinha($con, "SELECT status FROM `{$databaseName}`.`tbmanprev` WHERE placa = ? ORDER BY idtbmanprev DESC LIMIT 1", 's', [$placa]);
        if (strtoupper(trim((string) ($manutencao['status'] ?? ''))) === 'ABERTO') {
            throw new RuntimeException('Veículo em manutenção aberta. Vistoria não autorizada.');
        }
    } catch (Throwable $e) {
        throw new RuntimeException('Erro ao verificar manutenção: ' . $e->getMessage());
    }

    try {
        if ($dadosFormulario['tipo'] === '2' && $dadosFormulario['matricula'] !== '') {
            $veiculoAssociado = $buscarLinha($con, "SELECT placa FROM `{$databaseName}`.`tbveiculo` WHERE matcond = ? AND placa <> ? AND placa IS NOT NULL AND placa <> '' LIMIT 1", 'ss', [$dadosFormulario['matricula'], $placa]);
            if ($veiculoAssociado) {
                throw new RuntimeException('Condutor já possui outra placa associada: ' . (string) $veiculoAssociado['placa'] . '.');
            }
        }
    } catch (Throwable $e) {
        throw new RuntimeException('Erro ao verificar placa do condutor: ' . $e->getMessage());
    }

    try {
        $marcadores = implode(',', array_fill(0, count($campos), '?'));
        $sql = 'INSERT INTO `' . $databaseName . '`.`tbvistoria` (`' . implode('`,`', $campos) . "`) VALUES ($marcadores)";
        $stmt = mysqli_prepare($con, $sql);
        if (!$stmt) throw new RuntimeException(mysqli_error($con));
        mysqli_stmt_bind_param($stmt, str_repeat('s', count($dados)), ...$dados);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        $id = mysqli_insert_id($con);
    } catch (Throwable $e) {
        throw new RuntimeException('Erro ao inserir vistoria: ' . $e->getMessage());
    }

    try {
        $executar(
            $con,
            "UPDATE `{$databaseName}`.`tbveiculo` SET hodometro = ?, statusvel = ?, ccusto = ?, datamovimentacao = ? WHERE placa = ?",
            'sssss',
            [$dadosFormulario['hodometro'], $dadosFormulario['statusveic'], $dadosFormulario['centrocusto'], $datavistoria, $placa]
        );
    } catch (Throwable $e) {
        throw new RuntimeException('Erro ao atualizar veículo (hodômetro/status): ' . $e->getMessage());
    }

    try {
        if ($dadosFormulario['tipo'] === '2') {
            $executar(
                $con,
                "UPDATE `{$databaseName}`.`tbveiculo` SET matcond = ?, statusvel = ?, status = ?, datamovimentacao = ?, ccusto = ?, unidade = ? WHERE placa = ?",
                'sssssss',
                [$dadosFormulario['matricula'], $dadosFormulario['statusveic'], $statusOperacional, $datavistoria, $dadosFormulario['centrocusto'], $dadosFormulario['unidade'], $placa]
            );

            $condutorAtivo = $buscarLinha($con, "SELECT idtbcondutor, placaassoc FROM `{$databaseName}`.`tbcondutor` WHERE matricula = ? AND ativo = 1 ORDER BY idtbcondutor DESC LIMIT 1", 's', [$dadosFormulario['matricula']]);
            if ($condutorAtivo && (string) $condutorAtivo['placaassoc'] !== $placa) {
                $executar($con, "UPDATE `{$databaseName}`.`tbcondutor` SET datadissoc = ?, ativo = 0, statuscond = '' WHERE idtbcondutor = ?", 'si', [$datavistoria, (int) $condutorAtivo['idtbcondutor']]);
            }
            if (!$condutorAtivo || (string) $condutorAtivo['placaassoc'] !== $placa) {
                $executar($con, "INSERT INTO `{$databaseName}`.`tbcondutor` (nome, matricula, ativo, placaassoc, dataassoc, statuscond) VALUES (?, ?, 1, ?, ?, 'COM VEICULO VINCULADO')", 'ssss', [$dadosFormulario['nome'], $dadosFormulario['matricula'], $placa, date('Y-m-d H:i:s')]);
            }

            $placaAtiva = $buscarLinha($con, "SELECT idtbcondutor, matricula FROM `{$databaseName}`.`tbcondutor` WHERE placaassoc = ? AND ativo = 1 AND matricula <> ? ORDER BY idtbcondutor DESC LIMIT 1", 'ss', [$placa, $dadosFormulario['matricula']]);
            if ($placaAtiva) {
                $executar($con, "UPDATE `{$databaseName}`.`tbcondutor` SET datadissoc = ?, ativo = 0, statuscond = '' WHERE idtbcondutor = ?", 'si', [$datavistoria, (int) $placaAtiva['idtbcondutor']]);
            }
            $registrarLogChecklist($con, date('Y-m-d H:i:s'), 'Recebeu veículo por vistoria/checklist', $dadosFormulario['matricula'], $dadosFormulario['matrvistoriador'], 'checklist', $placa);
        } elseif ($dadosFormulario['tipo'] === '3') {
            $condutorAtivo = $buscarLinha($con, "SELECT idtbcondutor FROM `{$databaseName}`.`tbcondutor` WHERE placaassoc = ? AND ativo = 1 ORDER BY idtbcondutor DESC LIMIT 1", 's', [$placa]);
            if ($condutorAtivo) {
                $executar($con, "UPDATE `{$databaseName}`.`tbcondutor` SET datadissoc = ?, ativo = 0, statuscond = '' WHERE idtbcondutor = ?", 'si', [$datavistoria, (int) $condutorAtivo['idtbcondutor']]);
            }
            $executar(
                $con,
                "UPDATE `{$databaseName}`.`tbveiculo` SET datamovimentacao = ?, status = ?, statusvel = ?, matcond = '', ccusto = ?, unidade = ? WHERE placa = ?",
                'ssssss',
                [$datavistoria, $statusOperacional, $dadosFormulario['statusveic'], $dadosFormulario['centrocusto'], $dadosFormulario['unidade'], $placa]
            );
            $registrarLogChecklist($con, date('Y-m-d H:i:s'), 'Devolveu veículo por vistoria/checklist', $dadosFormulario['matricula'], $dadosFormulario['matrvistoriador'], 'checklist', $placa);
        }
    } catch (Throwable $e) {
        throw new RuntimeException('Erro ao processar condutor/veículo: ' . $e->getMessage());
    }

    // Segue o fluxo legado: cria apenas o registro-base para o Passo 2.
    // O schema não possui DEFAULT nas colunas de foto, portanto todas precisam
    // receber string vazia até que cada upload do Passo 2 atualize sua coluna.
    try {
        $fotoVazia = '';
        $idVistoriaFotos = (string) $id;
        $stmtFotos = mysqli_prepare(
            $con,
            "INSERT INTO `{$databaseName}`.`tbvistoriafotos`
            (placa, frontal, traseira, direita, esquerda, bateria, selfie, cnh,
             extra1, extra2, extra3, extra4, extra5, datavistoriaf, idtbvistoria, painel)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmtFotos) throw new RuntimeException(mysqli_error($con));
        mysqli_stmt_bind_param(
            $stmtFotos,
            'ssssssssssssssss',
            $placa,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $fotoVazia,
            $datavistoria,
            $idVistoriaFotos,
            $fotoVazia
        );
        if (!mysqli_stmt_execute($stmtFotos)) throw new RuntimeException(mysqli_stmt_error($stmtFotos));
    } catch (Throwable $e) {
        throw new RuntimeException('Erro ao criar registro de fotos: ' . $e->getMessage());
    }

    mysqli_commit($con);

    $query = http_build_query(['placa'=>$placa, 'datavistoria'=>$data, 'matricula'=>$dadosFormulario['matricula'], 'idinserido'=>$id]);
    
    // Retorna JSON com sucesso
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Checklist salvo com sucesso. Redirecionando...',
        'redirecionarPara' => '/checklist/checklistp2.php?' . $query,
        'id' => $id,
        'placa' => $placa
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $erro) {
    mysqli_rollback($con);
    $mensagemErro = $erro->getMessage();
    
    http_response_code(500);
    
    // Mensagens mais descritivas baseadas no tipo de erro
    $mensagemUsuario = 'Erro ao salvar o checklist';
    if (strpos($mensagemErro, 'manutenção aberta') !== false) {
        $mensagemUsuario = 'Veículo em manutenção aberta. Não é possível registrar vistoria.';
    } elseif (strpos($mensagemErro, 'já possui outra placa') !== false) {
        $mensagemUsuario = $mensagemErro;
    } elseif (strpos($mensagemErro, 'Duplicate entry') !== false) {
        $mensagemUsuario = 'Erro de duplicação de dados. Verifique se o checklist já foi registrado.';
    } elseif (strpos($mensagemErro, 'Foreign key') !== false) {
        $mensagemUsuario = 'Erro de referência. Verifique se a placa ou matrícula existem no sistema.';
    }
    
    $resposta = [
        'sucesso' => false,
        'mensagem' => $mensagemUsuario
    ];
    
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resposta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}