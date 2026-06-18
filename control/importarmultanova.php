<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
setlocale(LC_ALL, 'pt_BR.utf8');
date_default_timezone_set('America/Sao_Paulo');

$hoje = date('Y-m-d H:i:s');
$hoje1 = str_replace([" ", "-", ":"], "", $hoje);

// Arrays de validação
$brasil = array("AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MT", "MS", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO");
$gravs = array("LEVE", "MEDIA", "GRAVE", "GRAVISSIMA");
$etapas = array("EM ABERTO", "AGUARDANDO ASSINATURA CONDUTOR", "CONCLUIDO", "MULTA INDEVIDA", "COLABORADOR DEMITIDO", "MULTA ASSINADA", "ENVIADO PARA DESCONTO");
$tramites = array('Inserir infrator', 'Imprimir Recibo DP', 'Confirmar Desconto', 'Fazer Pagamento', 'Finalizado Frota', 'DP Finalizado', 'Finalizado Financeiro', 'EM ABERTO');
$pontosarray = array(3, 4, 5, 7);

$nome_base_arquivo = "multas";
$_UP['pasta'] = '../docs/multas/';

// Criar diretório se não existir
if (!file_exists($_UP['pasta'])) {
    mkdir($_UP['pasta'], 0777, true);
    chmod($_UP['pasta'], 0777);
}

$matr_autor = $_POST['matr_autor'] ?? '';

include '../../vendor/autoload.php';
require_once "../includes/autofrota_common.php";
$autofrotaSessao = autofrotaInit();
$con = $autofrotaSessao["conn"] ?? null;
$databaseName = (string) ($autofrotaSessao["databaseName"] ?? "bdautofrotas");

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

function validarENormalizarData($dataString, &$erro, $horaObrigatoria = true) {
    if (empty($dataString) || trim($dataString) == '' || $dataString == '--') {
        $erro = "vazio";
        return false;
    }
    
    $dataString = str_replace("'", "", trim($dataString));
    
    $parts = explode(' ', $dataString);
    
    // Se hora não é obrigatória e só tem data
    if (!$horaObrigatoria && count($parts) == 1) {
        $datePart = $parts[0];
        $timePart = "00:00:00";
    } elseif (count($parts) < 2 && $horaObrigatoria) {
        $erro = "formato inválido (falta hora)";
        return false;
    } elseif (count($parts) >= 2) {
        $datePart = $parts[0];
        $timePart = $parts[1];
    } else {
        $datePart = $parts[0];
        $timePart = "00:00:00";
    }
    
    // Validar hora
    $timeComponents = explode(':', $timePart);
    if (count($timeComponents) == 2) {
        $timePart .= ':00';
    } elseif (count($timeComponents) != 3) {
        $erro = "formato de hora inválido (use HH:MM ou HH:MM:SS)";
        return false;
    }
    
    // Validar hora (0-23)
    $hora = (int)$timeComponents[0];
    $minuto = (int)$timeComponents[1];
    if ($hora < 0 || $hora > 23 || $minuto < 0 || $minuto > 59) {
        $erro = "hora inválida ($hora:$minuto)";
        return false;
    }
    
    // Validar data
    $dateComponents = explode('/', $datePart);
    if (count($dateComponents) != 3) {
        $erro = "formato de data inválido (use DD/MM/AAAA)";
        return false;
    }
    
    $dia = intval($dateComponents[0]);
    $mes = intval($dateComponents[1]);
    $ano = intval($dateComponents[2]);
    
    if ($ano < 1900 || $ano > 2100) {
        $erro = "ano inválido ($ano)";
        return false;
    }
    
    if (!checkdate($mes, $dia, $ano)) {
        $erro = "data inválida ($dia/$mes/$ano)";
        return false;
    }
    
    $dia = str_pad($dia, 2, '0', STR_PAD_LEFT);
    $mes = str_pad($mes, 2, '0', STR_PAD_LEFT);
    
    return "$ano-$mes-$dia $timePart";
}

if (isset($_FILES["arquivo"]) && $_FILES["arquivo"]["name"] != '') {
    $file_extension = strtolower(pathinfo($_FILES["arquivo"]["name"], PATHINFO_EXTENSION));
    
    if ($file_extension != 'xlsx') {
        echo "<script>
            Swal.fire({
                title: 'Erro!',
                text: 'Formato de arquivo não permitido. Use apenas .XLSX',
                icon: 'error',
                confirmButtonText: 'OK'
            }).then(() => { window.history.back(); });
        </script>";
        exit;
    }
    
    try {
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($_FILES['arquivo']['tmp_name']);
        $data = $spreadsheet->getActiveSheet()->toArray();
        
        // Validar limite de registros
        $totalRegistros = count($data) - 1; // Desconsiderando cabeçalho
        if ($totalRegistros > 1000) {
            echo "<script>
                Swal.fire({
                    title: 'Erro!',
                    text: 'O arquivo excede o limite de 1000 registros. Total: $totalRegistros registros.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => { window.history.back(); });
            </script>";
            exit;
        }
        
    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
        die("Erro ao carregar arquivo: " . $e->getMessage());
    }
    
    $respostageral = "";
    $totalLinhas = 0;
    $totalImportados = 0;
    $totalErros = 0;
    
    // Array para armazenar linhas com erro (para exportar em Excel)
    $linhasComErro = [];
    
    // Cabeçalho das colunas originais
    $colunas = ['PLACA', 'DATA/HORA CADASTRO', 'DATA/HORA INFRAÇÃO', 'AUTO INFRAÇÃO', 'AUTO INFRAÇÃO 2', 
                'AIT ORIGINÁRIA', 'ORGÃO', 'ENDEREÇO', 'MUNICÍPIO', 'UF', 'CÓDIGO MULTA', 
                'DESCRIÇÃO INFRAÇÃO', 'VALOR', 'AP CONDUTOR DATA VENCIMENTO', 'NOME CONDUTOR', 
                'PONTUAÇÃO', 'GRAVIDADE', 'ETAPA', 'TRÂMITE', 'CENTRO DE CUSTO PLANO'];
    
    foreach ($data as $rowIndex => $row) {
        // Pular cabeçalho
        if ($rowIndex == 0 && strtoupper(trim($row[0])) == 'PLACA') {
            continue;
        }
        
        // Pular linhas vazias
        if (empty(array_filter($row))) {
            continue;
        }
        
        $totalLinhas++;
        $gravar = TRUE;
        $resposta = "";
        $motivosErro = [];
        
        // Mapeamento dos campos
        $placa = isset($row[0]) ? trim(strtoupper($row[0])) : null;
        $datahoracadastro = isset($row[1]) ? trim($row[1]) : null;
        $datahorainfracao = isset($row[2]) ? trim($row[2]) : null;
        $autoinfracao = isset($row[3]) ? trim($row[3]) : null;
        $autoinfracao2 = isset($row[4]) ? trim($row[4]) : null;
        $originario = isset($row[5]) ? trim($row[5]) : null;
        $orgao = isset($row[6]) ? trim($row[6]) : null;
        $endereco = isset($row[7]) ? trim($row[7]) : null;
        $municipio = isset($row[8]) ? trim($row[8]) : null;
        $uf = isset($row[9]) ? trim(strtoupper($row[9])) : null;
        $codigom = isset($row[10]) ? trim($row[10]) : null;
        $descricaoinfra = isset($row[11]) ? trim($row[11]) : null;
        $valor_excel = isset($row[12]) ? trim($row[12]) : null;
        $datalimitecond = isset($row[13]) ? trim($row[13]) : null;
        $nomecond = isset($row[14]) ? trim($row[14]) : null;
        $pontos = isset($row[15]) ? trim($row[15]) : null;
        $gravidade_excel = isset($row[16]) ? trim(strtoupper($row[16])) : null;
        $etapa = isset($row[17]) ? trim(strtoupper($row[17])) : null;
        $tramite = isset($row[18]) ? trim($row[18]) : null;
        $ccustoplan = isset($row[19]) ? trim($row[19]) : null;
        
        // ========== VALIDAÇÕES ==========
        
        // 1. Validar PLACA
        if (empty($placa)) {
            $gravar = FALSE;
            $motivosErro[] = "Placa não informada";
        } else {
            $sql1 = "SELECT placa FROM `{$databaseName}`.tbveiculo WHERE placa = '" . mysqli_real_escape_string($con, $placa) . "'";
            $resultado1 = mysqli_query($con, $sql1);
            if (!$resultado1 || mysqli_num_rows($resultado1) <= 0) {
                $gravar = FALSE;
                $motivosErro[] = "Placa '$placa' não cadastrada no sistema";
            }
        }
        
        // 2. Validar DATA/HORA CADASTRO (com hora obrigatória)
        $erro_data_cadastro = "";
        $datahoracadastrof_temp = validarENormalizarData($datahoracadastro, $erro_data_cadastro, true);
        if ($datahoracadastrof_temp === false) {
            $gravar = FALSE;
            if ($erro_data_cadastro == "vazio") {
                $motivosErro[] = "Data/Hora Cadastro não informada";
            } else {
                $motivosErro[] = "Data/Hora Cadastro inválida: $erro_data_cadastro (valor: '$datahoracadastro')";
            }
        }
        
        // 3. Validar DATA/HORA INFRAÇÃO (com hora obrigatória - formato HH:MM)
        $erro_data_infracao = "";
        $datahorainfracaof_temp = validarENormalizarData($datahorainfracao, $erro_data_infracao, true);
        if ($datahorainfracaof_temp === false) {
            $gravar = FALSE;
            if ($erro_data_infracao == "vazio") {
                $motivosErro[] = "Data/Hora Infração não informada";
            } else {
                $motivosErro[] = "Data/Hora Infração inválida: $erro_data_infracao (valor: '$datahorainfracao')";
            }
        }
        
        // 4. Validar AUTO INFRAÇÃO (mínimo 8 dígitos)
        if (empty($autoinfracao)) {
            $gravar = FALSE;
            $motivosErro[] = "Auto de Infração não informado";
        } elseif (strlen(preg_replace('/[^0-9]/', '', $autoinfracao)) < 8) {
            $gravar = FALSE;
            $motivosErro[] = "Auto de Infração deve ter no mínimo 8 dígitos (valor: '$autoinfracao')";
        }
        
        // 5. Validar AUTO INFRAÇÃO 2 (se informado, mínimo 8 dígitos)
        if (!empty($autoinfracao2) && strlen(preg_replace('/[^0-9]/', '', $autoinfracao2)) < 8) {
            $gravar = FALSE;
            $motivosErro[] = "Auto de Infração 2 deve ter no mínimo 8 dígitos (valor: '$autoinfracao2')";
        }
        
        // 6. Validar AIT ORIGINÁRIA (se informado, mínimo 8 dígitos)
        if (!empty($originario) && strlen(preg_replace('/[^0-9]/', '', $originario)) < 8) {
            $gravar = FALSE;
            $motivosErro[] = "AIT Originária deve ter no mínimo 8 dígitos (valor: '$originario')";
        }
        
        // 7. Validar UF (2 dígitos, estado brasileiro)
        if (empty($uf)) {
            $gravar = FALSE;
            $motivosErro[] = "UF não informada";
        } elseif (!in_array($uf, $brasil, true)) {
            $gravar = FALSE;
            $motivosErro[] = "UF '$uf' inválida. Use UF com 2 dígitos (ex: SP, RJ, MG)";
        }
        
        // 8. Validar CÓDIGO MULTA (5 dígitos)
        if (empty($codigom)) {
            $gravar = FALSE;
            $motivosErro[] = "Código Multa não informado";
        } else {
            $codigom_limpo = preg_replace('/[^0-9]/', '', $codigom);
            if (strlen($codigom_limpo) != 5) {
                $gravar = FALSE;
                $motivosErro[] = "Código Multa deve ter 5 dígitos (valor: '$codigom')";
            }
        }
        
        // 9. Validar VALOR (sem formatação contábil, sem ponto de milhar)
        $valor = 0;
        if (!empty($valor_excel)) {
            // Verificar se contém pontos de milhar (formato brasileiro ou americano)
            if (preg_match('/\.\d{3}/', $valor_excel)) {
                $gravar = FALSE;
                $motivosErro[] = "VALOR não deve conter ponto de milhar. Use vírgula para decimal (valor: '$valor_excel')";
            } else {
                $valor_limpo = str_replace(['R$', ' ', '-'], '', $valor_excel);
                $valor_limpo = str_replace(',', '.', $valor_limpo);
                if (!is_numeric($valor_limpo)) {
                    $gravar = FALSE;
                    $motivosErro[] = "VALOR inválido. Use formato numérico com vírgula para decimal (valor: '$valor_excel')";
                } else {
                    $valor = (float)$valor_limpo;
                }
            }
        }
        // Se valor vazio, não é erro pois pode ser buscado do banco
        
        // 10. Validar AP CONDUTOR DATA VENCIMENTO (se informado, formato correto)
        $datalimitecondf_temp = null;
        if (!empty($datalimitecond) && $datalimitecond != '--') {
            $erro_data_limite = "";
            $datalimitecondf_temp = validarENormalizarData($datalimitecond, $erro_data_limite, true);
            if ($datalimitecondf_temp === false) {
                $gravar = FALSE;
                $motivosErro[] = "AP Condutor Data Vencimento inválida: $erro_data_limite (valor: '$datalimitecond')";
            }
        }
        
        // 11. Validar NOME CONDUTOR (se informado, deve existir no sistema)
        if (!empty($nomecond)) {
            $sql2 = "SELECT nome FROM tbfuncionario WHERE nome = '" . mysqli_real_escape_string($con, $nomecond) . "'";
            $resultado2 = mysqli_query($con, $sql2);
            if (!$resultado2 || mysqli_num_rows($resultado2) <= 0) {
                $gravar = FALSE;
                $motivosErro[] = "Nome do condutor '$nomecond' não encontrado no sistema. Verifique a grafia";
            }
        }
        
        // 12. Validar PONTUAÇÃO (3, 4, 5 ou 7)
        if (!empty($pontos)) {
            if (!is_numeric($pontos)) {
                $gravar = FALSE;
                $motivosErro[] = "Pontuação deve ser numérica (valor: '$pontos')";
            } else {
                $pontos_int = intval($pontos);
                if (!in_array($pontos_int, $pontosarray, true)) {
                    $gravar = FALSE;
                    $motivosErro[] = "Pontuação '$pontos' inválida. Valores aceitos: 3, 4, 5, 7";
                }
            }
        }
        
        // 13. Validar GRAVIDADE (LEVE, MEDIA, GRAVE, GRAVISSIMA - sem acentos)
        if (!empty($gravidade_excel) && $gravidade_excel != '-') {
            if (!in_array($gravidade_excel, $gravs, true)) {
                $gravar = FALSE;
                $motivosErro[] = "Gravidade '$gravidade_excel' inválida. Use: LEVE, MEDIA, GRAVE ou GRAVISSIMA (sem acentos)";
            }
        }
        
        // 14. Validar ETAPA
        if (empty($etapa)) {
            $gravar = FALSE;
            $motivosErro[] = "Etapa não informada";
        } elseif (!in_array($etapa, $etapas, true)) {
            $gravar = FALSE;
            $motivosErro[] = "Etapa '$etapa' inválida. Valores válidos: " . implode(', ', $etapas);
        }
        
        // 15. Validar TRÂMITE (se informado)
        if (!empty($tramite) && !in_array($tramite, $tramites, true)) {
            $gravar = FALSE;
            $motivosErro[] = "Trâmite '$tramite' inválido. Valores válidos: " . implode(', ', $tramites);
        }
        
        // 16. Validar CENTRO DE CUSTO (se informado, deve existir)
        if (!empty($ccustoplan)) {
            $sql3 = "SELECT ccusto FROM tbfuncionario WHERE ccusto = '" . mysqli_real_escape_string($con, $ccustoplan) . "' LIMIT 1";
            $resultado3 = mysqli_query($con, $sql3);
            if (!$resultado3 || mysqli_num_rows($resultado3) <= 0) {
                $motivosErro[] = "Centro de Custo '$ccustoplan' não encontrado no sistema";
                // Não é erro fatal, apenas aviso
            }
        }
        
        // 17. Verificar se há apóstrofos nos dados textuais
        $camposTexto = [$orgao, $endereco, $municipio, $descricaoinfra, $tramite];
        foreach ($camposTexto as $campo) {
            if (!empty($campo) && strpos($campo, "'") !== false) {
                $motivosErro[] = "Campos textuais não devem conter apóstrofo ('). Remova este caractere.";
                $gravar = FALSE;
                break;
            }
        }
        
        // 18. Verificar campos com ' - ' (traço espaço espaço)
        $todosCampos = [$placa, $datahoracadastro, $datahorainfracao, $autoinfracao, $autoinfracao2, $originario, $orgao, $endereco, $municipio, $uf, $codigom, $descricaoinfra, $valor_excel, $datalimitecond, $nomecond, $pontos, $gravidade_excel, $etapa, $tramite, $ccustoplan];
        foreach ($todosCampos as $campo) {
            if (!empty($campo) && trim($campo) == '-') {
                $motivosErro[] = "Campo com valor '-'. Deixe a célula vazia ao invés de usar traço";
                $gravar = FALSE;
                break;
            }
        }
        
        // 19. Verificar se já existe no sistema (duplicado)
        if ($gravar && !empty($autoinfracao)) {
            $sql_duplicado = "SELECT autoinfracao FROM `{$databaseName}`.tbmulta WHERE autoinfracao = '" . mysqli_real_escape_string($con, $autoinfracao) . "'";
            $result_duplicado = mysqli_query($con, $sql_duplicado);
            if ($result_duplicado && mysqli_num_rows($result_duplicado) > 0) {
                $gravar = FALSE;
                $motivosErro[] = "Auto de Infração já existe no sistema (duplicado não será atualizado)";
            }
        }
        
        // Se não gravou, adiciona à lista de erros
        if (!$gravar) {
            $totalErros++;
            $motivoCompleto = implode("; ", $motivosErro);
            
            $linhasComErro[] = [
                'linha' => $rowIndex + 1,
                'placa' => $placa,
                'datahoracadastro' => $datahoracadastro,
                'datahorainfracao' => $datahorainfracao,
                'autoinfracao' => $autoinfracao,
                'autoinfracao2' => $autoinfracao2,
                'originario' => $originario,
                'orgao' => $orgao,
                'endereco' => $endereco,
                'municipio' => $municipio,
                'uf' => $uf,
                'codigom' => $codigom,
                'descricaoinfra' => $descricaoinfra,
                'valor' => $valor_excel,
                'datalimitecond' => $datalimitecond,
                'nomecond' => $nomecond,
                'pontos' => $pontos,
                'gravidade' => $gravidade_excel,
                'etapa' => $etapa,
                'tramite' => $tramite,
                'ccustoplan' => $ccustoplan,
                'motivo' => $motivoCompleto
            ];
            
            $respostageral .= ">>> NAO IMPORTADO - Linha " . ($rowIndex + 1) . " - Auto: $autoinfracao - Placa: $placa\n";
            $respostageral .= "Motivos: $motivoCompleto\n\n";
        } else {
            // ========== AQUI ENTRA O CÓDIGO DE INSERÇÃO ==========
            // (mantenha seu código original de INSERT aqui)
            $totalImportados++;
        }
    }
    
    // ========== GERAR EXCEL COM LINHAS NÃO IMPORTADAS ==========
    if ($totalErros > 0) {
        $spreadsheet_erros = new Spreadsheet();
        $sheet_erros = $spreadsheet_erros->getActiveSheet();
        $sheet_erros->setTitle('Linhas Não Importadas');
        
        // Cabeçalho do relatório de erros
        $cabecalhoErros = [
            'Linha Original', 'PLACA', 'Data/Hora Cadastro', 'Data/Hora Infração', 
            'Auto Infração', 'Auto Infração 2', 'AIT Originária', 'Orgão', 
            'Endereço', 'Município', 'UF', 'Código Multa', 'Descrição Infração', 
            'Valor', 'AP Condutor Vencimento', 'Nome Condutor', 'Pontuação', 
            'Gravidade', 'Etapa', 'Trâmite', 'Centro de Custo', 'MOTIVO DA NÃO IMPORTAÇÃO'
        ];
        
        // Aplicar cabeçalho
        foreach ($cabecalhoErros as $colIndex => $titulo) {
            $coluna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
            $sheet_erros->setCellValue($coluna . '1', $titulo);
            $sheet_erros->getStyle($coluna . '1')->getFont()->setBold(true);
            $sheet_erros->getStyle($coluna . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
            $sheet_erros->getStyle($coluna . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        
        // Preencher dados das linhas com erro
        $linhaAtual = 2;
        foreach ($linhasComErro as $erro) {
            $sheet_erros->setCellValue('A' . $linhaAtual, $erro['linha']);
            $sheet_erros->setCellValue('B' . $linhaAtual, $erro['placa']);
            $sheet_erros->setCellValue('C' . $linhaAtual, $erro['datahoracadastro']);
            $sheet_erros->setCellValue('D' . $linhaAtual, $erro['datahorainfracao']);
            $sheet_erros->setCellValue('E' . $linhaAtual, $erro['autoinfracao']);
            $sheet_erros->setCellValue('F' . $linhaAtual, $erro['autoinfracao2']);
            $sheet_erros->setCellValue('G' . $linhaAtual, $erro['originario']);
            $sheet_erros->setCellValue('H' . $linhaAtual, $erro['orgao']);
            $sheet_erros->setCellValue('I' . $linhaAtual, $erro['endereco']);
            $sheet_erros->setCellValue('J' . $linhaAtual, $erro['municipio']);
            $sheet_erros->setCellValue('K' . $linhaAtual, $erro['uf']);
            $sheet_erros->setCellValue('L' . $linhaAtual, $erro['codigom']);
            $sheet_erros->setCellValue('M' . $linhaAtual, $erro['descricaoinfra']);
            $sheet_erros->setCellValue('N' . $linhaAtual, $erro['valor']);
            $sheet_erros->setCellValue('O' . $linhaAtual, $erro['datalimitecond']);
            $sheet_erros->setCellValue('P' . $linhaAtual, $erro['nomecond']);
            $sheet_erros->setCellValue('Q' . $linhaAtual, $erro['pontos']);
            $sheet_erros->setCellValue('R' . $linhaAtual, $erro['gravidade']);
            $sheet_erros->setCellValue('S' . $linhaAtual, $erro['etapa']);
            $sheet_erros->setCellValue('T' . $linhaAtual, $erro['tramite']);
            $sheet_erros->setCellValue('U' . $linhaAtual, $erro['ccustoplan']);
            $sheet_erros->setCellValue('V' . $linhaAtual, $erro['motivo']);
            
            // Destacar linha com erro (fundo vermelho claro)
            $sheet_erros->getStyle('A' . $linhaAtual . ':V' . $linhaAtual)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFCCCC');
            
            $linhaAtual++;
        }
        
        // Ajustar largura das colunas automaticamente
        foreach (range('A', 'V') as $coluna) {
            $sheet_erros->getColumnDimension($coluna)->setAutoSize(true);
        }
        
        // Salvar arquivo de erros
        $errosFileName = "erros_importacao_" . $hoje1 . ".xlsx";
        $errosFilepath = $_UP['pasta'] . $errosFileName;
        
        $writer = new Xlsx($spreadsheet_erros);
        $writer->save($errosFilepath);
        
        $downloadLink = "../docs/multas/{$errosFileName}";
    } else {
        $downloadLink = null;
    }
    
    // Mover arquivo original
    $arquivo_nome_final = "$nome_base_arquivo-$hoje1.xlsx";
    $caminho_completo = $_UP['pasta'] . $arquivo_nome_final;
    move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminho_completo);
    
    // ========== EXIBIR RESULTADO COM SWEETALERT ==========
    $resumo = "========================================\n";
    $resumo .= "     RESUMO DA IMPORTAÇÃO DE MULTAS\n";
    $resumo .= "========================================\n\n";
    $resumo .= "📊 Total de linhas processadas: $totalLinhas\n";
    $resumo .= "✅ Registros importados com sucesso: $totalImportados\n";
    $resumo .= "❌ Registros NÃO importados: $totalErros\n";
    $resumo .= "========================================\n\n";
    
    $mensagemFinal = nl2br(htmlspecialchars($resumo));
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Importação de Multas</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <style>
            .swal2-popup .swal2-html-container {
                max-height: 500px;
                overflow-y: auto;
                text-align: left;
            }
        </style>
    </head>
    <body>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var totalErros = ' . $totalErros . ';
            var totalImportados = ' . $totalImportados . ';
            var totalLinhas = ' . $totalLinhas . ';
            var downloadLink = ' . json_encode($downloadLink) . ';
            
            var htmlContent = `
                <div style="text-align: left;">
                    <p><strong>📊 Total de linhas processadas:</strong> ${totalLinhas}</p>
                    <p><strong>✅ Importados com sucesso:</strong> ${totalImportados}</p>
                    <p><strong>❌ Não importados:</strong> ${totalErros}</p>
                    <hr>
            `;
            
            if (totalErros > 0) {
                htmlContent += `
                    <p><strong>📋 Detalhes dos erros:</strong></p>
                    <p>Um arquivo Excel com detalhes das linhas não importadas foi gerado.</p>
                    <p><strong>Principais motivos de erro:</strong></p>
                    <ul>
                        <li>Placa não cadastrada no sistema</li>
                        <li>Formato de data/hora incorreto</li>
                        <li>Auto de infração com menos de 8 dígitos</li>
                        <li>UF inválida (use 2 dígitos: SP, RJ, MG...)</li>
                        <li>Código multa com formato incorreto (5 dígitos)</li>
                        <li>Valor com ponto de milhar ou formatação contábil</li>
                        <li>Gravidade com acentos ou valor inválido</li>
                        <li>Etapa não informada ou inválida</li>
                        <li>Campos com "-" (traço) ao invés de vazio</li>
                        <li>Campos textuais com apóstrofo (\')</li>
                    </ul>
                `;
                
                if (downloadLink) {
                    htmlContent += `
                        <hr>
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="${downloadLink}" download style="
                                display: inline-block;
                                background: #d33;
                                color: white;
                                padding: 12px 24px;
                                border-radius: 5px;
                                text-decoration: none;
                                font-weight: bold;
                                font-size: 16px;
                            ">
                                📥 Baixar Relatório de Erros (Excel)
                            </a>
                            <p style="font-size: 12px; margin-top: 10px; color: #666;">
                                O arquivo contém todas as linhas não importadas e o motivo ao lado
                            </p>
                        </div>
                    `;
                }
            } else {
                htmlContent += `<p style="color: green; font-weight: bold; text-align: center;">🎉 Todos os registros foram importados com sucesso!</p>`;
            }
            
            htmlContent += `</div>`;
            
            Swal.fire({
                title: totalErros > 0 ? "⚠️ Importação Concluída com Erros" : "✅ Importação Concluída com Sucesso",
                html: htmlContent,
                icon: totalErros > 0 ? "warning" : "success",
                confirmButtonText: "OK",
                confirmButtonColor: "#3085d6",
                width: "850px"
            }).then(function(result) {
                if (result.isConfirmed) {
                    window.location.href = "../multa/importarmultasnovas.php";
                }
            });
        });
    </script>
    </body>
    </html>';
    
} else {
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Atenção</title>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                title: "Atenção!",
                text: "Nenhum arquivo selecionado para importação.",
                icon: "warning",
                confirmButtonText: "OK"
            }).then(function() {
                window.location.href = "../multa/importarmultasnovas.php";
            });
        });
    </script>
    </body>
    </html>';
}
mysqli_close($con);
?>