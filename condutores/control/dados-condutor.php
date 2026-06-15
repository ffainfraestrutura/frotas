<?php

if (!function_exists('colunaValorDadosCondutor')) {
    function colunaValorDadosCondutor(array $linha, array $colunas, string $padrao = ''): string
    {
        foreach ($colunas as $coluna) {
            if (array_key_exists($coluna, $linha) && trim((string) ($linha[$coluna] ?? '')) !== '') {
                return (string) $linha[$coluna];
            }
        }

        return $padrao;
    }
}

if (!function_exists('carregarDadosCondutorAutofrota')) {
    function carregarDadosCondutorAutofrota(mysqli $conn, string $databaseName, string $matriculaCondutor): array
    {
        $retorno = [
            'condutor' => [],
            'cnh' => [],
            'usuario' => [],
            'veiculos' => [],
            'erro' => '',
            'filial' => '',
            'statusCondutor' => '',
            'placaAssociada' => '',
            'linkDocumento' => '',
        ];

        if ($matriculaCondutor === '') {
            return $retorno;
        }

        $retorno['condutor'] = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbfuncionario` WHERE matricula = ? LIMIT 1", 's', [$matriculaCondutor]);
        $retorno['cnh'] = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbcnh` WHERE matricula = ? LIMIT 1", 's', [$matriculaCondutor]);
        $retorno['usuario'] = buscarUmaLinha($conn, "SELECT * FROM `{$databaseName}`.`tbusuario` WHERE matricula = ? LIMIT 1", 's', [$matriculaCondutor]);

        $consultaVeiculos = consultaPreparada($conn, "SELECT * FROM `{$databaseName}`.`tbveiculo` WHERE matcond = ? ORDER BY placa", 's', [$matriculaCondutor]);
        $retorno['veiculos'] = $consultaVeiculos['linhas'];
        $retorno['erro'] = $consultaVeiculos['erro'];

        $retorno['filial'] = colunaValorDadosCondutor($retorno['condutor'], ['filial', 'nomefilial', 'codfilial']);
        $retorno['statusCondutor'] = colunaValorDadosCondutor($retorno['condutor'], ['status']);
        $retorno['placaAssociada'] = colunaValorDadosCondutor($retorno['veiculos'][0] ?? [], ['placa']);

        foreach (['doc2', 'doc1'] as $doc) {
            if (!empty($retorno['cnh'][$doc])) {
                $retorno['linkDocumento'] = (string) $retorno['cnh'][$doc];
                break;
            }
        }

        return $retorno;
    }
}
