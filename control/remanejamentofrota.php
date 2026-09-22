<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/autofrota_common.php';

$sessao = autofrotaInit();
$conn = $GLOBALS['conn'] ?? null;
$databaseName = $GLOBALS['databaseName'] ?? 'bdautofrotas';

function voltarRelatorioSaldo(string $mensagem, string $detalhes = ''): never
{
    $_SESSION['rel_saldo_msg'] = $mensagem;
    if ($detalhes !== '') {
        $_SESSION['rel_saldo_alert_detalhes'] = $detalhes;
    }
    header('Location: ../relatorio-saldo-veiculos.php');
    exit;
}

function valorSaldoEmCentavos(string $valor): ?int
{
    $valor = trim(str_replace(['R$', ' '], '', $valor));
    if (str_contains($valor, ',') && str_contains($valor, '.')) {
        $valor = str_replace('.', '', $valor);
    }
    $valor = str_replace(',', '.', $valor);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $valor)) {
        return null;
    }

    $centavos = (int) round(((float) $valor) * 100);
    return $centavos >= 1 ? $centavos : null;
}

/** @return array{basic_auth:string,codigo_cliente:int,codigo_produto:int}|null */
function configuracaoTicketLogSaldo(mysqli $conn, string $databaseName): ?array
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
        return null;
    }
    $sql = "SELECT basic_auth, codigo_cliente, codigo_produto
              FROM `{$databaseName}`.`tbintegracao_ticketlog`
             WHERE ativo = 1
             ORDER BY idtbintegracao_ticketlog DESC
             LIMIT 1";
    $resultado = mysqli_query($conn, $sql);
    $linha = $resultado ? (mysqli_fetch_assoc($resultado) ?: null) : null;
    if ($resultado) {
        mysqli_free_result($resultado);
    }
    if (!$linha || trim((string) $linha['basic_auth']) === '') {
        return null;
    }

    return [
        'basic_auth' => trim((string) $linha['basic_auth']),
        'codigo_cliente' => (int) $linha['codigo_cliente'],
        'codigo_produto' => (int) $linha['codigo_produto'],
    ];
}

/** @return array{sucesso:bool,numero_cartao:string,mensagem:string} */
function buscarCartaoAtivoTicketLog(mysqli $conn, string $databaseName, string $placa): array
{
    $config = configuracaoTicketLogSaldo($conn, $databaseName);
    if (!$config) {
        return ['sucesso' => false, 'numero_cartao' => '', 'mensagem' => 'Configuração TicketLog não cadastrada.'];
    }

    $curl = curl_init('https://srv1.ticketlog.com.br/ticketlog-servicos/ebs/relatorioExtratoSimplificado/search');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $config['basic_auth'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'codigoCliente' => $config['codigo_cliente'],
            'codigoProduto' => $config['codigo_produto'],
            'placaVeiculo' => $placa,
            'situacaoCartao' => 'A',
            'ordem' => 'C',
        ], JSON_UNESCAPED_SLASHES),
    ]);
    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $erro = curl_error($curl);
    curl_close($curl);

    $dados = is_string($resposta) ? json_decode($resposta, true) : null;
    if ($erro !== '' || $status < 200 || $status >= 300 || !is_array($dados) || ($dados['sucesso'] ?? false) !== true) {
        return ['sucesso' => false, 'numero_cartao' => '', 'mensagem' => 'Não foi possível consultar o cartão na TicketLog.'];
    }
    foreach (($dados['itens'] ?? []) as $item) {
        if (is_array($item) && ($item['situacao'] ?? '') === 'A' && !empty($item['numeroCartao'])) {
            return ['sucesso' => true, 'numero_cartao' => (string) $item['numeroCartao'], 'mensagem' => ''];
        }
    }

    return ['sucesso' => false, 'numero_cartao' => '', 'mensagem' => 'Nenhum cartão ativo encontrado para a placa.'];
}

/** @return array{sucesso:bool,mensagem:string,http_status:int} */
function adicionarSaldoTicketLog(
    mysqli $conn,
    string $databaseName,
    string $numeroCartao,
    int $centavos,
    string $descricaoJustificativa
): array
{
    $config = configuracaoTicketLogSaldo($conn, $databaseName);
    if (!$config) {
        return ['sucesso' => false, 'mensagem' => 'Configuração TicketLog não cadastrada.', 'http_status' => 0];
    }

    // O contrato de /credito define numeroCartao como number, não como string.
    // A consulta devolve esse campo como texto, então é necessário convertê-lo
    // antes de montar o JSON enviado ao endpoint de crédito.
    if (!preg_match('/^\d{1,18}$/', $numeroCartao)) {
        return ['sucesso' => false, 'mensagem' => 'Número do cartão retornado pela TicketLog é inválido.', 'http_status' => 0];
    }
    $descricaoJustificativa = trim($descricaoJustificativa);
    if ($descricaoJustificativa === '') {
        return ['sucesso' => false, 'mensagem' => 'A justificativa do crédito não foi informada.', 'http_status' => 0];
    }

    $agoraSaoPaulo = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
    $payload = [
        'codigoCredito' => random_int(1, 2147483647),
        'codigoCliente' => $config['codigo_cliente'],
        'codigoProduto' => $config['codigo_produto'],
        'valorCredito' => $centavos / 100,
        'numeroCartao' => (int) $numeroCartao,
        'dataValidade' => $agoraSaoPaulo
            ->modify('+1 year')
            ->setTime(23, 59, 59)
            ->format('Y-m-d\TH:i:sP'),
        'dataLiberacao' => $agoraSaoPaulo->format('Y-m-d'),
        'descricaoJustificativa' => mb_substr($descricaoJustificativa, 0, 255),
    ];
    $corpo = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    if ($corpo === false) {
        return ['sucesso' => false, 'mensagem' => 'Não foi possível montar a requisição para a TicketLog.', 'http_status' => 0];
    }

    $payloadLog = $payload;
    $payloadLog['numeroCartao'] = '************' . substr($numeroCartao, -4);
    $corpoLog = json_encode($payloadLog, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    error_log('[TicketLog] Payload POST /credito: ' . ($corpoLog !== false ? $corpoLog : '[falha ao serializar payload de log]'));

    $curl = curl_init('https://srv1.ticketlog.com.br/ticketlog-servicos/ebs/credito');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $config['basic_auth'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $corpo,
    ]);
    $resposta = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $tipoConteudo = trim((string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE));
    $erro = curl_error($curl);
    curl_close($curl);

    if ($erro !== '' || $resposta === false) {
        return ['sucesso' => false, 'mensagem' => 'Falha de comunicação com a TicketLog: ' . ($erro !== '' ? $erro : 'resposta vazia'), 'http_status' => $status];
    }

    $dados = json_decode((string) $resposta, true);
    $mensagemApi = '';
    if (is_array($dados)) {
        foreach (['mensagem', 'errorMessage', 'message', 'erro', 'error', 'descricao'] as $campoMensagem) {
            if (isset($dados[$campoMensagem]) && is_scalar($dados[$campoMensagem])) {
                $mensagemApi = trim((string) $dados[$campoMensagem]);
                if ($mensagemApi !== '') {
                    break;
                }
            }
        }
    }

    $codigoErro = is_array($dados) && isset($dados['codigoErro']) && is_scalar($dados['codigoErro'])
        ? trim((string) $dados['codigoErro'])
        : (is_array($dados) && isset($dados['errorCode']) && is_scalar($dados['errorCode'])
            ? trim((string) $dados['errorCode'])
            : '');
    $sucessoHttp = $status >= 200 && $status < 300;
    $sucessoApi = is_array($dados) && ($dados['sucesso'] ?? false) === true;
    if (!$sucessoHttp || !$sucessoApi) {
        if ($status === 401 || $status === 403) {
            $motivo = $status === 401 ? 'não foi autenticada' : 'não possui acesso ao endpoint';
            return [
                'sucesso' => false,
                'mensagem' => 'A credencial TicketLog ' . $motivo . ' POST /ticketlog-servicos/ebs/credito (HTTP ' . $status . '). Confirme com a TicketLog a liberação dessa rota para o cliente ' . $config['codigo_cliente'] . ' e produto ' . $config['codigo_produto'] . '.',
                'http_status' => $status,
            ];
        }

        if ($codigoErro === 'OSB-380000' && stripos($mensagemApi, 'autorizad') !== false) {
            return [
                'sucesso' => false,
                'mensagem' => 'A credencial TicketLog não está autorizada a inserir créditos. Solicite à TicketLog a liberação do POST /ticketlog-servicos/ebs/credito para o cliente ' . $config['codigo_cliente'] . ' e produto ' . $config['codigo_produto'] . '.',
                'http_status' => $status,
            ];
        }

        $detalhe = 'TicketLog retornou HTTP ' . $status . '.';
        if ($codigoErro !== '') {
            $detalhe .= ' Código ' . $codigoErro . '.';
        }
        if ($mensagemApi !== '') {
            $detalhe .= ' ' . $mensagemApi;
        } elseif (!is_array($dados)) {
            $respostaTexto = html_entity_decode(strip_tags((string) $resposta), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $respostaTexto = trim((string) preg_replace('/\s+/u', ' ', $respostaTexto));
            $respostaTexto = (string) preg_replace('/\b\d{12,19}\b/', '[número protegido]', $respostaTexto);
            $respostaTexto = mb_substr($respostaTexto, 0, 300);
            $detalhe .= ' A resposta não contém um JSON válido';
            if ($tipoConteudo !== '') {
                $detalhe .= ' (Content-Type: ' . $tipoConteudo . ')';
            }
            $detalhe .= '.';
            if ($respostaTexto !== '') {
                $detalhe .= ' Retorno: ' . $respostaTexto;
            }
        }
        return ['sucesso' => false, 'mensagem' => $detalhe, 'http_status' => $status];
    }

    return ['sucesso' => true, 'mensagem' => $mensagemApi, 'http_status' => $status];
}

if (!$conn instanceof mysqli || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    voltarRelatorioSaldo('Requisição inválida.');
}
mysqli_set_charset($conn, 'utf8mb4');

if ((string) ($_POST['tipoacao'] ?? '') !== '1') {
    voltarRelatorioSaldo('A remoção de saldo está bloqueada.');
}

$matriculaTecnico = trim((string) ($_POST['matriculatec'] ?? ''));
$matriculaAutor = trim((string) ($sessao['matricula'] ?? $_SESSION['matricula'] ?? ''));
$placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($_POST['placa'] ?? '')) ?? '');
$justificativa = trim((string) ($_POST['justificativa'] ?? ''));
$centavos = valorSaldoEmCentavos((string) ($_POST['valor'] ?? ''));
$idSaldo = (int) ($_POST['idtbsaldo'] ?? 0);
$saldoAnterior = (float) ($_POST['saldoatual'] ?? 0);

if ($matriculaTecnico === '' || $matriculaAutor === '' || $placa === '') {
    voltarRelatorioSaldo('Dados do técnico ou veículo incompletos.');
}
if ($centavos === null) {
    voltarRelatorioSaldo('Informe um valor válido a partir de R$ 0,01.');
}
if (mb_strlen($justificativa) < 10) {
    voltarRelatorioSaldo('Informe uma justificativa com pelo menos 10 caracteres.');
}

if ($idSaldo <= 0) {
    $stmtSaldo = mysqli_prepare($conn, "SELECT idtbsaldo FROM `{$databaseName}`.`tbsaldo` WHERE matricula = ? ORDER BY data DESC, idtbsaldo DESC LIMIT 1");
    if (!$stmtSaldo || !mysqli_stmt_bind_param($stmtSaldo, 's', $matriculaTecnico) || !mysqli_stmt_execute($stmtSaldo)) {
        voltarRelatorioSaldo('Não foi possível localizar o saldo interno do técnico.');
    }
    $resultadoSaldo = mysqli_stmt_get_result($stmtSaldo);
    $idSaldo = (int) (($resultadoSaldo ? mysqli_fetch_assoc($resultadoSaldo) : [])['idtbsaldo'] ?? 0);
    mysqli_stmt_close($stmtSaldo);
}
if ($idSaldo <= 0) {
    voltarRelatorioSaldo('Não foi encontrado saldo interno para o técnico.');
}

$cartao = buscarCartaoAtivoTicketLog($conn, $databaseName, $placa);
if (!$cartao['sucesso']) {
    voltarRelatorioSaldo('Saldo não adicionado.', $cartao['mensagem']);
}
$alteracaoTicketLog = adicionarSaldoTicketLog(
    $conn,
    $databaseName,
    $cartao['numero_cartao'],
    $centavos,
    $justificativa
);
if (!$alteracaoTicketLog['sucesso']) {
    error_log('[TicketLog] Inclusão de crédito recusada: ' . $alteracaoTicketLog['mensagem']);
    voltarRelatorioSaldo('Saldo não adicionado.', $alteracaoTicketLog['mensagem']);
}

$valor = $centavos / 100;
mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "UPDATE `{$databaseName}`.`tbsaldo` SET saldo = COALESCE(saldo, 0) + ?, valoraplicado = COALESCE(valoraplicado, 0) + ?, totalextra = COALESCE(totalextra, 0) + ? WHERE idtbsaldo = ? AND matricula = ?");
    if (!$stmt || !mysqli_stmt_bind_param($stmt, 'dddis', $valor, $valor, $valor, $idSaldo, $matriculaTecnico) || !mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Falha ao atualizar o saldo interno.');
    }
    if (mysqli_stmt_affected_rows($stmt) !== 1) {
        throw new RuntimeException('Saldo interno não atualizado.');
    }
    mysqli_stmt_close($stmt);

    $perfilAutor = (int) ($sessao['perfil'] ?? $_SESSION['perfil'] ?? 0);
    $stmt = mysqli_prepare($conn, "INSERT INTO `{$databaseName}`.`tbremanejamento` (data_e_hora, matricula, matr_autor, valor, perfil_autor, tipo, valor_anterior) VALUES (NOW(), ?, ?, ?, ?, 1, ?)");
    if (!$stmt || !mysqli_stmt_bind_param($stmt, 'ssdid', $matriculaTecnico, $matriculaAutor, $valor, $perfilAutor, $saldoAnterior) || !mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Falha ao registrar o remanejamento.');
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "INSERT INTO `{$databaseName}`.`tbjustificativa` (matricula_autor, data_hora, tipo_acao, justificativa, matricula_tecnico, placa, valor) VALUES (?, NOW(), 1, ?, ?, ?, ?)");
    if (!$stmt || !mysqli_stmt_bind_param($stmt, 'ssssd', $matriculaAutor, $justificativa, $matriculaTecnico, $placa, $valor) || !mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('Falha ao registrar a justificativa.');
    }
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    error_log('[TicketLog] Saldo externo adicionado, mas houve falha no registro interno: ' . $e->getMessage());
    voltarRelatorioSaldo('Saldo adicionado na TicketLog, mas houve falha ao atualizar o controle interno. Contate o suporte.');
}

voltarRelatorioSaldo('Saldo adicionado com sucesso.');