<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/toaqui.php';

$contexto = toaquiContextoSupervisor();
$matriculaTecnico = trim((string) ($_GET['matricula'] ?? ''));

if ($matriculaTecnico === '') {
    toaquiResponderJson(['sucesso' => false, 'mensagem' => 'Matrícula não informada.'], 422);
}

$consulta = consultaPreparada(
    $contexto['conn'],
    "SELECT t.idtbtoaqui, t.motivo, t.obs, t.endereco, t.latitude, t.longitude, t.data, t.hora
       FROM `{$contexto['databaseName']}`.`tbtoaqui` t
       INNER JOIN `{$contexto['databaseCorp']}`.`tbusuario` u ON u.matricula = t.matricula
      WHERE t.matricula = ? AND t.aceite = 0 AND u.idtbsupervisor = ?
      ORDER BY COALESCE(t.data, t.hora), t.idtbtoaqui",
    'si',
    [$matriculaTecnico, $contexto['idtbsupervisor']]
);

if ($consulta['erro'] !== '') {
    toaquiResponderJson(['sucesso' => false, 'mensagem' => 'Não foi possível consultar os apontamentos.'], 500);
}

$registros = array_map(static function (array $linha): array {
    $dataHora = trim((string) ($linha['data'] ?? ''));
    $hora = trim((string) ($linha['hora'] ?? ''));
    if ($dataHora === '' || $dataHora === '0000-00-00 00:00:00') {
        $dataHora = $hora;
    } elseif ($hora !== '' && strlen($dataHora) <= 10) {
        $dataHora .= ' ' . substr($hora, -8);
    }
    $linha['data_formatada'] = formatarDataPortal($dataHora, 'd/m/Y H:i');
    return $linha;
}, $consulta['linhas']);

toaquiResponderJson(['sucesso' => true, 'registros' => $registros]);
