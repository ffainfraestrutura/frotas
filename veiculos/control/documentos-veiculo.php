<?php

require_once __DIR__ . '/../../includes/portal_helpers.php';

function salvarUploadsDocumentosVeiculo(string $placa): array
{
    $campos = ['crlv' => 'crlv', 'crv' => 'crv', 'ipva' => 'cert_ipva'];
    $extensoesPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
    $tamanhoMaximo = 32 * 1024 * 1024;
    $diretorio = rtrim((string) (getenv('FROTAS_UPLOAD_DIR') ?: diretorioUploadsPortal()), '/\\') . DIRECTORY_SEPARATOR;
    $documentos = [];

    if (!is_dir($diretorio) && !@mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Não foi possível preparar a pasta de documentos dos veículos.');
    }
    if (!is_writable($diretorio)) {
        throw new RuntimeException('A pasta de documentos dos veículos não possui permissão de escrita.');
    }

    foreach ($campos as $campo => $coluna) {
        if (empty($_FILES[$campo]['name'])) {
            continue;
        }

        $erro = (int) ($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro !== UPLOAD_ERR_OK || empty($_FILES[$campo]['tmp_name']) || !is_uploaded_file($_FILES[$campo]['tmp_name'])) {
            throw new RuntimeException('Não foi possível fazer o upload do documento ' . strtoupper($campo) . '.');
        }

        $extensao = strtolower((string) pathinfo((string) $_FILES[$campo]['name'], PATHINFO_EXTENSION));
        if (!in_array($extensao, $extensoesPermitidas, true)) {
            throw new RuntimeException('Envie os documentos apenas nos formatos PDF, JPG, JPEG ou PNG.');
        }
        if ((int) ($_FILES[$campo]['size'] ?? 0) > $tamanhoMaximo) {
            throw new RuntimeException('Cada documento deve possuir no máximo 32 MB.');
        }

        $placaSegura = preg_replace('/[^A-Z0-9_-]/i', '', $placa);
        $nomeFinal = $placaSegura . '-' . date('YmdHis') . '-' . $campo . '.' . $extensao;
        if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $diretorio . $nomeFinal)) {
            throw new RuntimeException('Não foi possível gravar o documento ' . strtoupper($campo) . '.');
        }

        $documentos[$coluna] = urlDocumentoUploadPortal($nomeFinal);
    }

    return $documentos;
}

function persistirDocumentosVeiculo(mysqli $conn, string $databaseName, string $placa, array $documentos): void
{
    if ($documentos === []) {
        return;
    }

    $schema = str_replace('`', '``', $databaseName);
    $stmtBusca = mysqli_prepare($conn, "SELECT idtbveicdocs FROM `{$schema}`.`tbveicdocs` WHERE placa = ? LIMIT 1");
    if (!$stmtBusca) {
        throw new RuntimeException('Não foi possível consultar os documentos atuais do veículo.');
    }
    mysqli_stmt_bind_param($stmtBusca, 's', $placa);
    mysqli_stmt_execute($stmtBusca);
    $resultado = mysqli_stmt_get_result($stmtBusca);
    $registro = $resultado ? mysqli_fetch_assoc($resultado) : null;
    mysqli_stmt_close($stmtBusca);

    if ($registro) {
        $atribuicoes = [];
        $valores = [];
        foreach (['crlv', 'crv', 'cert_ipva'] as $coluna) {
            if (isset($documentos[$coluna])) {
                $atribuicoes[] = "`{$coluna}` = ?";
                $valores[] = $documentos[$coluna];
            }
        }
        $valores[] = $placa;
        $stmt = mysqli_prepare($conn, "UPDATE `{$schema}`.`tbveicdocs` SET " . implode(', ', $atribuicoes) . ' WHERE placa = ?');
        $tipos = str_repeat('s', count($valores));
    } else {
        $crlv = (string) ($documentos['crlv'] ?? '');
        $crv = (string) ($documentos['crv'] ?? '');
        $ipva = (string) ($documentos['cert_ipva'] ?? '');
        $valores = [$placa, $crlv, $crv, $ipva];
        $stmt = mysqli_prepare($conn, "INSERT INTO `{$schema}`.`tbveicdocs` (placa, crlv, crv, cert_ipva) VALUES (?, ?, ?, ?)");
        $tipos = 'ssss';
    }

    if (!$stmt) {
        throw new RuntimeException('Não foi possível preparar o registro dos documentos do veículo.');
    }
    $parametros = [$stmt, $tipos];
    foreach ($valores as &$valor) {
        $parametros[] = &$valor;
    }
    unset($valor);
    call_user_func_array('mysqli_stmt_bind_param', $parametros);
    if (!mysqli_stmt_execute($stmt)) {
        $erro = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Não foi possível registrar os documentos do veículo: ' . $erro);
    }
    mysqli_stmt_close($stmt);
}