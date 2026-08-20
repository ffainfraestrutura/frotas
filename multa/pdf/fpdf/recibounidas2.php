<?php
require '../181/fpdf.php';
require('../../../control/conecta.php');
header("Content-type: text/html; charset=utf-8");
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('memory_limit', '256M'); // Aumenta para 256MB

$id = $_POST['id'] ?? '';

if (empty($id)) {
    die('ID não informado');
}

// Consulta única com JOINs para trazer todas as informações necessárias
$sql = "
    SELECT 
        t.nome,
        t.cpf,
        t.matricula,
        t.placa,
        t.autoinfra,
        t.dtcons,
        t.locadora,
        t.idmulta,

        m.valor AS valor1,
        m.valdesconto AS valdesconto1,
        m.taxaadm AS taxaadm1,
        m.juros,
        m.valtotal AS valortotal,
        m.valparcelas,
        m.datainfracao AS dataInfra,
        m.numparcelas,

        v.renavam,
        v.chassi,
        v.idlocador,
        v.unidade AS estadofilial,

        f.idtbfuncionario,
        f.rg,
        f.endereco,
        f.bairro,
        f.cidade,
        f.estado,
        f.cep,
        f.matricula AS matricula_funcionario,
		f.codfilial AS filial,

        c.numcnh AS cnh,

        fi.cnpj,
        fi.Estado AS estado_filial_bd
    FROM bdfrota.tbmovidatramite t
    LEFT JOIN bdfrota.tbmulta m ON 
        (t.idmulta = m.idtbmulta OR (t.idmulta IS NULL AND m.placa = t.placa AND m.autoinfracao = t.autoinfra))
    LEFT JOIN bdfrota.tbveiculo v ON v.placa = t.placa
    LEFT JOIN bdcorp.tbfuncionario f ON f.nome = t.nome
    LEFT JOIN bdfrota.tbcnh c ON c.matricula = f.matricula
    LEFT JOIN bdcorp.tbfilial fi ON fi.idtbfilial = f.codfilial
    WHERE t.idtbmovidatramite = '".mysqli_real_escape_string($conexao, $id)."'
    LIMIT 1;
";

$resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
$row = mysqli_fetch_assoc($resultado);

if (!$row) {
    die('Registro não encontrado');
}

// Extrair variáveis
$nome = $row['nome'];
$cpf = $row['cpf'];
$matricula = $row['matricula'];
$placa = $row['placa'];
$autoinfra = $row['autoinfra'];
$dtcons = $row['dtcons'];
$locadora = $row['locadora'];
$idmulta = $row['idmulta'];

$valor1 = $row['valor1'] ?? 0;
$valdesconto1 = $row['valdesconto1'] ?? 0;
$taxaadm1 = $row['taxaadm1'];
$juros = $row['juros'];
$valortotal = $row['valortotal'];
$valparcelas = $row['valparcelas'];
$filial = $row['filial'];
$dataInfra = $row['dataInfra'];
$numparcelas = $row['numparcelas'];

$renavam = $row['renavam'];
$chassi = $row['chassi'];
$idlocador = $row['idlocador'];
$estadofilial = $row['estadofilial'];

$idtbfuncionario = $row['idtbfuncionario'];
$rg = $row['rg'];
$endereco = $row['endereco'];
$bairro = $row['bairro'];
$cidade = $row['cidade'];
$estado = $row['estado'];
$cep = $row['cep'];
$matricula_funcionario = $row['matricula_funcionario'];

$cnh = $row['cnh'];

$cnpj = $row['cnpj'];
$estadofilial_bd = $row['estado_filial_bd'];

// Caso locadora esteja vazio, tenta usar idlocador do veículo
if (empty($locadora)) {
    $locadora = $idlocador;
}

// Caso filial esteja vazio e veículo tenha estado (unidade)
if (empty($filial)) {
    $filial = $filial; // já vem da consulta; pode ajustar conforme a regra se quiser
}

if (empty($estadofilial)) {
    $estadofilial = $estadofilial; // já vem da consulta
}

// Ajustes padrão para CNPJ e cidade conforme estado da filial
if ($estadofilial == 'SP') {
    if (empty($cnpj)) {
        $cnpj = '08.375.450/0005-02';
    }
    $cidadeass = 'SÃO PAULO/SP';

} elseif ($estadofilial == 'RJ') {
    if (empty($cnpj)) {
        $cnpj = '08.375.450/0001-70';
    }
    $cidadeass = 'RIO DE JANEIRO/RJ';

} elseif ($estadofilial == 'PR') {
    if (empty($cnpj)) {
        $cnpj = '08.375.450/0017-38';
    }
    $cidadeass = 'CURITIBA/PR';
} else {
    $cidadeass = '';
}

// Calcular taxa administrativa se estiver vazia ou zero
if ($taxaadm1 === null || $taxaadm1 == '' || $taxaadm1 == '0') {
    if (in_array($locadora, ['2', '9', '16', '17', '19'])) { // movida
        $taxaadm1 = round((0.15 * $valor1), 2);
    } elseif (in_array($locadora, ['3', '4', '14', '15'])) { // localiza
        $taxaadm1 = 25.00;
    } elseif (in_array($locadora, ['6', '8'])) {
        $taxaadm1 = round((0.2 * $valor1), 2);
    } else {
        $taxaadm1 = 0.00;
    }
}

// Se valortotal não definido, calcular
if (empty($valortotal)) {
    $valortotal = ($valor1 + $taxaadm1) - $valdesconto1;
}

// Formatar valores para exibir com vírgula decimal
function format_valor($valor) {
    $valor = number_format((float)$valor, 2, ',', '');
    if (strpos($valor, ',') === false) {
        $valor .= ',00';
    }
    return $valor;
}

$valor1 = format_valor($valor1);
$valdesconto1 = format_valor($valdesconto1);
$taxaadm1 = format_valor($taxaadm1);
$valortotal = format_valor($valortotal);

$valorparcelas = $row['valparcelas'] ?? 0;
$valorparcelas = format_valor($valorparcelas);

// Formatando data da infração
$dataInfraf = '';
if (!empty($dataInfra)) {
    $dataInfra1 = explode(" ", $dataInfra);
    $dataInfra2 = explode("-", $dataInfra1[0]);
    $dataInfraf = $dataInfra2[2] . "/" . $dataInfra2[1] . "/" . $dataInfra2[0];
    if (isset($dataInfra1[1])) {
        $dataInfraf .= " " . $dataInfra1[1];
    }
}

// Data atual para o recibo
$hoje = date('Y-m-d');
$datah = explode("-", $hoje);
$ano = $datah[0];
$mes = (int)$datah[1];
$dia = $datah[2];

// Mes por extenso
$meses = [
    1 => 'janeiro',
    2 => 'fevereiro',
    3 => 'março',
    4 => 'abril',
    5 => 'maio',
    6 => 'junho',
    7 => 'julho',
    8 => 'agosto',
    9 => 'setembro',
    10 => 'outubro',
    11 => 'novembro',
    12 => 'dezembro'
];
$mesd = $meses[$mes] ?? '';

// Utf8 para as strings que vieram do banco
$endereco = utf8_encode($endereco);
$bairro = utf8_encode($bairro);
$cidade = utf8_encode($cidade);

class PDF extends FPDF
{
    // Você pode descomentar e ajustar o header se quiser, no futuro
    /*
    function Header()
    {
        $this->Image('../../img/logo.png', 10, 6, 20);
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(215);
        $this->Ln(0.1);
        $this->Cell(22);
        $this->Cell(160, 20, 'RECIBO DE ADIANTAMENTO SALARIAL', 1, 1, 'C', true);
        $this->Ln(2);
    }
    */
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->Image('../../src/images/logo.png', 10, 6, 20);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(215);
$pdf->Ln(0.1);
$pdf->Cell(22);
$pdf->Cell(160, 20, 'RECIBO DE ADIANTAMENTO SALARIAL', 1, 1, 'C', true);
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregador: FFA INFRAESTRUTURA E SERVIÇOS LTDA\nCNPJ n.º $cnpj\n"), 1, 1);

$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregado: $nome\nCPF: $cpf\n"), 1, 1);

$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Adiantamento Salarial para pagamento de MULTA DO VEÍCULO $placa\n\nAuto de infração: $autoinfra\nValor da multa: R$ $valor1\nValor Taxa Administração Locadora: R$ $taxaadm1\n\nValor desconto: $valdesconto1\nTotal: R$ $valortotal\nQuantidade de Parcelas: $numparcelas\nValor das Parcelas: R$ $valorparcelas\n\nDeclaro, para os devidos fins, que recebi da empresa, a título de Adiantamento Salarial para pagamento de multa, a importância de R$ $valortotal, em espécie.\nEm conformidade com o disposto no artigo 462, caput, da Consolidação das Leis do Trabalho (CLT), estou ciente de que o referido valor será integralmente descontado da minha remuneração mensal, por meio de $numparcelas parcelas de R$ $valorparcelas, a serem abatidas diretamente na folha de pagamento, até a quitação total do valor antecipado..\n\n$cidadeass, $dia de $mesd de $ano. \n\n\n\n\n______________________________________________________\n     Assinatura do Empregado\n\n\n\n\n\n"), 1, 1);

$pdf->AddPage();

$pdf->SetFont('Times', 'B', 12);
$pdf->SetFillColor(255);
$pdf->Ln(10);
$pdf->Cell(10);
$pdf->Cell(0, 10, utf8_decode('Indicação de Condutor e Declaração de Responsabilidade'), 0, 1, 'C', false);
$pdf->Ln(2);

$pdf->SetFont('Times', '', 11);
$pdf->Cell(10);
$pdf->MultiCell(0, 6, utf8_decode("\nEu, $nome, portador (a) da Carteira de Habilitação (CNH) Nº $cnh, da Cédula de identidade RG: $rg, inscrito(a) no CPF /MF sob o nº $cpf, motorista / condutor de veículo locado,  neste ato autorizo a empresa Ouro Verde Locações, CNPJ XXXXXXX e  LW TECNOLOGIA, para, nos termos das Resoluções 17/98, 72/98 e 149/03, do Conselho Nacional de Trânsito, indicar meu nome, como condutor do veículo locado, acima identificado, preencher meus dados no formulário de \"Indicação do Condutor\", inclusive assinatura, e representar-me perante os órgãos que compõe o Sistema Nacional de Trânsito, quando assim for necessário pela imputação de qualquer penalidade em face de infrações cometidas durante o período de contrato, em que o veículo locado de placa $placa, renavam: $renavam e chassi $chassi estiver sob minha responsabilidade ou mesmo que não estiver mais na posse do mesmo a responsabilidade permanece em relação as infrações recebidas \"a posteriori\", mas no periodo do contrato de locação em referência."), 0, 'J');

$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0, 5, utf8_decode("\nAUTO INFRAÇÃO: $autoinfra\nDATA E HORA DA INFRAÇÃO: $dataInfraf"), 0, 'J');

$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0, 5, utf8_decode("\nPara atendimento às questões acima mencionadas, autorizo também o fornecimento de cópias de meus documentos (RG/CPF/CNH), bem como do Contrato de Locação, por mim assinado ou pela empresa onde trabalho, sempre que necessário.\n\nDeclaro conhecer todas as normas de trânsito, obrigando-me a respeitá-las e comunicar, imediatamente Ouro Verde Locações, CNPJ XXXXXXXX, qualquer circunstância anormal que envolva o veículo.\n\nDeclaro, também, para todos os fins de direito que na qualidade de Locatário/Usuário do veículo acima discriminado, a partir da assinatura do termo, assumo total responsabilidade, tanto na esfera civil, como na esfera criminal, com relação à utilização do veículo, conforme pactuado no Contrato de Locação acima referido.\n\nDeclaro, por fim, assumir eventuais penalidades (multas e pontuação) aplicadas em decorrência de quaisquer infrações cometidas quando da condução do veículo locado e durante o período em que este estiver sob minha responsabilidade."), 0, 'J');

$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0, 5, utf8_decode("\nCuritiba, _____de ___________ de_____"), 0, 'C');

$pdf->Ln(5);
$pdf->Cell(10);
$pdf->MultiCell(0, 5, utf8_decode("\n_______________________________________________________________\nAssinatura do Condutor\n(Assinatura Idêntica a da CNH)"), 0, 'C');

$pdf->Output();
?>
