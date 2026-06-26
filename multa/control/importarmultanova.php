<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
//importar os itens de excel para o BD
setlocale(LC_ALL, 'pt_BR.utf8');
date_default_timezone_set('America/Sao_Paulo');

/*selecionar hoje*/
$hoje = date('Y-m-d H:i:s');
$hoje1 = str_replace(" ", "", $hoje);
$hoje1 = str_replace("-", "", $hoje1);
$hoje1 = str_replace(":", "", $hoje1);

$brasil = array("AC", "AL", "AP", "AM", "BA", "CE", "DF", "ES", "GO", "MA", "MT", "MS", "MG", "PA", "PB", "PR", "PE", "PI", "RJ", "RN", "RS", "RO", "RR", "SC", "SP", "SE", "TO");
$gravs = array("LEVE", "MEDIA", "GRAVE", "GRAVISSIMA", "leve", "media", "grave", "gravissima", "Leve", "Media", "Grave", "Gravissima", "-");
$etapas = array("EM ABERTO", "AGUARDANDO ASSINATURA CONDUTOR", "CONCLUIDO", "MULTA INDEVIDA", "COLABORADOR DEMITIDO", "MULTA ASSINADA", "ENVIADO PARA DESCONTO");
$tramites = array('Inserir infrator', 'Imprimir Recibo DP', 'Confirmar Desconto', 'Fazer Pagamento', 'Finalizado Frota', 'DP Finalizado', 'Finalizado Financeiro', 'EM ABERTO');
$pontosarray = array(0, 3, 4, 5, 7);

$nome_base_arquivo = "multas"; // Renamed from $nome to avoid confusion

$_UP['pasta'] = '../docs/multas/';

$matr_autor = $_POST['matr_autor'];

include '../vendor/autoload.php';
include_once "../../func/log3.php";
require_once __DIR__ . "/../../includes/autofrota_common.php";
$autofrotaSessao = autofrotaInit();
$con = $autofrotaSessao["conn"] ?? null;
$databaseName = (string) ($autofrotaSessao["databaseName"] ?? "bdautofrotas");

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

// Function definition moved outside the loop
function converterParaFloat($valor)
{
    $valorLimpo = str_replace(['R$', ' '], '', (string)$valor); // Remove "R$" e espaços
    $valorLimpo = str_replace(',', '.', $valorLimpo);        // Converte vírgula decimal para ponto
    return (float) $valorLimpo;
}

// Função para validar e normalizar datas
function validarENormalizarData($dataString, &$erro) {
    if (empty($dataString) || trim($dataString) == '' || $dataString == '--') {
        $erro = "vazio";
        return false;
    }
    
    // Remove apóstrofo se houver
    $dataString = str_replace("'", "", trim($dataString));
    
    // Tenta identificar o padrão da data
    // Padrões aceitos:
    // DD/MM/AAAA HH:MM:SS
    // DD/MM/AAAA HH:MM
    // M/D/AAAA HH:MM (formato americano do Excel)
    // M/D/AAAA HH:MM:SS
    
    $parts = explode(' ', $dataString);
    if (count($parts) < 2) {
        $erro = "formato inválido (falta hora)";
        return false;
    }
    
    $datePart = $parts[0];
    $timePart = $parts[1];
    
    // Valida e corrige a parte da hora
    $timeComponents = explode(':', $timePart);
    if (count($timeComponents) == 2) {
        $timePart .= ':00'; // Adiciona segundos se não tiver
    } elseif (count($timeComponents) != 3) {
        $erro = "formato de hora inválido";
        return false;
    }
    
    // Valida e normaliza a parte da data
    $dateComponents = explode('/', $datePart);
    if (count($dateComponents) != 3) {
        $erro = "formato de data inválido (use DD/MM/AAAA)";
        return false;
    }
    
    $part1 = intval($dateComponents[0]);
    $part2 = intval($dateComponents[1]);
    $year = intval($dateComponents[2]);
    
    // Detecta se é formato americano (M/D/YYYY) ou brasileiro (D/M/YYYY)
    // Se part1 > 12, é dia (formato brasileiro)
    // Se part2 > 12, é formato americano (part1 = mês, part2 = dia)
    if ($part2 > 12) {
        // Formato americano: M/D/YYYY
        $month = $part1;
        $day = $part2;
    } else if ($part1 > 12) {
        // Formato brasileiro: D/M/YYYY
        $day = $part1;
        $month = $part2;
    } else {
        // Ambos <= 12, assume formato brasileiro como padrão
        $day = $part1;
        $month = $part2;
    }
    
    // Valida a data
    if (!checkdate($month, $day, $year)) {
        $erro = "data inválida ($day/$month/$year)";
        return false;
    }
    
    // Formata com zero à esquerda
    $day = str_pad($day, 2, '0', STR_PAD_LEFT);
    $month = str_pad($month, 2, '0', STR_PAD_LEFT);
    
    // Retorna no formato MySQL: YYYY-MM-DD HH:MM:SS
    return "$year-$month-$day $timePart";
}


if (isset($_FILES["arquivo"]) && $_FILES["arquivo"]["name"] != '') {
    $allowed_extension = array('xls', 'xlsx');
    $file_array = explode(".", $_FILES["arquivo"]["name"]);
    $file_extension = end($file_array);

    if (in_array($file_extension, $allowed_extension)) {
        try {
            $reader = IOFactory::createReader('Xlsx'); // Or 'Xls' based on actual file type if needed
            $spreadsheet = $reader->load($_FILES['arquivo']['tmp_name']);
            $arquivo_nome_final = "$nome_base_arquivo-$hoje1.$file_extension"; // Use renamed variable
            $data = $spreadsheet->getActiveSheet()->toArray();
        } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
            die("Error loading file: " . $e->getMessage());
        }


        $respostageral = "";
        $totalLinhas = 0;
        $totalImportados = 0;
        $totalErros = 0;

        foreach ($data as $rowIndex => $row) { //aqui se faz o looping
            if ($rowIndex == 0 && strtoupper(trim($row[0])) == 'PLACA') { // Skip header row more robustly
                continue;
            }
            if (empty(array_filter($row))) { // Skip entirely empty rows
                continue;
            }

            $totalLinhas++; // Conta linha processada
            $gravar = TRUE;
            $resposta = ""; // Initialize $resposta for each row

            // Use mysqli_real_escape_string for safety, or better, prepared statements
            // For simplicity in this fix, direct assignment is kept, but know the risk.
            $placa = isset($row[0]) ? trim($row[0]) : null;
            $datahoracadastro = isset($row[1]) ? trim($row[1]) : null;
            $datahorainfracao = isset($row[2]) ? trim($row[2]) : null;
            $autoinfracao = isset($row[3]) ? trim($row[3]) : null;
            $autoinfracao2 = isset($row[4]) ? trim($row[4]) : null;
            $originario = isset($row[5]) ? trim($row[5]) : null;
            $orgao = isset($row[6]) ? trim($row[6]) : null;
            $endereco = isset($row[7]) ? trim($row[7]) : null;
            $municipio = isset($row[8]) ? trim($row[8]) : null;
            $uf = isset($row[9]) ? trim(strtoupper($row[9])) : null; // Standardize to uppercase for comparison
            $codigom = isset($row[10]) ? trim($row[10]) : null;
            $descricaoinfra = isset($row[11]) ? trim($row[11]) : null;
            $valor_excel = isset($row[12]) ? trim($row[12]) : null; // Renamed to avoid clash with $valor later
            $datalimitecond = isset($row[13]) ? trim($row[13]) : null;
            $nomecond = isset($row[14]) ? trim($row[14]) : null;
            $pontos = isset($row[15]) ? trim($row[15]) : null;
            $gravidade_excel = isset($row[16]) ? trim($row[16]) : null; // Renamed
            $etapa = isset($row[17]) ? trim(strtoupper($row[17])) : null; // Standardize to uppercase
            $tramite = isset($row[18]) ? trim($row[18]) : null;
            $ccustoplan = isset($row[19]) ? trim($row[19]) : null;


            // TRAVAS //
            $sql1 = "SELECT placa FROM `{$databaseName}`.tbveiculo WHERE placa='" . mysqli_real_escape_string($con, $placa) . "'; ";
            $resultado1 = mysqli_query($con, $sql1);
            if (!$resultado1) { $resposta .= "- Erro DB Placa: " . mysqli_error($con) . ";\n"; $gravar = FALSE; }
            else if (mysqli_num_rows($resultado1) <= 0) {
                $gravar = FALSE;
                $resposta = $resposta . "- Placa não encontrada no sistema ($placa);\n";
            }

            // Validação e normalização de DATA HORA CADASTRO
            $erro_data_cadastro = "";
            $datahoracadastrof_temp = validarENormalizarData($datahoracadastro, $erro_data_cadastro);
            if ($datahoracadastrof_temp === false) {
                if ($erro_data_cadastro != "vazio") {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Data e hora de cadastro $erro_data_cadastro (valor informado: '$datahoracadastro');\n";
                } else {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Data e hora de cadastro não informada;\n";
                }
            }

            // Validação e normalização de DATA HORA INFRAÇÃO
            $erro_data_infracao = "";
            $datahorainfracaof_temp = validarENormalizarData($datahorainfracao, $erro_data_infracao);
            if ($datahorainfracaof_temp === false) {
                if ($erro_data_infracao != "vazio") {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Data e hora da infração $erro_data_infracao (valor informado: '$datahorainfracao');\n";
                } else {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Data e hora da infração não informada;\n";
                }
            }


            if (strlen((string)$autoinfracao) < 8) { // Allow 0 for autoinfracao if not mandatory
                if(!empty($autoinfracao)){ // only error if not empty and too short
                    $gravar = FALSE;
                    $resposta = $resposta . "- Auto de infração com formato incorreto (mín 8 caracteres);\n";
                } else {
                    // If autoinfracao can be empty, this is not an error. If it's mandatory, then it's an error.
                    // Assuming it's mandatory for now, but this logic might need adjustment based on business rules.
                    $gravar = FALSE;
                    $resposta = $resposta . "- Auto de infração não informado;\n";
                }
            }


            if (!empty($autoinfracao2)) {
                if (strlen($autoinfracao2) < 8) {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Auto de infração 2 com formato incorreto (mín 8 caracteres);\n";
                }
            }

            if (!empty($originario)) {
                if (strlen($originario) < 8) {
                    $gravar = FALSE;
                    $resposta = $resposta . "- AIT ORIGINÁRIA com formato incorreto (mín 8 caracteres);\n";
                }
            }

            if (!in_array($uf, $brasil, true)) {
                $gravar = FALSE;
                $resposta = $resposta . "- UF inválido ($uf);\n";
            }

            if (!empty($codigom)) {
                if (strpos($codigom, "'") !== false) {
                    $codigom = str_replace("'", "", $codigom);
                }
                if (strpos($codigom, "-") !== false) {
                    $codigom = str_replace("-", "", $codigom);
                }
                if (strlen($codigom) != 5) {
                    $gravar = FALSE;
                    $resposta = $resposta . "- CÓDIGO INFRAÇÃO com formato incorreto (deve ter 5 caracteres);\n";
                }
            } else {
                $gravar = FALSE;
                $resposta = $resposta . "- CÓDIGO INFRAÇÃO precisa ser informado;\n";
            }

            // Validate valor_excel
            $valor = 0; // Initialize valor
            if (!empty($valor_excel)) {
                if (preg_match('/[R$\s-]/', $valor_excel) && !is_numeric(str_replace(',', '.', str_replace(['R$', ' ', '-'], '', $valor_excel)))) {
                     $gravar = FALSE;
                     $resposta = $resposta . "- VALOR com formato incorreto (caracteres inválidos); $valor_excel\n";
                } else {
                    $temp_valor = str_replace(',', '.', $valor_excel);
                    if (!is_numeric($temp_valor)) {
                        $gravar = FALSE;
                        $resposta = $resposta . "- VALOR não é numérico; $valor_excel\n";
                    } else {
                        $valor = (float)$temp_valor; // Use this $valor for calculations
                    }
                }
            } else {
                // $valor remains 0, will be fetched from DB if empty
            }


            // Validação e normalização de AP CONDUTOR DATA VENCIMENTO
            $datalimitecondf_temp = null;
            if (!empty($datalimitecond)) {
                $erro_data_limite = "";
                $datalimitecondf_temp = validarENormalizarData($datalimitecond, $erro_data_limite);
                if ($datalimitecondf_temp === false && $erro_data_limite != "vazio") {
                    $gravar = FALSE;
                    $resposta = $resposta . "- AP CONDUTOR DATA VENCIMENTO $erro_data_limite (valor informado: '$datalimitecond');\n";
                }
            }

            if (!empty($nomecond)) {
                $sql2 = "SELECT nome FROM bdaniel.tbfuncionario WHERE nome = '" . mysqli_real_escape_string($con, $nomecond) . "'; ";
                $resultado2 = mysqli_query($con, $sql2);
                 if (!$resultado2) { $resposta .= "- Erro DB Nome Cond: " . mysqli_error($con) . ";\n"; $gravar = FALSE; }
                 else if (mysqli_num_rows($resultado2) <= 0) {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Nome do condutor não encontrado no sistema. Confira a grafia ($nomecond);\n";
                }
            }

            // Convert pontos to integer for comparison
            $pontos_int = null;
            if (isset($pontos) && $pontos !== '') { // Check if $pontos is set and not an empty string
                if (is_numeric($pontos)) {
                    $pontos_int = intval($pontos);
                    if (!in_array($pontos_int, $pontosarray, true)) {
                        $gravar = FALSE;
                        $resposta = $resposta . "- Valor de pontuação incorreto ($pontos);\n";
                    }
                } else {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Valor de pontuação não é numérico ($pontos);\n";
                }
            } else {
                 // Pontos can be empty, will be fetched from DB
            }
            $pontos = $pontos_int; // Assign back the integer value or null


            // Gravidade validation - convert excel input to uppercase for comparison
            $gravidade_input_upper = strtoupper($gravidade_excel);
            $valid_gravs_upper = array_map('strtoupper', $gravs);

            if (!empty($gravidade_excel)) {
                if (!in_array($gravidade_input_upper, $valid_gravs_upper, true) && $gravidade_excel !== '-') {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Valor de gravidade incorreto ($gravidade_excel). Lembre-se de enviar esses valores sem acentos;\n";
                }
            }
            // $gravidade will be determined later if empty or '-'

            if (!empty($etapa)) {
                if (!in_array($etapa, $etapas, true)) {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Etapa inválida ($etapa);\n";
                }
            } else {
                $gravar = FALSE;
                $resposta = $resposta . "- Etapa não informada;\n";
            }


            if (!empty($tramite)) {
                if (!in_array($tramite, $tramites, true)) {
                    $gravar = FALSE;
                    $resposta = $resposta . "- Trâmite inválido ($tramite);\n";
                }
            }
            // Tramite can be empty if $etapa implies it.

            if (!empty($ccustoplan)) { // Corrected variable from $ccusto to $ccustoplan
                $sql3 = "SELECT ccusto FROM bdaniel.tbfuncionario WHERE ccusto = '" . mysqli_real_escape_string($con, $ccustoplan) . "';";
                $resultado3 = mysqli_query($con, $sql3);
                if (!$resultado3) { $resposta .= "- Erro DB CCusto: " . mysqli_error($con) . ";\n"; $gravar = FALSE; }
                else if (mysqli_num_rows($resultado3) <= 0) {
                    // This might not be a fatal error if ccusto can come from vehicle or conductor later
                    // $gravar = FALSE;
                    // $resposta = $resposta . "- Centro de custo do plano não encontrado ($ccustoplan). Confira a grafia;\n";
                    // Log it or decide if it's a showstopper. For now, let it pass as ccusto can be derived.
                }
            }


            if (!$gravar) {
                $totalErros++;
                $respostainicio = ">>> NAO IMPORTADO - Auto de infracao $autoinfracao (Placa: $placa)\nMotivos:\n";
                $respostageral = $respostageral . $respostainicio . $resposta . "\n";
            } else {
                //formatando orgão
                if (!empty($orgao)) {
                    if (strpos($orgao, "'") !== false) {
                        $orgao = str_replace("'", "", $orgao);
                    }
                }

                //formatando data de cadastro - usa a data já normalizada
                $datahoracadastrof = $datahoracadastrof_temp ? $datahoracadastrof_temp : '0000-00-00 00:00:00';

                //formatando data de infração - usa a data já normalizada
                $datahorainfracaof = $datahorainfracaof_temp ? $datahorainfracaof_temp : '0000-00-00 00:00:00';
                
                // data limite condutor - usa a data já normalizada ou calcula +30 dias
                if ($datalimitecondf_temp) {
                    $datalimitecondf = $datalimitecondf_temp;
                } else {
                    $datalimitecondf = date("Y-m-d H:i:s", strtotime($datahorainfracaof . " +30 days"));
                }

                $vencimentof = date("Y-m-d H:i:s", strtotime($datahorainfracaof . " +20 days"));
                $datalimiteloc = date("Y-m-d H:i:s", strtotime($datahorainfracaof . " +25 days"));

                //tratando etapas e tramites
                $tdp = ''; $tfin = ''; $tsup = ''; $status = 0; // Default status
                // Ensure $etapa is uppercase as per previous trim/strtoupper
                switch ($etapa) {
                    case 'EM ABERTO':
                    case 'AGUARDANDO ASSINATURA CONDUTOR':
                    case 'MULTA ASSINADA':
                        $status = 1;
                        break;
                    case 'CONCLUIDO':
                    case 'MULTA INDEVIDA':
                    case 'COLABORADOR DEMITIDO':
                        $tdp = 'DP Finalizado';
                        $tfin = 'Finalizado Financeiro';
                        $status = 5;
                        break;
                    case 'ENVIADO PARA DESCONTO':
                        $tdp = 'Confirmar Desconto';
                        $tfin = 'Fazer Pagamento';
                        $status = 2;
                        break;
                }
                 // Default $tramite if not provided from Excel but implied by $etapa
                if (empty($tramite) && $etapa === 'EM ABERTO') {
                    $tramite = 'EM ABERTO'; // Or 'Inserir Infrator' etc. based on flow
                }


                $idfunc = null; $nomec = null; $codfilial = null; $codempresa = null; $cpf = null; $ccustocond = null; $matriculac = null;

                if (!empty($nomecond)) {
                    $sqla = "SELECT idtbfuncionario, nome, codfilial, codempresa, cpf, ccusto, matricula
                             FROM bdaniel.tbfuncionario
                             WHERE nome='" . mysqli_real_escape_string($con, $nomecond) . "'
                             ORDER BY CASE WHEN dtdemissao = '0000-00-00' THEN 1 ELSE 2 END, dtdemissao DESC
                             LIMIT 1;";
                    $resultadoa = mysqli_query($con, $sqla);
                    if ($resultadoa && $rowa = mysqli_fetch_assoc($resultadoa)) {
                        $idfunc = $rowa['idtbfuncionario'];
                        $nomec = $rowa['nome'];
                        $codfilial = $rowa['codfilial'];
                        $codempresa = $rowa['codempresa'];
                        $cpf = $rowa['cpf'];
                        $ccustocond = $rowa['ccusto'];
                        $matriculac = $rowa['matricula'];
                    }
                } else {
                    $sql_cond_placa = "SELECT C.nome, C.matricula, F.idtbfuncionario, F.codfilial, F.codempresa, F.cpf, F.ccusto
                                       FROM `{$databaseName}`.tbcondutor C
                                       LEFT JOIN bdaniel.tbfuncionario F ON C.matricula = F.matricula  -- Assuming matricula links them
                                       WHERE C.placaassoc='" . mysqli_real_escape_string($con, $placa) . "'
                                         AND C.dataassoc <= '" . mysqli_real_escape_string($con, $datahorainfracaof) . "'
                                         AND (C.datadissoc >= '" . mysqli_real_escape_string($con, $datahorainfracaof) . "' OR C.datadissoc='0000-00-00 00:00:00')
                                       ORDER BY F.dtdemissao DESC, F.idtbfuncionario DESC -- Prefer active, then most recent
                                       LIMIT 1;";
                    $result_cond_placa = mysqli_query($con, $sql_cond_placa);
                    if ($result_cond_placa && $row_cond = mysqli_fetch_assoc($result_cond_placa)) {
                        $nomec = $row_cond['nome'];
                        $matriculac = $row_cond['matricula'];
                        $idfunc = $row_cond['idtbfuncionario'];
                        $codfilial = $row_cond['codfilial'];
                        $codempresa = $row_cond['codempresa'];
                        $cpf = $row_cond['cpf'];
                        $ccustocond = $row_cond['ccusto'];
                    }
                }

                $filial = null;
                if ($codempresa && $codfilial) {
                    $sql3 = "SELECT nome FROM bdaniel.tbfilial WHERE codempresa='" . mysqli_real_escape_string($con, $codempresa) . "' AND codfilial='" . mysqli_real_escape_string($con, $codfilial) . "'; ";
                    $resultado3 = mysqli_query($con, $sql3);
                    if ($resultado3 && $row3 = mysqli_fetch_assoc($resultado3)) {
                        $filial = $row3['nome'];
                    }
                }

                if (empty($descricaoinfra) && !empty($codigom)) {
                    $sql4 = "SELECT descricao FROM bdfrota.tbacodmulta where codigomulta='" . mysqli_real_escape_string($con, $codigom) . "' ";
                    $resultado4 = mysqli_query($con, $sql4);
                    if ($resultado4 && $row4 = mysqli_fetch_assoc($resultado4)) {
                        $descricaoinfra = $row4['descricao'];
                    }
                }

                if (empty($pontos) && $pontos !== 0 && !empty($codigom)) { // Check if points is not 0 explicitly
                    $sql4 = "SELECT pontos FROM bdfrota.tbacodmulta where codigomulta='" . mysqli_real_escape_string($con, $codigom) . "' ";
                    $resultado4 = mysqli_query($con, $sql4);
                    if ($resultado4 && $row4 = mysqli_fetch_assoc($resultado4)) {
                        $pontos = (int)$row4['pontos'];
                    }
                }

                $gravidade = $gravidade_excel; // Use the one from Excel first
                if ( (empty($gravidade) || $gravidade == '-') && !empty($codigom)) {
                    $sql4 = "SELECT gravidade FROM bdfrota.tbacodmulta where codigomulta='" . mysqli_real_escape_string($con, $codigom) . "' ";
                    $resultado4 = mysqli_query($con, $sql4);
                    if ($resultado4 && $row4 = mysqli_fetch_assoc($resultado4)) {
                        $gravidade = $row4['gravidade'];
                    }
                }

                if (empty($valor) && !empty($codigom)) { // $valor was converted to float earlier
                    $sql5 = "SELECT valor FROM bdfrota.tbacodmulta where codigomulta='" . mysqli_real_escape_string($con, $codigom) . "' ";
                    $resultado5 = mysqli_query($con, $sql5);
                    if ($resultado5 && $row5 = mysqli_fetch_assoc($resultado5)) {
                        $valor = (float)str_replace(",", ".", $row5['valor']);
                    }
                }
                // $valor is already float here

                if (strpos((string)$autoinfracao, "'") !== false) {
                    $autoinfracao = substr($autoinfracao, 1); // This might be problematic if ' is not always the first char
                                                              // Consider $autoinfracao = str_replace("'", "", $autoinfracao);
                }

                $idlocador = null; $ccustoveic = null;
                $sql5_veic = "SELECT idlocador, ccusto FROM `{$databaseName}`.tbveiculo WHERE placa='" . mysqli_real_escape_string($con, $placa) . "' ";
                $resultado5_veic = mysqli_query($con, $sql5_veic);
                if ($resultado5_veic && $row5_veic = mysqli_fetch_assoc($resultado5_veic)) {
                    $idlocador = $row5_veic['idlocador'];
                    $ccustoveic = $row5_veic['ccusto'];
                }

                $ccustofinal = null;
                if (!empty($ccustoplan)) {
                    $ccustofinal = $ccustoplan;
                } elseif (!empty($ccustoveic)) {
                    $ccustofinal = $ccustoveic;
                } elseif (!empty($ccustocond)) {
                    $ccustofinal = $ccustocond;
                }

                $taxaadm = 0.0;
                if ($idlocador) {
                    $sql6 = "SELECT taxaadm FROM `{$databaseName}`.tbfornecedor WHERE idtbfornecedor='" . mysqli_real_escape_string($con, $idlocador) . "' ";
                    $resultado6 = mysqli_query($con, $sql6);
                    if ($resultado6 && $row6 = mysqli_fetch_assoc($resultado6)) {
                        if (!empty($row6['taxaadm'])) {
                           $taxaadm = (float)str_replace(",", ".", $row6['taxaadm']);
                        }
                    }
                }

                if ($taxaadm == 0.0 && $idlocador) { // If DB taxaadm is empty or 0, apply rules
                    if (in_array($idlocador, ['2', '9', '16', '17', '19'])) { //movida
                        $taxaadm = round((0.15 * $valor), 2);
                    } elseif (in_array($idlocador, ['3', '4', '14', '15'])) { //localiza
                        $taxaadm = 25.00;
                    } elseif (in_array($idlocador, ['6', '8'])) {
                        $taxaadm = round((0.20 * $valor), 2);
                    }
                }
                // $taxaadm is float here

                $valtotal = ($valor + $taxaadm);
                // $valtotal is float here

                $valorMulta = 0.0;
                if ($valtotal > 0) {
                    $valorMulta = $valtotal; // Already float
                } else {
                    $valorMulta = $valor; // Already float
                }

                $numparcelas = $valorMulta > 0 ? ceil($valorMulta / 200) : 1;
                if ($numparcelas == 0) $numparcelas = 1; // Avoid division by zero
                $valparcelas = $valorMulta > 0 ? ceil(($valorMulta / $numparcelas) * 100) / 100 : 0.0;

                $sql11 = "SELECT autoinfracao FROM `{$databaseName}`.tbmulta WHERE autoinfracao = '" . mysqli_real_escape_string($con, $autoinfracao) . "' ";
                $resultado11 = mysqli_query($con, $sql11);
                $linhas = 0;
                if($resultado11) $linhas = mysqli_num_rows($resultado11);

                mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
                try {
                    if ($linhas <= 0) {
                        $sql7 = "INSERT INTO `{$databaseName}`.tbmulta(placa, filial, ccusto, fornecedor, autoinfracao, datainfracao, datalimiteloc, vencimento, codigom, pontos, gravidade, valor, taxaadm, valtotal, orgao, endereco, municipio, matriculac, nomec, datalimitecond, descricaoinfra, ufinfra, originario, datahoracadastro, autoinfracao2, etapa, numparcelas, valparcelas)
                                 VALUES (
                                     '" . mysqli_real_escape_string($con, $placa) . "',
                                     '" . mysqli_real_escape_string($con, (string)$filial) . "',
                                     '" . mysqli_real_escape_string($con, (string)$ccustofinal) . "',
                                     '" . mysqli_real_escape_string($con, (string)$idlocador) . "',
                                     '" . mysqli_real_escape_string($con, $autoinfracao) . "',
                                     '" . mysqli_real_escape_string($con, $datahorainfracaof) . "',
                                     '" . mysqli_real_escape_string($con, $datalimiteloc) . "',
                                     '" . mysqli_real_escape_string($con, $vencimentof) . "',
                                     '" . mysqli_real_escape_string($con, (string)$codigom) . "',
                                     " . ($pontos !== null ? (int)$pontos : 'NULL') . ",
                                     '" . mysqli_real_escape_string($con, (string)$gravidade) . "',
                                     " . (float)$valor . ",
                                     " . (float)$taxaadm . ",
                                     " . (float)$valtotal . ",
                                     '" . mysqli_real_escape_string($con, (string)$orgao) . "',
                                     '" . mysqli_real_escape_string($con, (string)$endereco) . "',
                                     '" . mysqli_real_escape_string($con, (string)$municipio) . "',
                                     '" . mysqli_real_escape_string($con, (string)$matriculac) . "',
                                     '" . mysqli_real_escape_string($con, (string)$nomec) . "',
                                     '" . mysqli_real_escape_string($con, $datalimitecondf) . "',
                                     '" . mysqli_real_escape_string($con, (string)$descricaoinfra) . "',
                                     '" . mysqli_real_escape_string($con, (string)$uf) . "',
                                     '" . mysqli_real_escape_string($con, (string)$originario) . "',
                                     '" . mysqli_real_escape_string($con, $datahoracadastrof) . "',
                                     '" . mysqli_real_escape_string($con, (string)$autoinfracao2) . "',
                                     '" . mysqli_real_escape_string($con, (string)$etapa) . "',
                                     " . (int)$numparcelas . ",
                                     " . (float)$valparcelas . "
                                 );";
                        $resultado7 = mysqli_query($con, $sql7);

                        if ($resultado7) {
                            $idtbmulta = mysqli_insert_id($con); // Get last inserted ID

                            $sql10 = "SELECT autoinfra FROM `{$databaseName}`.tbmovidatramite WHERE idmulta = '" . $idtbmulta . "' OR autoinfra = '".mysqli_real_escape_string($con, $autoinfracao)."';"; // Check by idmulta too
                            $resultado10 = mysqli_query($con, $sql10);
                            $linhas10 = 0;
                            if ($resultado10) $linhas10 = mysqli_num_rows($resultado10);

                            if ($linhas10 <= 0) {
                                $sql9 = "INSERT INTO `{$databaseName}`.tbmovidatramite(placa, autoinfra, pontuacao, gravidade, apCondDV, dtinfra, dtvenc, valor, nome, matricula, cpf, dtcons, idmulta, tramite, tdp, tfin, tsup, status)
                                         VALUES (
                                             '" . mysqli_real_escape_string($con, $placa) . "',
                                             '" . mysqli_real_escape_string($con, $autoinfracao) . "',
                                             " . ($pontos !== null ? (int)$pontos : 'NULL') . ",
                                             '" . mysqli_real_escape_string($con, (string)$gravidade) . "',
                                             '" . mysqli_real_escape_string($con, $datalimitecondf) . "',
                                             '" . mysqli_real_escape_string($con, $datahorainfracaof) . "',
                                             '" . mysqli_real_escape_string($con, $vencimentof) . "',
                                             " . (float)$valtotal . ",
                                             '" . mysqli_real_escape_string($con, (string)$nomec) . "',
                                             '" . mysqli_real_escape_string($con, (string)$matriculac) . "',
                                             '" . mysqli_real_escape_string($con, (string)$cpf) . "',
                                             '" . mysqli_real_escape_string($con, $hoje) . "',
                                             " . (int)$idtbmulta . ",
                                             '" . mysqli_real_escape_string($con, (string)$tramite) . "',
                                             '" . mysqli_real_escape_string($con, (string)$tdp) . "',
                                             '" . mysqli_real_escape_string($con, (string)$tfin) . "',
                                             '" . mysqli_real_escape_string($con, (string)$tsup) . "',
                                             " . (int)$status . "
                                         );";
                                $resultado9 = mysqli_query($con, $sql9);
                                if(!$resultado9){
                                     error_log("Erro ao inserir em tbmovidatramite: " . mysqli_error($con) . " SQL: " . $sql9);
                                     $respostageral .= "Erro ao gravar trâmite para $autoinfracao: ".mysqli_error($con)."\n";
                                } else {
                                     $totalImportados++; // Incrementa contador de sucesso
                                }
                            } else {
                                $totalImportados++; // Incrementa mesmo sem trâmite (trâmite já existe)
                            }
                        } else {
                             error_log("Erro ao inserir em tbmulta: " . mysqli_error($con) . " SQL: " . $sql7);
                             $respostageral .= "Erro ao gravar multa $autoinfracao: ".mysqli_error($con)."\n";
                             $totalErros++;
                        }
                    } else {
                         $respostageral .= ">>> NAO IMPORTADO - Auto de infracao $autoinfracao (Placa: $placa) ja existe no sistema e nao sera atualizado.\n";
                         $totalErros++;
                    }
                } catch (mysqli_sql_exception $e) {
                    mysqli_report(MYSQLI_REPORT_OFF); // Turn off reporting after catch
                    error_log("Erro no banco de dados ao processar multa $autoinfracao: " . $e->getMessage() . " \nSQL causing error: " . (isset($sql7) ? $sql7 : (isset($sql9) ? $sql9 : 'N/A')));
                    $respostageral .= "Ocorreu um erro de banco de dados ao registrar a multa $autoinfracao. Por favor, verifique os logs. Detalhe: ".$e->getMessage()."\n";
                    $totalErros++;
                }
                mysqli_report(MYSQLI_REPORT_OFF); // Ensure it's off outside try-catch too
            }
        } // end foreach

        // Log outside the loop, once per file import
        if (class_exists('Log')) { // Check if Log class is defined
            $log = new Log($con);
            $acao = 'Importou/atualizou multas do arquivo: ' . basename($_FILES["arquivo"]["name"]);
            $tipo = 'cadastro';
            $logresult = $log->enviarlognovo($hoje, $acao, '', $matr_autor, $tipo, ''); // Placa can be empty for batch
        } else {
            error_log("Classe Log não encontrada.");
        }

        // Monta o resumo da importação
        $resumo = "========================================\n";
        $resumo .= "     RESUMO DA IMPORTACAO DE MULTAS\n";
        $resumo .= "========================================\n\n";
        $resumo .= "Total de linhas processadas: $totalLinhas\n";
        $resumo .= "Registros importados com sucesso: $totalImportados\n";
        $resumo .= "Registros NAO importados (erros/duplicados): $totalErros\n";
        $resumo .= "========================================\n\n";

        // Monta a mensagem final completa
        $mensagemFinal = $resumo;
        
        if (!empty($respostageral)) {
            $mensagemFinal .= "DETALHES DOS ERROS:\n" . $respostageral . "\n";
        } else {
            $mensagemFinal .= "Todos os registros foram importados com sucesso!\n\n";
        }

        // Tenta mover o arquivo e adiciona o resultado à mensagem
        if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $_UP['pasta'] . $arquivo_nome_final)) {
            $mensagemFinal .= "Arquivo Excel salvo com sucesso como: " . $arquivo_nome_final;
            // Usa json_encode para garantir que caracteres especiais sejam escapados corretamente
            echo "<script language='javascript' type='text/javascript'>alert(" . json_encode($mensagemFinal, JSON_HEX_APOS | JSON_HEX_QUOT) . "); window.location.href = '../importarmultasnovas.php';</script>";
        } else {
            $upload_error = error_get_last();
            $mensagemFinal .= "ATENCAO: Nao foi possivel salvar o arquivo no servidor.";
            if ($upload_error) {
                $mensagemFinal .= "\nDetalhe: " . $upload_error['message'];
            }
            echo "<script language='javascript' type='text/javascript'>alert(" . json_encode($mensagemFinal, JSON_HEX_APOS | JSON_HEX_QUOT) . "); window.location.href = '../importarmultasnovas.php';</script>";
        }

    } else { // file extension not allowed
        echo "<script language='javascript' type='text/javascript'>alert('Extensão de arquivo não permitida. Use XLS ou XLSX.'); window.history.back();</script>";
    }
} else { // No file uploaded
    echo "<script language='javascript' type='text/javascript'>alert('Nenhum arquivo selecionado para importação.'); window.history.back();</script>";
}
mysqli_close($con); // Close connection
?>