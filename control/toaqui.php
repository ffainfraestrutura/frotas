<?php
require_once __DIR__ . '/../includes/autofrota_common.php';
$sessao = autofrotaInit();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../localizacao.php');
    exit;
}

$conn = $sessao['conn'] ?? null;
$databaseName = (string) ($sessao['databaseName'] ?? '');
$databaseCorp = (string) ($sessao['databaseCorp'] ?? '');
$matricula = trim((string) ($sessao['matricula'] ?? ''));
$token = (string) ($_POST['token'] ?? '');
$motivo = trim((string) ($_POST['motivo'] ?? ''));
$observacao = trim((string) ($_POST['justificativa'] ?? ''));
$endereco = trim((string) ($_POST['endereco'] ?? ''));
$latitude = filter_var($_POST['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
$longitude = filter_var($_POST['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
$motivosPermitidos = ['base', 'ponto de encontro', 'matriz', 'atividade'];

$falhar = static function (string $mensagem, ?string $detalhe = null) use ($latitude, $longitude): void {
    $texto = $mensagem;
    if ($detalhe !== null && trim((string) $detalhe) !== '') {
        $detalhe = preg_replace('/\s+/', ' ', trim((string) $detalhe));
        $detalhe = mb_substr((string) $detalhe, 0, 300, 'UTF-8');
        $texto .= ' Detalhe: ' . $detalhe;
    }

    $_SESSION['toaqui_mensagem'] = $texto;
    $_SESSION['toaqui_tipo'] = 'danger';
    $query = ($latitude !== false && $longitude !== false)
        ? '?' . http_build_query(['latitude' => $latitude, 'longitude' => $longitude])
        : '';
    header('Location: ../toaqui.php' . $query);
    exit;
};

if (!$conn instanceof mysqli || !preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
    $falhar('A conexão com o banco de dados está indisponível.');
}
if ($token === '' || !hash_equals((string) ($_SESSION['toaqui_token'] ?? ''), $token)) {
    $falhar('A sessão do formulário expirou. Atualize a localização e tente novamente.');
}
unset($_SESSION['toaqui_token']);
if ($matricula === '' || !in_array($motivo, $motivosPermitidos, true)) {
    $falhar('Informe um motivo válido.');
}
if ($latitude === false || $longitude === false || $latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
    header('Location: ../localizacao.php');
    exit;
}

if ($motivo === 'atividade') {
    $centrosAtividade = [
        'CLARO - REDE EXTERNA', 'OPERAÇÃO - CLARO - MAN', 'OPERAÇÃO -CLARO -OSP GDE OBRAS',
        'OPERAÇÃO -CLARO -OSP PEQ OBRAS', 'TIM RJ - IMPLANTAÇÃO', 'TIM RJ - REDE EXTERNA',
        'TIM SP - IMPLANTAÇÃO', 'TIM SP - REDE EXTERNA', 'TIM SP IMPLANTAÇÃO',
    ];
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseCorp)) {
        $falhar('O motivo Atividade não está disponível para sua matrícula.');
    }
    $placeholders = implode(',', array_fill(0, count($centrosAtividade), '?'));
    $consultaCentro = consultaPreparada(
        $conn,
        "SELECT 1 FROM `{$databaseCorp}`.`tbfuncionario` WHERE matricula = ? AND ccusto IN ({$placeholders}) LIMIT 1",
        's' . str_repeat('s', count($centrosAtividade)),
        array_merge([$matricula], $centrosAtividade)
    );
    if (($consultaCentro['linhas'] ?? []) === []) {
        $falhar('O motivo Atividade não está disponível para sua matrícula.');
    }
}

$observacao = mb_substr($observacao, 0, 2000);
$endereco = preg_replace('/\s+/', ' ', trim($endereco));
$endereco = mb_substr($endereco, 0, 180, 'UTF-8');
if ($endereco === '') {
    $endereco = sprintf('Latitude %.7F, Longitude %.7F', $latitude, $longitude);
}
$agora = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$dataHora = $agora->format('Y-m-d H:i:s');
$data = $agora->format('Y-m-d');

$inserirToaqui = static function (string $enderecoFinal) use ($conn, $databaseName, $matricula, $motivo, $observacao, $latitude, $longitude, $dataHora, $data): array {
    return consultaPreparada(
        $conn,
        "INSERT INTO `{$databaseName}`.`tbtoaqui` (matricula, motivo, obs, latitude, longitude, hora, data, endereco) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'sssddsss',
        [$matricula, $motivo, $observacao, $latitude, $longitude, $dataHora, $data, $enderecoFinal]
    );
};

$resultado = $inserirToaqui($endereco);
if (($resultado['erro'] ?? '') !== '' && stripos((string) $resultado['erro'], 'Data too long for column') !== false && stripos((string) $resultado['erro'], 'endereco') !== false) {
    $endereco = mb_substr($endereco, 0, 120, 'UTF-8');
    if ($endereco === '') {
        $endereco = sprintf('Latitude %.7F, Longitude %.7F', $latitude, $longitude);
    }
    $resultado = $inserirToaqui($endereco);
}
if (($resultado['erro'] ?? '') !== '') {
    $falhar('Não foi possível registrar sua localização.', $resultado['erro']);
}

require_once __DIR__ . '/../func/log.php';
enviarlog($dataHora, 'criou rota to aqui', 2, $matricula, $matricula, 0, 0);
$_SESSION['toaqui_mensagem'] = 'Localização registrada com sucesso!';
$_SESSION['toaqui_tipo'] = 'success';
header('Location: ../tecnico.php');
exit;
