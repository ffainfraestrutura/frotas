<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/toaqui.php';

$contexto = toaquiContextoSupervisor();
$conn = $contexto['conn'];

function voltarToAqui(string $mensagem, string $tipo = 'danger'): void
{
    $_SESSION['toaqui_mensagem'] = $mensagem;
    $_SESSION['toaqui_tipo_mensagem'] = $tipo;
    header('Location: ../aceite-toaqui.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarToAqui('Método de requisição inválido.');
}

$token = (string) ($_POST['token'] ?? '');
if ($token === '' || !hash_equals((string) ($_SESSION['toaqui_token'] ?? ''), $token)) {
    voltarToAqui('A sessão do formulário expirou. Atualize a página e tente novamente.');
}
unset($_SESSION['toaqui_token']);

$id = filter_input(INPUT_POST, 'idtbtoaqui', FILTER_VALIDATE_INT);
$decisao = filter_input(INPUT_POST, 'decisao', FILTER_VALIDATE_INT);
if (!$id || !in_array($decisao, [1, 2], true)) {
    voltarToAqui('Selecione um apontamento e uma decisão válida.');
}

mysqli_begin_transaction($conn);
try {
    $registro = buscarUmaLinha(
        $conn,
        "SELECT t.idtbtoaqui, t.matricula, t.endereco, t.latitude, t.longitude,
                DATE(t.data) AS data_rota, COALESCE(TIME(t.hora), TIME(t.data)) AS hora_rota,
                COALESCE(f.ccusto, '') AS ccusto
           FROM `{$contexto['databaseName']}`.`tbtoaqui` t
           INNER JOIN `{$contexto['databaseCorp']}`.`tbusuario` u ON u.matricula = t.matricula
           LEFT JOIN `{$contexto['databaseCorp']}`.`tbfuncionario` f ON f.matricula = t.matricula
          WHERE t.idtbtoaqui = ? AND t.aceite = 0 AND u.idtbsupervisor = ?
          FOR UPDATE",
        'ii',
        [$id, $contexto['idtbsupervisor']]
    );

    if ($registro === []) {
        throw new RuntimeException('O apontamento não existe, já foi decidido ou não pertence à sua equipe.');
    }

    if ($decisao === 1) {
        if (trim((string) $registro['endereco']) === '') {
            throw new RuntimeException('Informe/converta o endereço do apontamento antes de aprová-lo.');
        }

        $tabelaRota = stripos((string) $registro['ccusto'], 'Claro') !== false ? 'tbosrotalatlong' : 'tbosrota';
        $stmtRota = mysqli_prepare(
            $conn,
            "INSERT INTO `{$contexto['databaseName']}`.`{$tabelaRota}`
                (endereco, data, eta, fim, latitude, longitude, matricula, numero_de_ordem)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'To Aqui')"
        );
        if (!$stmtRota) {
            throw new RuntimeException('Não foi possível preparar a inclusão na rota.');
        }
        mysqli_stmt_bind_param(
            $stmtRota,
            'sssssss',
            $registro['endereco'],
            $registro['data_rota'],
            $registro['hora_rota'],
            $registro['hora_rota'],
            $registro['latitude'],
            $registro['longitude'],
            $registro['matricula']
        );
        if (!mysqli_stmt_execute($stmtRota)) {
            throw new RuntimeException('Não foi possível incluir o apontamento na rota: ' . mysqli_stmt_error($stmtRota));
        }
        mysqli_stmt_close($stmtRota);
    }

    $stmt = mysqli_prepare($conn, "UPDATE `{$contexto['databaseName']}`.`tbtoaqui` SET aceite = ?, dthoraaceite = NOW() WHERE idtbtoaqui = ? AND aceite = 0");
    if (!$stmt) {
        throw new RuntimeException('Não foi possível preparar a atualização do apontamento.');
    }
    mysqli_stmt_bind_param($stmt, 'ii', $decisao, $id);
    if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
        throw new RuntimeException('O apontamento foi alterado por outro usuário. Atualize a tela.');
    }
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    voltarToAqui($decisao === 1 ? 'Apontamento aprovado e incluído na rota.' : 'Apontamento rejeitado com sucesso.', 'success');
} catch (Throwable $exception) {
    mysqli_rollback($conn);
    voltarToAqui($exception->getMessage());
}
