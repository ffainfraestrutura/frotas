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

$id=$_POST['id'];
//$id = 2759;

//echo $id;
//$id='2759';
$sql = "SELECT * FROM bdautofrota.tbmovidatramite where idtbmovidatramite= '$id';";
$resultado = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

$nome= $row['nome'];
$cpf= $row['cpf'];
$placa=$row['placa'];
$autoinfra=$row['autoinfra'];
//$valor = $row['valor'];
$dtcons = $row['dtcons'];

$locadora= $row['locadora'];
$idmulta = $row['idmulta'];

/*$valordescaux = $row['valor'] * 8 / 10;
$valordesc = sprintf('%0.2f', round($valordescaux, 2));
$valortotal2 = $valordesc+80;
$valortotal = sprintf('%0.2f', round($valortotal2, 2));*/

if($idmulta !=''){
	$sql1 = "SELECT * FROM bdautofrota.tbmulta where idtbmulta= '$idmulta';";
}else{
	$sql1 = "SELECT * FROM bdautofrota.tbmulta where placa= '$placa' AND autoinfracao = '$autoinfra';";
}

$resultado1 = mysqli_query($conn, $sql1) or die(mysqli_error($conn));
$row1 = mysqli_fetch_array($resultado1, MYSQLI_BOTH);
	$valor1= $row1['valor'];
	$valdesconto1= $row1['valdesconto'];
	$taxaadm1=$row1['taxaadm'];
	$juros=$row1['juros'];
	$valortotal = $row1['valtotal'];
	$valparcelas= $row1['valparcelas'];

if($locadora==''){
	$sql2="SELECT idlocador FROM bdautofrota.tbveiculo WHERE placa='$placa';";
	$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
	$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
		$locadora= $row2['idlocador'];
}

if($filial ==''){
	$sql3="SELECT unidade FROM bdautofrota.tbveiculo WHERE placa='$placa';";
	$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
	$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
		$filial= $row3['unidade'];
}


if($filial == 'FFA Sao Paulo' || $filial=='SP' || $filial=='FFA SP'){
	$cnpj='08.375.450/0005-02';
	$cidadeass='SÃO PAULO/SP';
}else{
	$cnpj='08.375.450/0001-70';
	$cidadeass='RIO DE JANEIRO/RJ';
}

if($taxaadm1 == '' || $taxaadm1 == '0'){
	if($locadora == '2' || $locadora == '9' || $locadora == '16' || $locadora == '17' || $locadora == '19'){//movida
		$taxaadm1 = round((0.15*$valor1),2);
	} elseif($locadora == '3' || $locadora == '4' || $locadora == '14' || $locadora == '15'){//localiza
		$taxaadm1 = '25.00';
	} elseif($locadora == '6' || $locadora == '8'){
		$taxaadm1 = round((0.2*$valor1),2);
		
	}
}



//if($valortotal == ''){
	$valortotal = ($valor1+$taxaadm1)-$valdesconto1;
//}

if($taxaadm1 == ''){
	$taxaadm1 = '0.00';
}

//tratando valor
$valor1=str_replace('.', ',', $valor1);
$valortotal=str_replace('.', ',', $valortotal);
$valdesconto1=str_replace('.', ',', $valdesconto1);

$taxaadm1=str_replace('.', ',', $taxaadm1);


if(strpos($taxaadm1, ",") === false){
	$taxaadm1 = $taxaadm1.",00";
}

if(strpos($valortotal, ",") === false){
	$valortotal = $valortotal.",00";
}

$hoje = date('Y-m-d');
$datah = explode("-", $hoje);
$ano = $datah[0];
$mes = $datah[1];
if ($mes == 1) {
	$mesd = 'janeiro';
} elseif ($mes == 2) {
	$mesd = 'fevereiro';
} elseif ($mes == 3) {
	$mesd = 'março';
} elseif ($mes == 4) {
	$mesd = 'abril';
} elseif ($mes == 5) {
	$mesd = 'maio';
} elseif ($mes == 6) {
	$mesd = 'junho';
} elseif ($mes == 7) {
	$mesd = 'julho';
} elseif ($mes == 8) {
	$mesd = 'agosto';
} elseif ($mes == 9) {
	$mesd = 'setembro';
} elseif ($mes == 10) {
	$mesd = 'outubro';
} elseif ($mes == 11) {
	$mesd = 'novembro';
} elseif ($mes == 12) {
	$mesd = 'dezembro';
}
$dia = $datah[2];

class PDF extends FPDF
{
// Page header
function Header()
{
	// Logo
	$this->Image('../../img/logo.png',10,6,30);
	// Arial bold 15
	$this->SetFont('Arial','B',12);
	// Cor de fundo
	$this->SetFillColor(215);
	// Line break
	$this->Ln(30);
	// Move to the right
	$this->Cell(40);
	// Title
	$this->Cell(120,20,'RECIBO DE ADIANTAMENTO SALARIAL',1,1,'C','true');
	// Line break
	$this->Ln(2);
}

}

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',10);
$pdf->Cell(40);
$pdf->MultiCell(120,5,utf8_decode("\nNome do Empregador: FFA INFRAESTRUTURA E SERVIÇOS LTDA\n\nCNPJ n.º $cnpj\n\n"),1,1);
$pdf->Ln(2);
$pdf->Cell(40);
$pdf->MultiCell(120,5,utf8_decode("\nNome do Empregado: $nome\n\nCPF: $cpf\n\n"),1,1);
$pdf->Ln(2);
$pdf->Cell(40);//R$ 15,62
$pdf->MultiCell(120,5,utf8_decode("\nAdiantamento Salarial para pagamento de MULTA DO VEÍCULO $placa\n\nAuto de infração: $autoinfra\n\nValor da multa: R$ $valor1\n\nValor Taxa Administração Locadora: R$ $taxaadm1\n\nValor desconto: $valdesconto1\n\nTotal: R$ $valortotal\n\nDeclaro, para todos os efeitos, ter recebido a título de \"Adiantamento Salarial para pagamento de multa\", a importância de R$ $valortotal em espécie, e  em consonância com o disposto no art. 462, caput, da CLT, tenho a  plena ciência de que o respectivo valor será descontado, pelo  empregador, quando do pagamento da minha remuneração mensal relativa à folha de pagamento.\n\n$cidadeass, $dia de $mesd de $ano.\n\n\n\n\n\n\n\n                              ___________________________________\n                                          Assinatura do Empregado \n\n\n"),1,1);
$pdf->Output();

/* \n\nValor desconto: $valdesconto1*/
?>

