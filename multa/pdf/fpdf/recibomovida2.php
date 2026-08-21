<?php
require '../181/fpdf.php';
require('../../../control/conecta.php');
header("Content-type: text/html; charset=utf-8");
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('memory_limit', '256M'); // Aumenta para 256MB



/*$link = "$_SERVER[REQUEST_URI]";
$idaux = explode("=", $link);
$id = $idaux[1];*/

$id = $_GET['id'];
//echo $id;
//$id='2759';
$sql = "SELECT * FROM tbmovidatramite where idtbmovidatramite = '$id';";
$resultado = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

$nome = $row['nome'];
$cpf = $row['cpf'];
$matricula = $row['matricula'];
$placa = $row['placa'];
$autoinfra = $row['autoinfra'];
//$valor = $row['valor'];
$dtcons = $row['dtcons'];

$locadora = $row['locadora'];
$idmulta = $row['idmulta'];

/*$valordescaux = $row['valor'] * 8 / 10;
$valordesc = sprintf('%0.2f', round($valordescaux, 2));
$valortotal2 = $valordesc+80;
$valortotal = sprintf('%0.2f', round($valortotal2, 2));*/

if ($idmulta != '') {
	$sql1 = "SELECT * FROM tbmulta where idtbmulta= '$idmulta';";
} else {
	$sql1 = "SELECT * FROM tbmulta where placa= '$placa' AND autoinfracao = '$autoinfra';";
}
//print $sql1;
$resultado1 = mysqli_query($conn, $sql1) or die(mysqli_error($conn));
$row1 = mysqli_fetch_array($resultado1, MYSQLI_BOTH);
$valor1 = $row1['valor'];
$valdesconto1 = $row1['valdesconto'];
$taxaadm1 = $row1['taxaadm'];
$juros = $row1['juros'];
$valortotal = $row1['valtotal'];
$valparcelas = $row1['valparcelas'];
$valparcelas = $row1['valparcelas'];
$filial = $row1['filial'];
$dataInfra = $row1['datainfracao'];
$descricao = utf8_encode($row1['descricaoinfra']);
$enderecoinfra = utf8_encode($row1['endereco']);
$valorparcelas = $row1['valparcelas'];
$numparcelas = $row1['numparcelas'];

$dataInfra1 = explode(" ", $dataInfra);
$dataInfra2 = explode("-", $dataInfra1[0]);
$dataInfraf = $dataInfra2[2] . "/" . $dataInfra2[1] . "/" . $dataInfra2[0] . " " . $dataInfra1[1];

if ($locadora == '') {
	$sql2 = "SELECT idlocador FROM tbveiculo WHERE placa='$placa';";
	$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
	$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
	$locadora = $row2['idlocador'];
}

$sql2 = "SELECT 
    fi.CNPJ, fi.estado
FROM
    bdcorp.tbfuncionario f
        JOIN
    bdcorp.tbfilial fi ON fi.estado = f.uf_trabalho
WHERE
    f.matricula = '$matricula'";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$cnpj = $row2['CNPJ'];
$estadofilial = $row2['Estado'];

if ($filial == '') {
	$sql3 = "SELECT unidade FROM tbveiculo WHERE placa='$placa';";
	$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
	$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
	$estadofilial = $row3['unidade'];
}


if ($estadofilial == 'SP') {
	if (empty($cnpj)) {
		$cnpj = '01.307.399/0001-10';
	}

	$cidadeass = 'SÃO PAULO/SP';

} elseif ($estadofilial == 'RJ') {
	if (empty($cnpj)) {
		$cnpj = '01.307.399/0001-10';
	}

	$cidadeass = 'RIO DE JANEIRO/RJ';

} elseif ($estadofilial == 'PR') {
	if (empty($cnpj)) {
		$cnpj = '01.307.399/0001-10';
	}

	$cidadeass = 'CURITIBA/PR';
}



if ($taxaadm1 == '' || $taxaadm1 == '0') {
	if ($locadora == '2' || $locadora == '9' || $locadora == '16' || $locadora == '17' || $locadora == '19') {//movida
		$taxaadm1 = round((0.15 * $valor1), 2);
	} elseif ($locadora == '3' || $locadora == '4' || $locadora == '14' || $locadora == '15') {//localiza
		$taxaadm1 = '25.00';
	} elseif ($locadora == '6' || $locadora == '8') {
		$taxaadm1 = round((0.2 * $valor1), 2);

	}
}

//if($valortotal == ''){
$valortotal = ($valor1 + $taxaadm1) - $valdesconto1;
//}

if ($taxaadm1 == '') {
	$taxaadm1 = '0.00';
}

//tratando valor
$valor1 = str_replace('.', ',', $valor1);
$valortotal = str_replace('.', ',', $valortotal);
$valdesconto1 = str_replace('.', ',', $valdesconto1);
$valorparcelas = str_replace('.', ',', $valorparcelas);

$taxaadm1 = str_replace('.', ',', $taxaadm1);

$preco = explode(',', $valortotal);
$centavos = $preco[1];
$real = $preco[0];

if (strlen($centavos) == 1) {
	$valortotal = $valortotal . "0";
}

if (strpos($taxaadm1, ",") === false) {
	$taxaadm1 = $taxaadm1 . ",00";
}

if (strpos($valortotal, ",") === false) {
	$valortotal = $valortotal . ",00";
}

if (strpos($valorparcelas, ",") === false) {
	$valorparcelas = $valorparcelas . ",00";
}

$hoje = date('Y-m-d');
$datah = explode("-", $hoje);
$ano = $datah[0];
$mes = $datah[1];

switch ($mes) {
	case 1:
		$mesd = 'janeiro';
		break;

	case 2:
		$mesd = 'fevereiro';
		break;

	case 3:
		$mesd = 'março';
		break;

	case 4:
		$mesd = 'abril';
		break;

	case 5:
		$mesd = 'maio';
		break;

	case 6:
		$mesd = 'junho';
		break;

	case 7:
		$mesd = 'julho';
		break;

	case 8:
		$mesd = 'agosto';
		break;

	case 9:
		$mesd = 'setembro';
		break;

	case 10:
		$mesd = 'outubro';
		break;

	case 11:
		$mesd = 'novembro';
		break;

	case 12:
		$mesd = 'dezembro';
		break;

	default:
		$mesd = '';
}

$dia = $datah[2];

$sql2a = "SELECT MAX(idtbfuncionario) AS idtbfuncionario FROM bdcorp.tbfuncionario WHERE nome = '$nome'; ";
$resultado2a = mysqli_query($conn, $sql2a) or die(mysqli_error($conn));
$row2a = mysqli_fetch_array($resultado2a, MYSQLI_BOTH);
$idtbfuncionario = $row2a['idtbfuncionario'];

//$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE nome = '$nome'; ";
$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE matricula = '$matricula'; ";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$rg = $row2['rg'];
$cpf = $row2['cpf'];
$endereco = utf8_encode($row2['endereco']);
$bairro = utf8_encode($row2['bairro']);
$cidade = utf8_encode($row2['cidade']);
$estado = $row2['estado'];
$cep = $row2['cep'];
// $matricula = $row2['matricula'];

$sql3 = "SELECT renavam, chassi FROM tbveiculo WHERE placa='$placa'; ";
$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
$chassi = $row3['chassi'];
$renavam = $row3['renavam'];

$sql4 = "SELECT numcnh FROM tbcnh WHERE matricula='$matricula'; ";
$resultado4 = mysqli_query($conn, $sql4) or die(mysqli_error($conn));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
$cnh = $row4['numcnh'];


class PDF extends FPDF
{

}

// Instanciation of inherited class

$pdf = new PDF();

$pdf->AliasNbPages();
$pdf->AddPage();

$caminho = '../../../src/logo/logo_hallen.png';

if (!file_exists($caminho)) {
    die("Imagem não encontrada: " . realpath(dirname($caminho)));
}

$pdf->Image($caminho, 10, 6, 20);// Arial bold 15
$pdf->SetFont('Arial', 'B', 12);
// Cor de fundo
$pdf->SetFillColor(215);
// Line break
$pdf->Ln(0.1);
// Move to the right
$pdf->Cell(22);
// Title
$pdf->Cell(160, 20, 'RECIBO DE ADIANTAMENTO SALARIAL', 1, 1, 'C', 'true');
// Line break
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregador: Hallen Instalacoes de Equipamentos de Telecomunicacoes LTDA\nCNPJ n.º $cnpj\n"), 1, 1);

$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregado: $nome\nCPF: $cpf\n"), 1, 1);
$pdf->Ln(2);
$pdf->Cell(5);//R$ 15,62
$pdf->MultiCell(177, 5, utf8_decode("Adiantamento Salarial para pagamento de MULTA DO VEÍCULO $placa\n\nAuto de infração: $autoinfra\nValor da multa: R$ $valor1\nValor Taxa Administração Locadora: R$ $taxaadm1\n\nValor desconto: $valdesconto1\nTotal: R$ $valortotal\nQuantidade de Parcelas: $numparcelas\nValor das Parcelas: R$ $valorparcelas\n\nDeclaro, para os devidos fins, que recebi da empresa, a título de Adiantamento Salarial para pagamento de multa, a importância de R$ $valortotal, em espécie.\nEm conformidade com o disposto no artigo 462, caput, da Consolidação das Leis do Trabalho (CLT), estou ciente de que o referido valor será integralmente descontado da minha remuneração mensal, por meio de $numparcelas parcelas de R$ $valorparcelas, a serem abatidas diretamente na folha de pagamento, até a quitação total do valor antecipado..\n\n$cidadeass, $dia de $mesd de $ano. \n\n\n\n\n______________________________________________________\n     Assinatura do Empregado\n\n\n\n\n\n"), 1, 1);

//$pdf->Image('../../src/images/logo_hallen.png',10,6,20);

// Instanciation of inherited class
/*class PDF extends FPDF
{
// Page header
function Header()
	{
		// Logo
		//$this->Image('../../src/images/logo-movida.png',10,6,30);
		// Arial bold (negrito) 11
		$this->SetFont('Times','B',12);
		// Cor de fundo
		$this->SetFillColor(255);
		// Line break (quebra de linha)
		$this->Ln(10);
		// Move to the right - espaçamento antes do texto começar
		$this->Cell(10);
		// Título
		$this->Cell(0,10, utf8_decode('Indicação de Condutor e Declaração de Responsabilidade'),0,1,'C','false');
		// Line break (quebra de linha)
		$this->Ln(2);
	}

}*/

$pdf->AddPage();

// Logo
$pdf->Image('../../../src/logo/logo-movida.png', 10, 6, 30);
// Arial bold (negrito) 11
$pdf->SetFont('Arial', 'B', 11);
// Cor de fundo
$pdf->SetFillColor(255);
// Line break (quebra de linha)
$pdf->Ln(10);
// Move to the right - espaçamento antes do texto começar
$pdf->Cell(10);
// Título
$pdf->Cell(0, 10, utf8_decode('TERMO DE DECLARAÇÃO DE RESPONSABILIDADE POR INFRAÇÃO DE TRÂNSITO E OUTRAS'), 0, 1, 'C', 'false');
// Line break (quebra de linha)
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10.5);

$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("\nPelo presente instrumento, eu $nome, portador(a) da Carteira Nacional de Habilitação (CNH) nº $cnh, da Cédula de Identidade RG n° $rg e inscrito(a) no CPF/MF sob o n° $cpf, residente e domiciliado na $endereco, $bairro - $cidade - $estado - CEP $cep, DECLARO, para os devidos fins de direito, ser o condutor do veículo de placa $placa - renavam $renavam - chassi $chassi, e único responsável pela infração de trânsito abaixo:\n\nAUTO INFRAÇÃO: $autoinfra   -    DATA HORA $dataInfraf \nDESCRIÇÃO: $descricao \nENDEREÇO INFRAÇÃO: $enderecoinfra - $cidadeinfra \n\nbem como nomeio e constituo como minha bastante procuradora a empresa Movida Locacao de Veiculos S.A., Sociedade Anonima Fechada, inscrita no CNPJ sob o nº 07976147002295, com sede na Av Bias Fortes, 704, Lourdes, Município de Belo Horizonte, Estado de Minas Gerais, CEP: 30170-011, legítima proprietária do veículo acima descrito, para que em meu nome assine o \"Termo de Apresentação do Condutor Infrator\", relativo à multa acima descrita nos termos do artigo 257, parágrafo 7° do Código de Trânsito Brasileiro e a resolução do Contran n° 149, de 19 de setembro de 2013.\n\nDECLARO, ainda, ciência quanto aos termos do artigo 257, parágrafo 8° do Código de Trânsito Brasileiro, que prevê que em caso de não identificação do condutor infrator e, em sendo o veículo de propriedade de pessoa jurídica, será lavrada nova multa, cujo valor será o da multa originária multiplicada pelo número de infrações iguais, cometidas no período subsequente de 12 (doze) meses, bem como ser responsável pelas referidas multas até a formalização de minha indicação como condutor infrator junto ao órgão de trânsito
responsável. \n\n $cidadeass, $dia de $mesd de $ano.\n\n\n\n\n\n____________________________________________________________\n$nome \n\n\n"), 0, 'J');

$pdf->Output();

/* \n\nValor desconto: $valdesconto1*/
?>