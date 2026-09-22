<?php

declare(strict_types=1);

/**
 * Consulta o cartao ativo de uma placa na TicketLog.
 *
 * A credencial e os codigos da integracao sao carregados da tabela
 * tbintegracao_ticketlog. Consulte docs/ticketlog-configuracao.md.
 *
 * @return array{sucesso: bool, saldo: ?float, numero_cartao: string, mensagem: string}
 */
function consultarSaldoTicketLogPorPlaca(mysqli $conn, string $databaseName, string $placa): array
{
    $placa = strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($placa)) ?? '');

    if ($placa === '') {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'Placa não informada.'];
    }
    if (!function_exists('curl_init')) {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'Extensão cURL indisponível.'];
    }

    $configuracao = buscarConfiguracaoTicketLog($conn, $databaseName);
    if (!$configuracao['sucesso']) {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => $configuracao['mensagem']];
    }

    $credencial = $configuracao['basic_auth'];
    $codigoCliente = $configuracao['codigo_cliente'];
    $codigoProduto = $configuracao['codigo_produto'];

    $payload = json_encode([
        'codigoCliente' => $codigoCliente,
        'codigoProduto' => $codigoProduto,
        'placaVeiculo' => $placa,
        'situacaoCartao' => 'A',
        'ordem' => 'C',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $curl = curl_init('https://srv1.ticketlog.com.br/ticketlog-servicos/ebs/relatorioExtratoSimplificado/search');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $credencial,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $resposta = curl_exec($curl);
    $erroCurl = curl_error($curl);
    $statusHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($resposta === false || $erroCurl !== '') {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'Falha de comunicação com a TicketLog.'];
    }

    $dados = json_decode($resposta, true);
    if ($statusHttp < 200 || $statusHttp >= 300 || !is_array($dados) || ($dados['sucesso'] ?? false) !== true) {
        return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'A TicketLog recusou a consulta.'];
    }

    foreach (($dados['itens'] ?? []) as $item) {
        if (!is_array($item) || strtoupper((string) ($item['situacao'] ?? '')) !== 'A') {
            continue;
        }

        return [
            'sucesso' => true,
            'saldo' => isset($item['saldo']) ? (float) $item['saldo'] : 0.0,
            'numero_cartao' => (string) ($item['numeroCartao'] ?? ''),
            'mensagem' => '',
        ];
    }

    return ['sucesso' => false, 'saldo' => null, 'numero_cartao' => '', 'mensagem' => 'Nenhum cartão ativo encontrado para a placa.'];
}

/**
 * Insere saldo no periodo atual de um cartao TicketLog.
 *
 * Mantem a mesma operacao AR/AS/SP utilizada pelo portal legado e pelo
 * remanejamento de frota.
 *
 * @return array{sucesso: bool, mensagem: string}
 */
function inserirSaldoTicketLogPorCartao(mysqli $conn, string $databaseName, string $numeroCartao, float $valor): array
{
    $numeroCartao = trim($numeroCartao);
    if ($numeroCartao === '' || $valor <= 0) {
        return ['sucesso' => false, 'mensagem' => 'Cartão ou valor para inclusão de saldo inválido.'];
    }
    if (!function_exists('curl_init')) {
        return ['sucesso' => false, 'mensagem' => 'Extensão cURL indisponível.'];
    }

    $configuracao = buscarConfiguracaoTicketLog($conn, $databaseName);
    if (!$configuracao['sucesso']) {
        return ['sucesso' => false, 'mensagem' => $configuracao['mensagem']];
    }

    $curl = curl_init('https://srv1.ticketlog.com.br/ticketlog-servicos/ebs/usuarioCartaoLimite');
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . $configuracao['basic_auth'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'codigoCliente' => $configuracao['codigo_cliente'],
            'codigoProduto' => $configuracao['codigo_produto'],
            'tipoAlteracao' => 'AR',
            'tipoLimite' => 'AS',
            'tipoOperacao' => 'SP',
            'cartoes' => [[
                'numeroCartao' => $numeroCartao,
                'valorLimite' => $valor,
                'valorLimiteProxPeriodo' => null,
                'mensagem' => 'Controle Combustivel',
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    $resposta = curl_exec($curl);
    $erroCurl = curl_error($curl);
    $statusHttp = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($resposta === false || $erroCurl !== '') {
        return ['sucesso' => false, 'mensagem' => 'Falha de comunicação com a TicketLog.'];
    }

    $dados = json_decode((string) $resposta, true);

    if ($statusHttp < 200 || $statusHttp >= 300) {
        return ['sucesso' => false, 'mensagem' => 'A TicketLog recusou a inclusão de saldo (HTTP ' . $statusHttp . ').'];
    }

    if (!is_array($dados)) {
        return ['sucesso' => false, 'mensagem' => 'A TicketLog respondeu com conteúdo inválido para inclusão de saldo.'];
    }

    if (($dados['sucesso'] ?? true) === false) {
        $mensagemErro = '';
        foreach (['mensagem', 'message', 'erro', 'error', 'detail', 'details'] as $chave) {
            if (isset($dados[$chave]) && is_scalar($dados[$chave])) {
                $mensagemErro = trim((string) $dados[$chave]);
                break;
            }
        }

        $mensagemFinal = $mensagemErro !== ''
            ? 'A TicketLog respondeu HTTP 200, mas retornou falha de negócio: ' . $mensagemErro
            : 'A TicketLog respondeu HTTP 200, mas retornou falha de negócio.';

        return ['sucesso' => false, 'mensagem' => $mensagemFinal];
    }

    return ['sucesso' => true, 'mensagem' => ''];
}

/**
 * @return array{sucesso: bool, basic_auth: string, codigo_cliente: int, codigo_produto: int, mensagem: string}
 */
function buscarConfiguracaoTicketLog(mysqli $conn, string $databaseName): array
{
    static $cache = [];

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $databaseName)) {
        return ['sucesso' => false, 'basic_auth' => '', 'codigo_cliente' => 0, 'codigo_produto' => 0, 'mensagem' => 'Banco da configuração TicketLog inválido.'];
    }

    $cacheKey = spl_object_id($conn) . ':' . $databaseName;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $sql = "SELECT basic_auth, codigo_cliente, codigo_produto
              FROM `{$databaseName}`.`tbintegracao_ticketlog`
             WHERE ativo = 1
             ORDER BY idtbintegracao_ticketlog DESC
             LIMIT 1";
    $resultado = mysqli_query($conn, $sql);
    if (!$resultado) {
        $cache[$cacheKey] = ['sucesso' => false, 'basic_auth' => '', 'codigo_cliente' => 0, 'codigo_produto' => 0, 'mensagem' => 'Configuração TicketLog indisponível.'];
        return $cache[$cacheKey];
    }

    $linha = mysqli_fetch_assoc($resultado) ?: [];
    mysqli_free_result($resultado);

    $basicAuth = trim((string) ($linha['basic_auth'] ?? ''));
    $codigoCliente = (int) ($linha['codigo_cliente'] ?? 0);
    $codigoProduto = (int) ($linha['codigo_produto'] ?? 0);
    if ($basicAuth === '' || $codigoCliente <= 0 || $codigoProduto <= 0) {
        $cache[$cacheKey] = ['sucesso' => false, 'basic_auth' => '', 'codigo_cliente' => 0, 'codigo_produto' => 0, 'mensagem' => 'Configuração TicketLog não cadastrada.'];
        return $cache[$cacheKey];
    }

    $cache[$cacheKey] = [
        'sucesso' => true,
        'basic_auth' => $basicAuth,
        'codigo_cliente' => $codigoCliente,
        'codigo_produto' => $codigoProduto,
        'mensagem' => '',
    ];

    return $cache[$cacheKey];
}