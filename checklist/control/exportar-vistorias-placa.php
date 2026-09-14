<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/autofrota_common.php';
$autofrota = autofrotaInit();

$conn = $autofrota['conn'] ?? null;
$databaseName = (string) ($autofrota['databaseName'] ?? '');
$perfil = trim((string) ($autofrota['perfil'] ?? ''));
$placa = strtoupper(trim((string) ($_POST['placa'] ?? '')));
$placa = preg_replace('/[^A-Z0-9]/', '', $placa) ?? '';

if ($perfil !== '4') {
    http_response_code(403);
    exit('Acesso permitido apenas para perfil 4.');
}

if (!$conn instanceof mysqli || preg_match('/^[A-Za-z0-9_]+$/', $databaseName) !== 1) {
    http_response_code(503);
    exit('Não foi possível conectar à base de vistorias.');
}

if ($placa === '') {
    http_response_code(400);
    exit('Informe uma placa válida.');
}

$colunas = [
    'nome' => 'Nome do Condutor', 'matricula' => 'Matrícula do Condutor',
    'cpf' => 'CPF do Condutor', 'cnh' => 'CNH do Condutor',
    'categoriacnh' => 'Categoria CNH do Condutor', 'validadecnh' => 'Validade CNH do Condutor',
    'placa' => 'Placa', 'modelo' => 'Modelo', 'anofabricacao' => 'Ano de Fabricação',
    'unidade' => 'Unidade', 'centrocusto' => 'Centro de Custo', 'tipo_vistoria' => 'Tipo de Vistoria',
    'vistoriador' => 'Vistoriador', 'matrvistoriador' => 'Matrícula do Vistoriador',
    'datavistoria' => 'Data da Vistoria', 'estado' => 'Estado do Veículo', 'avaria' => 'Possui avaria?',
    'hodometro' => 'Hodômetro', 'niveltanque' => 'Nível do Tanque', 'observacao' => 'Observação',
    'documentacao' => 'Documentação do Veículo', 'teto' => 'Teto', 'parabrisa' => 'Para-brisa',
    'capo' => 'Capô', 'faroldir' => 'Farol Direito', 'farolesq' => 'Farol Esquerdo',
    'parachoque' => 'Para-choque Dianteiro', 'paralamaesq' => 'Para-lama Esquerdo',
    'retrovesq' => 'Retrovisor Esquerdo', 'cxaresq' => 'Caixa de Ar Esquerda',
    'ptdiantesq' => 'Porta Dianteira Esquerda', 'pttrasesq' => 'Porta Traseira Esquerda',
    'tetoesq' => 'Coluna do Teto Esquerda', 'lantesq' => 'Lanterna Esquerda',
    'lantdir' => 'Lanterna Direita', 'tmpmala' => 'Tampa da Mala', 'parachoquet' => 'Para-choque Traseiro',
    'kitstep' => 'Kit Estepe', 'paralamadir' => 'Para-lama Direito', 'retrovdir' => 'Retrovisor Direito',
    'cxardir' => 'Caixa de Ar Direita', 'ptdiantdir' => 'Porta Dianteira Direita',
    'pttrasdir' => 'Porta Traseira Direita', 'tetodir' => 'Coluna do Teto Direita',
    'painel' => 'Painel', 'som' => 'Som', 'ilumint' => 'Iluminação Interna',
    'retrovint' => 'Retrovisor Interno', 'bancos' => 'Bancos', 'tapetes' => 'Tapetes',
    'tmpbag' => 'Tampa do Bagagito', 'calotas' => 'Calotas', 'bateria' => 'Bateria',
    'safecar' => 'Safe Car', 'marcapneus' => 'Marca dos Pneus', 'limpext' => 'Limpeza Externa',
    'limpint' => 'Limpeza Interna', 'assinado' => 'Relatório assinado pelo condutor?',
    'status_veiculo' => 'Status do Veículo',
];

$camposVistoria = array_keys($colunas);
$camposVistoria = array_values(array_diff($camposVistoria, ['tipo_vistoria', 'assinado', 'status_veiculo']));
$selectCampos = implode(', ', array_map(static fn(string $campo): string => "v.`{$campo}`", $camposVistoria));
$sql = "SELECT {$selectCampos},
               COALESCE(tv.tipo, v.tipo) AS tipo_vistoria,
               CASE WHEN v.assinadocond = 1 THEN 'SIM' ELSE 'NÃO' END AS assinado,
               COALESCE(sv.status, v.statusveic) AS status_veiculo
          FROM `{$databaseName}`.`tbvistoria` v
     LEFT JOIN `{$databaseName}`.`tbatipovist` tv ON tv.idtbatipovist = v.tipo
     LEFT JOIN `{$databaseName}`.`tbvelstatus` sv ON sv.idtbastatusvel = v.statusveic
         WHERE REPLACE(REPLACE(UPPER(v.placa), '-', ''), ' ', '') = ?
           AND v.placa <> ''
           AND REPLACE(REPLACE(UPPER(v.placa), '-', ''), ' ', '') NOT IN ('ABC1234', 'ABC1245', 'ABC1122')
           AND (v.idtbvistoria < 2995 OR v.idtbvistoria > 3015)
           AND v.idtbvistoria NOT IN (3028, 3031, 3032, 4719, 4718)
           AND v.statusreg = 1
      ORDER BY v.datavistoria DESC";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    http_response_code(500);
    exit('Não foi possível preparar a exportação.');
}

mysqli_stmt_bind_param($stmt, 's', $placa);
if (!mysqli_stmt_execute($stmt)) {
    mysqli_stmt_close($stmt);
    http_response_code(500);
    exit('Não foi possível gerar a exportação.');
}
$resultado = mysqli_stmt_get_result($stmt);

$excelEsc = static function (mixed $valor): string {
    $texto = (string) $valor;
    if (preg_match('/^[=+\-@]/', $texto) === 1) {
        $texto = "'" . $texto;
    }
    return htmlspecialchars($texto, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};
$formatarValor = static function (string $campo, mixed $valor): string {
    $texto = (string) $valor;
    if ($campo === 'datavistoria' && ($data = date_create($texto))) {
        return $data->format('d/m/Y H:i:s');
    }
    if ($campo === 'avaria') {
        return $texto === '1' ? 'SIM' : ($texto === '0' ? 'NÃO' : $texto);
    }
    if (in_array($campo, ['estado', 'teto', 'parabrisa', 'capo', 'faroldir', 'farolesq', 'parachoque',
        'paralamaesq', 'retrovesq', 'cxaresq', 'ptdiantesq', 'pttrasesq', 'tetoesq', 'lantesq',
        'lantdir', 'tmpmala', 'parachoquet', 'kitstep', 'paralamadir', 'retrovdir', 'cxardir',
        'ptdiantdir', 'pttrasdir', 'tetodir', 'painel', 'som', 'ilumint', 'retrovint', 'bancos',
        'tapetes', 'tmpbag', 'calotas', 'bateria', 'safecar', 'marcapneus', 'limpext', 'limpint'], true)) {
        $texto = strtoupper($texto);
        return $texto === 'NAOOK' ? 'NÃO OK' : $texto;
    }
    return $texto;
};

$arquivo = 'vistorias-' . $placa . '-' . date('Ymd-His') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo "\xEF\xBB\xBF";
echo '<table border="1"><thead><tr>';
foreach ($colunas as $titulo) {
    echo '<th>' . $excelEsc($titulo) . '</th>';
}
echo '</tr></thead><tbody>';
if ($resultado instanceof mysqli_result) {
    while ($vistoria = mysqli_fetch_assoc($resultado)) {
        echo '<tr>';
        foreach ($colunas as $campo => $_titulo) {
            echo '<td>' . $excelEsc($formatarValor($campo, $vistoria[$campo] ?? '')) . '</td>';
        }
        echo '</tr>';
    }
    mysqli_free_result($resultado);
}
echo '</tbody></table>';
mysqli_stmt_close($stmt);