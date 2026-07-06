<?php
require_once __DIR__ . '/../includes/autofrota_common.php';

$autofrotaSessao = autofrotaInit();
$databaseName = (string) ($autofrotaSessao['databaseName'] ?? '');
if ($databaseName === '') {
    $databaseName = 'bdautofrotas';
}
$databaseCorp = (string) ($autofrotaSessao['databaseCorp'] ?? '');
if ($databaseCorp === '') {
    $databaseCorp = 'bdcorp';
}

if (($_POST['action'] ?? '') !== 'exportar_hierarquia_completa') {
    http_response_code(400);
    exit('Ação inválida');
}

/** @var mysqli|null $conn */
$conn = $autofrotaSessao['conn'] ?? null;
if (!$conn instanceof mysqli) {
    http_response_code(500);
    exit('Conexão com banco não disponível.');
}

function excelEquipeValor(?string $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function excelEquipeConsulta(mysqli $conn, string $sql): array
{
    $dados = [];
    $resultado = mysqli_query($conn, $sql);

    if ($resultado === false) {
        return $dados;
    }

    while ($linha = mysqli_fetch_assoc($resultado)) {
        $dados[] = $linha;
    }

    mysqli_free_result($resultado);
    return $dados;
}

$arquivo = 'Hierarquia-Portal-' . date('Y-m-d_H-i-s') . '.xls';
$linhas = [];

$sqlGerentes = "
    SELECT DISTINCT g.idtbgerente, u.matricula, u.nome
    FROM {$databaseCorp}.tbgerente g
    INNER JOIN {$databaseCorp}.tbusuario u ON u.matricula = g.matricula
    ORDER BY u.nome
";

foreach (excelEquipeConsulta($conn, $sqlGerentes) as $gerente) {
    $gerenteId = (int) ($gerente['idtbgerente'] ?? 0);
    $gerenteNome = (string) ($gerente['nome'] ?? '');

    $linhas[] = [
        'nivel' => '1',
        'tipo' => 'GERENTE',
        'matricula' => (string) ($gerente['matricula'] ?? ''),
        'nome' => $gerenteNome,
        'cargo' => 'Gerente',
        'gerente' => $gerenteNome,
        'coordenador' => '',
        'supervisor' => '',
        'estilo' => 'background-color: #E7F3FF; font-weight: bold;',
    ];

    $sqlCoordenadores = "
        SELECT c.idtbcoordenador, u.matricula, u.nome, 'Coordenador' AS cargo
        FROM {$databaseCorp}.tbcoord c
        INNER JOIN {$databaseCorp}.tbusuario u ON u.matricula = c.matricula
        WHERE c.idtbgerente = {$gerenteId}
        ORDER BY u.nome
    ";

    foreach (excelEquipeConsulta($conn, $sqlCoordenadores) as $coordenador) {
        $coordenadorId = (int) ($coordenador['idtbcoordenador'] ?? 0);
        $coordenadorNome = (string) ($coordenador['nome'] ?? '');

        $linhas[] = [
            'nivel' => '2',
            'tipo' => 'COORDENADOR',
            'matricula' => (string) ($coordenador['matricula'] ?? ''),
            'nome' => $coordenadorNome,
            'cargo' => (string) ($coordenador['cargo'] ?? ''),
            'gerente' => $gerenteNome,
            'coordenador' => $coordenadorNome,
            'supervisor' => '',
            'estilo' => 'background-color: #F0F8FF;',
        ];

        $sqlSupervisores = "
            SELECT s.idtbsupervisor, u.matricula, u.nome, 'Supervisor' AS cargo
            FROM {$databaseCorp}.tbsupervisor s
            INNER JOIN {$databaseCorp}.tbusuario u ON u.matricula = s.matricula
            WHERE s.idtbcoordenador = {$coordenadorId}
            ORDER BY u.nome
        ";

        foreach (excelEquipeConsulta($conn, $sqlSupervisores) as $supervisor) {
            $supervisorId = (int) ($supervisor['idtbsupervisor'] ?? 0);
            $supervisorNome = (string) ($supervisor['nome'] ?? '');

            $linhas[] = [
                'nivel' => '3',
                'tipo' => 'SUPERVISOR',
                'matricula' => (string) ($supervisor['matricula'] ?? ''),
                'nome' => $supervisorNome,
                'cargo' => (string) ($supervisor['cargo'] ?? ''),
                'gerente' => $gerenteNome,
                'coordenador' => $coordenadorNome,
                'supervisor' => $supervisorNome,
                'estilo' => 'background-color: #F8F8FF;',
            ];

            $sqlTecnicos = "
                SELECT matricula, nome, 'Técnico' AS cargo
                                                                FROM {$databaseCorp}.tbusuario
                WHERE perfil = 0
                  AND idtbsupervisor = {$supervisorId}
                ORDER BY nome
            ";

            foreach (excelEquipeConsulta($conn, $sqlTecnicos) as $tecnico) {
                $linhas[] = [
                    'nivel' => '4',
                    'tipo' => 'TÉCNICO',
                    'matricula' => (string) ($tecnico['matricula'] ?? ''),
                    'nome' => (string) ($tecnico['nome'] ?? ''),
                    'cargo' => (string) ($tecnico['cargo'] ?? ''),
                    'gerente' => $gerenteNome,
                    'coordenador' => $coordenadorNome,
                    'supervisor' => $supervisorNome,
                    'estilo' => '',
                ];
            }
        }
    }

    $linhas[] = null;
}

header('Expires: Mon, 07 Jul 2016 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $arquivo . '"');
header('Content-Description: PHP Generated Data');

echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head><meta charset="utf-8"><title>Hierarquia AutoFrota</title></head>
<body>
<table border="1">
    <tr>
        <th>Nível</th>
        <th>Tipo</th>
        <th>Matrícula</th>
        <th>Nome</th>
        <th>Cargo</th>
        <th>Gerente</th>
        <th>Coordenador</th>
        <th>Supervisor</th>
    </tr>
    <?php foreach ($linhas as $linha): ?>
        <?php if ($linha === null): ?>
            <tr><td colspan="8">&nbsp;</td></tr>
        <?php else: ?>
            <tr style="<?= excelEquipeValor($linha['estilo']) ?>">
                <td><?= excelEquipeValor($linha['nivel']) ?></td>
                <td><?= excelEquipeValor($linha['tipo']) ?></td>
                <td><?= excelEquipeValor($linha['matricula']) ?></td>
                <td><?= excelEquipeValor($linha['nome']) ?></td>
                <td><?= excelEquipeValor($linha['cargo']) ?></td>
                <td><?= excelEquipeValor($linha['gerente']) ?></td>
                <td><?= excelEquipeValor($linha['coordenador']) ?></td>
                <td><?= excelEquipeValor($linha['supervisor']) ?></td>
            </tr>
        <?php endif; ?>
    <?php endforeach; ?>
</table>
</body>
</html>