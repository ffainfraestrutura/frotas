<?php
require '../181/fpdf.php';
require('../../../control/conecta.php');
header("Content-type: text/html; charset=utf-8");
// ini_set('display_errors', '1');
// ini_set('display_startup_errors', '1');
// error_reporting(E_ALL);
ini_set('memory_limit', '256M'); // Aumenta para 256MB



/*$link = "$_SERVER[REQUEST_URI]";
$idaux = explode("=", $link);
$id = $idaux[1];*/

$id = $_POST['id'];

//echo $id;

$sql = "SELECT * FROM tbmovidatramite where idtbmovidatramite= '$id';";
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
$filial = $row1['filial'];
$dataInfra = $row1['datainfracao'];
$descricao = utf8_encode($row1['descricaoinfra']);
$enderecoinfra = utf8_encode($row1['endereco']);
$codmulta = $row1['codigom'];
$orgaoautuador = utf8_encode($row1['orgao']);
$valorparcelas = $row1['valparcelas'];
$numparcelas = $row1['numparcelas'];
$valorparcelas = str_replace('.', ',', $valorparcelas);
if (strpos($valorparcelas, ",") === false) {
	$valorparcelas = $valorparcelas . ",00";
}

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
    BdPonto.tbfilial fi ON fi.idtbfilial = f.codfilial
WHERE
    f.matricula = '$matricula'";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$cnpj = $row2[0];
$estadofilial = $row2['Estado'];

if ($filial == '') {
	$sql3 = "SELECT unidade FROM tbveiculo WHERE placa='$placa';";
	$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
	$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
	$estadofilial = $row3['unidade'];
}


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

$hojef = $datah[2] . "/" . $datah[1] . "/" . $datah[0];

$sql2a = "SELECT MAX(idtbfuncionario) AS idtbfuncionario FROM bdcorp.tbfuncionario WHERE nome = '$nome' AND status != 'demitido'";
$resultado2a = mysqli_query($conn, $sql2a) or die(mysqli_error($conn));
$row2a = mysqli_fetch_array($resultado2a, MYSQLI_BOTH);
$idtbfuncionario = $row2a['idtbfuncionario'];

//$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE nome = '$nome'; ";
$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE idtbfuncionario = '$idtbfuncionario'; ";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$rg = $row2['rg'];
$orgaorg = $row2['orgaorg'];
$cpf = $row2['cpf'];
$endereco = utf8_encode($row2['endereco']);
$bairro = utf8_encode($row2['bairro']);
$cidade = utf8_encode($row2['cidade']);
$estado = $row2['estado'];
$cep = $row2['cep'];
$matricula = $row2['matricula'];

$sql3 = "SELECT renavam, chassi, modelo FROM tbveiculo WHERE placa='$placa'; ";
$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
$chassi = $row3['chassi'];
$renavam = $row3['renavam'];
$modelo = $row3['modelo'];

$sql4 = "SELECT numcnh,uf FROM tbcnh WHERE matricula='$matricula'; ";
$resultado4 = mysqli_query($conn, $sql4) or die(mysqli_error($conn));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
$cnh = $row4['numcnh'];
$ufcnh = $row4['uf'];

$sql5 = "SELECT modelo FROM tbmodeloveic WHERE idtbmodeloveic='$modelo'; ";
$resultado5 = mysqli_query($conn, $sql5) or die(mysqli_error($conn));
$row5 = mysqli_fetch_array($resultado5, MYSQLI_BOTH);
$modelof = $row5['modelo'];

// $sql6 = "SELECT telefone FROM tbusuario WHERE matricula='$matricula'; ";
// $resultado6 = mysqli_query($conn, $sql6) or die(mysqli_error($conn));
// $row6 = mysqli_fetch_array($resultado6, MYSQLI_BOTH);
// $telefone = $row6['telefone'];
// $ddd = substr($telefone, 0, 2);
// $telefone = substr($telefone, 2);

class PDF extends FPDF
{
	// Page header
/*function Header()
{
	// Logo
	$this->Image('../../img/logo.png',10,6,20);
	// Arial bold 15
	$this->SetFont('Arial','B',12);
	// Cor de fundo
	$this->SetFillColor(215);
	// Line break
	$this->Ln(0.1);
	// Move to the right
	$this->Cell(22);
	// Title
	$this->Cell(160,20,'RECIBO DE ADIANTAMENTO SALARIAL',1,1,'C','true');
	// Line break
	$this->Ln(2);
}*/

}

// Instanciation of inherited class
$pdf = new PDF();

$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->Image('../../../src/logo/logo.png', 10, 6, 20);
// Arial bold 15
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
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregador: FFA INFRAESTRUTURA E SERVIÇOS LTDA\nCNPJ n.º $cnpj\n"), 1, 1);

$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregado: $nome\nCPF: $cpf\n"), 1, 1);
$pdf->Ln(2);
$pdf->Cell(5);//R$ 15,62
$pdf->MultiCell(177, 5, utf8_decode("Adiantamento Salarial para pagamento de MULTA DO VEÍCULO $placa\n\nAuto de infração: $autoinfra\nValor da multa: R$ $valor1\nValor Taxa Administração Locadora: R$ $taxaadm1\n\nValor desconto: $valdesconto1\nTotal: R$ $valortotal\nQuantidade de Parcelas: $numparcelas\nValor das Parcelas: R$ $valorparcelas\n\nDeclaro, para os devidos fins, que recebi da empresa, a título de Adiantamento Salarial para pagamento de multa, a importância de R$ $valortotal, em espécie.\nEm conformidade com o disposto no artigo 462, caput, da Consolidação das Leis do Trabalho (CLT), estou ciente de que o referido valor será integralmente descontado da minha remuneração mensal, por meio de $numparcelas parcelas de R$ $valorparcelas, a serem abatidas diretamente na folha de pagamento, até a quitação total do valor antecipado..\n\n$cidadeass, $dia de $mesd de $ano. \n\n\n\n\n______________________________________________________\n     Assinatura do Empregado\n\n\n\n\n\n"), 1, 1);

// Instanciation of inherited class


$pdf->AddPage();

$pdf->Image('../../../src/logo/logo-localiza.png', 10, 6, 35);
// Arial bold (negrito) 11
$pdf->SetFont('Arial', 'B', 12);
// Cor de fundo
$pdf->SetFillColor(255);
// Line break (quebra de linha)
$pdf->Ln(0);
// Move to the right - espaçamento antes do texto começar
$pdf->Cell(35);
// Título
$pdf->Cell(0, 10, utf8_decode('TERMO DE RESPONSABILIDADE POR INFRAÇÃO DE TRÂNSITO'), 0, 1, 'J', 'false');
// Line break (quebra de linha)
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);

$pdf->Cell(8);
$pdf->MultiCell(30, 5, utf8_decode("\nContrato nº:\n\nCliente:\n\n\nPlaca/Modelo:\n"), 0, 'J');

$pdf->Ln(-35);
$pdf->Cell(40);
$pdf->MultiCell(0, 5, utf8_decode("\nRIO0007/21\nFFA INFRAESTRUTURA E SERVICOS LTDA\nRUA TANAGRA, nº42 - OLARIA\nCEP: 21031560 - RIO DE JANEIRO - RJ - BRASIL\n\n$placa/$modelof"), 0, 'J');

$pdf->Ln(2);
$pdf->Cell(10);
$pdf->MultiCell(0, 5, utf8_decode("__________________________________________________________________________________"), 0, 'J');

$pdf->Ln(5);
$pdf->Cell(8); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("INFORMAÇÕES DO CONDUTOR QUE SERÁ INDICADO"), 0, 'C');

$pdf->Ln(5);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(40, 6, utf8_decode("Nome do Condutor:"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', 'U');
$pdf->Cell(46); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(90, 6, utf8_decode("$nome"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', '');
$pdf->Cell(136); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(20, 6, utf8_decode("CPF:"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', 'U');
$pdf->Cell(146); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(70, 6, utf8_decode("$cpf"), 0, 'J');

$pdf->Ln(4);
$pdf->SetFont('', '');
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(80, 6, utf8_decode("Nº Registro Carteira Nacional de Habilitação:"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', 'U');
$pdf->Cell(88); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(80, 6, utf8_decode("$cnh"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', '');
$pdf->Cell(118); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(80, 6, utf8_decode("UF(CNH):"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', 'U');
$pdf->Cell(138); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(70, 6, utf8_decode("$ufcnh"), 0, 'J');

$pdf->Ln(4);
$pdf->SetFont('', '');
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(10, 6, utf8_decode("RG:"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', 'U');
$pdf->Cell(18); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(30, 6, utf8_decode("$rg"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', '');
$pdf->Cell(60); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(40, 6, utf8_decode("Órgão Emissor:"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', 'U');
$pdf->Cell(90); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(30, 6, utf8_decode("$orgaorg"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', '');
$pdf->Cell(130); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(40, 6, utf8_decode("UF(RG):"), 0, 'J');

$pdf->Ln(-6);
$pdf->SetFont('', 'U');
$pdf->Cell(147); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(30, 6, utf8_decode("$ufrg"), 0, 'J');

$pdf->Ln(8);
$pdf->SetFont('', '');
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("O Usuário / condutor identificado acima declara que estava em posse do carro placa $placa
no dia $dataInfraf, momento do cometimento da infração nº AIT $autoinfra, emitida pelo(a) $orgaoautuador."), 0, 'C');

$pdf->Ln(0);
$pdf->Cell(4); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("Data:"), 0, 'C');

$pdf->Ln(-6);
$pdf->SetFont('', 'U');
$pdf->Cell(35); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("$hojef"), 0, 'C');

$pdf->Ln(8);
$pdf->SetFont('', '');
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("Assinatura: ____________________________________________________________"), 0, 'C');

$pdf->Ln(1);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("(Idêntica à assinatura da CNH)"), 0, 'C');

$pdf->Ln(4);
$pdf->SetFont('', 'B', 9);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("Considerando a ciência do Usuário quanto ao Contrato de Aluguel e Gestão de Frota nº xxxxxxx"), 0, 'J');

$pdf->Ln(0);
$pdf->SetFont('', '', 8.8);
$pdf->Cell(12); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0, 6, utf8_decode("firmado entre LOCALIZA FLEET S/A, CNPJ 02.286.479/0001-08, com sede em Belo Horizonte/MG Localiza Gestão de Frotas, e o Cliente (“Contrato”), o Usuário / condutor declara que:\n1) Assume a responsabilidade pela infração supracitada cometida na condução do Carro alugado e pela pontuação decorrente desta infração, nos termos do artigo 5º e seus parágrafos, da Resolução 619/16 do CONTRAN, e da cláusula 1.7.1 do Contrato.\n2) Autoriza a Localiza Fleet a assinar o termo de apresentação do condutor / infrator das multas de trânsito que envolvam o Carro alugado, nos termos do artigo 257, parágrafos 1º, 3º, 7º e 8º, do Código Brasileiro de Trânsito, e da cláusula 1.7.1 do Contrato.\n3) É preposto autorizado pelo Cliente a conduzir os Carros alugados nos termos do Contrato.\n4) Não havendo identificação do infrator e sendo o veículo de propriedade de pessoa jurídica, será lavrada nova multa ao proprietário do veículo, mantida a originada pela infração, cujo valor é o da multa multiplicada pelo número de infrações iguais cometidas no período de doze meses.\n5) O preposto responsabiliza-se nas esferas cível, administrativa e penal, pela veracidade das informações e dos documentos fornecidos.\n6) A identificação do condutor infrator só surtirá efeito se estiver corretamente preenchida, assinada e acompanhada de cópia legível do documento de habilitação, além de documento que comprove a assinatura do condutor infrator, quando esta não constar do referido documento.\n"), 0, 'J');

$pdf->Image('../../../src/logo/rodape-localiza.png', 50, 280, 120);

$pdf->Output();

/* \n\nValor desconto: $valdesconto1*/
?>