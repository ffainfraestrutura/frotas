<?php

function carregarOpcoes(mysqli $conn, string $sql, string $valueField, string $labelField): array
{
    $options = [];
    $result = mysqli_query($conn, $sql);

    if (!$result) {
        error_log('Erro ao carregar opcoes: ' . mysqli_error($conn));
        return $options;
    }

    while ($row = mysqli_fetch_assoc($result)) {
        $options[] = [
            'value' => (string) ($row[$valueField] ?? ''),
            'label' => (string) ($row[$labelField] ?? ''),
        ];
    }

    mysqli_free_result($result);
    return $options;
}
?>