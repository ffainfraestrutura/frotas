<?php
require'fpdf.php';
require('../../conecta.php');
require('../../conecta2.php');
header("Content-type: text/html; charset=utf-8");



/*$link = "$_SERVER[REQUEST_URI]";
$idaux = explode("=", $link);
$id = $idaux[1];*/

$id = $_POST['id'];
//$id = 7855;

//echo $id;
//$id='2759';
$sql = "SELECT * FROM bdfrota.tbmovidatramite where idtbmovidatramite= '$id';";
$resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
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
	$sql1 = "SELECT * FROM bdfrota.tbmulta where idtbmulta= '$idmulta';";
}else{
	$sql1 = "SELECT * FROM bdfrota.tbmulta where placa= '$placa' AND autoinfracao = '$autoinfra';";
}

$resultado1 = mysqli_query($conexao, $sql1) or die(mysqli_error($conexao));
$row1 = mysqli_fetch_array($resultado1, MYSQLI_BOTH);
	$valor1= $row1['valor'];
	$valdesconto1= $row1['valdesconto'];
	$taxaadm1=$row1['taxaadm'];
	$juros=$row1['juros'];
	$valortotal = $row1['valtotal'];
	$valparcelas= $row1['valparcelas'];
	$filial = $row1['filial'];

if($locadora==''){
	$sql2="SELECT idlocador FROM bdfrota.tbveiculo WHERE placa='$placa';";
	$resultado2 = mysqli_query($conexao, $sql2) or die(mysqli_error($conexao));
	$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
		$locadora= $row2['idlocador'];
}

$sql2="SELECT cnpj, Estado FROM bdaniel.tbfilial WHERE nome='$filial'; ";
$resultado2 = mysqli_query($conexao, $sql2) or die(mysqli_error($conexao));
	$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
		$cnpj= $row2['cnpj'];
		$estadofilial = $row2['Estado'];

if($filial ==''){
	$sql3="SELECT unidade FROM bdfrota.tbveiculo WHERE placa='$placa';";
	$resultado3 = mysqli_query($conexao, $sql3) or die(mysqli_error($conexao));
	$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
		$estadofilial= $row3['unidade'];
}


/*if($filial == 'FFA Sao Paulo' || $filial=='SP' || $filial=='FFA SP'){
	$cnpj='08.375.450/0005-02';
	$cidadeass='SÃO PAULO/SP';
}else{
	$cnpj='08.375.450/0001-70';
	$cidadeass='RIO DE JANEIRO/RJ';
}*/

if($estadofilial == 'SP'){
	if(empty($cnpj)){
		$cnpj='08.375.450/0005-02';
	}
	
	$cidadeass='SÃO PAULO/SP';

}elseif($estadofilial == 'RJ'){
	if(empty($cnpj)){
		$cnpj='08.375.450/0001-70';
	}

	$cidadeass='RIO DE JANEIRO/RJ';

}elseif($estadofilial == 'PR'){
	if(empty($cnpj)){
		$cnpj='08.375.450/0017-38';
	}

	$cidadeass='CURITIBA/PR';
}

if($taxaadm1 == '' || $taxaadm1 == '0'){
	if($locadora == '2' || $locadora == '9' || $locadora == '16' || $locadora == '17' || $locadora == '19'){//movida
		$taxaadm1 = round((0.15*$valor1),2);
	} elseif($locadora == '3' || $locadora == '4' || $locadora == '14' || $locadora == '15'){//localiza
		$taxaadm1 = '25.00';
	} elseif($locadora == '6' || $locadora == '8'){
		$taxaadm1 = round((0.2*$valor1),2);
		
	}elseif($locadora == '1'){
		$taxaadm1 = '20.70';
		
	}
}



if($taxaadm1 == ''){
	$sql4="SELECT tipoposse FROM bdfrota.tbveiculo WHERE placa='$placa';";
	$resultado4 = mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));
	$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
		$tipoposse= $row4['tipoposse'];

	if($tipoposse=='PROPRIO'){
		$taxaadm1 = '20.70';
	}else{
		$taxaadm1 = '0.00';
	}
	
}

//if($valortotal == ''){
	$valortotal = ($valor1+$taxaadm1)-$valdesconto1;
//}

//tratando valor
$valor1=str_replace('.', ',', $valor1);
$valortotal=str_replace('.', ',', $valortotal);
$valdesconto1=str_replace('.', ',', $valdesconto1);

$taxaadmf=str_replace('.', ',', $taxaadm1);

$preco = explode(',',$valortotal);
$centavos = $preco[1];
$real = $preco[0];

if(strlen($centavos) == 1){
	$valortotal= $valortotal."0";
}

$precotaxaadmf = explode(',',$taxaadmf);
$centavostaxaadmf = $precotaxaadmf[1];
$realtaxaadmf = $precotaxaadmf[0];

/*if(strpos($taxaadm1, ",") === false){
	$taxaadm1 = $taxaadm1.",00";
}*/

if(strlen($centavostaxaadmf) == 1){
	$vtaxaadmf = $taxaadmf."0";
}

if(strpos($valortotal, ",") === false){
	$valortotal = $valortotal.",00";
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
    	$mesd='';
}

$dia = $datah[2];

class PDF extends FPDF
{
// Page header
function Header()
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
}

}

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',10);
$pdf->Cell(5);
$pdf->MultiCell(177,5,utf8_decode("Nome do Empregador: FFA INFRAESTRUTURA E SERVIÇOS LTDA\nCNPJ n.º $cnpj\n"),1,1);

$pdf->Ln(2);
$pdf->Cell(5);
$pdf->MultiCell(177,5,utf8_decode("Nome do Empregado: $nome\nCPF: $cpf\n"),1,1);
$pdf->Ln(2);
$pdf->Cell(5);//R$ 15,62
$pdf->MultiCell(177,5,utf8_decode("Adiantamento Salarial para pagamento de MULTA DO VEÍCULO $placa\n\nAuto de infração: $autoinfra\nValor da multa: R$ $valor1\nValor Taxa Administração Locadora: R$ $taxaadmf\n\nValor desconto: R$ $valdesconto1\nTotal: R$ $valortotal\n\nDeclaro, para todos os efeitos, ter recebido a título de \"Adiantamento Salarial para pagamento de multa\", a importância de R$ $valortotal em espécie, e  em consonância com o disposto no art. 462, caput, da CLT, tenho a  plena ciência de que o respectivo valor será descontado, pelo  empregador, quando do pagamento da minha remuneração mensal relativa à folha de pagamento.\n\n$cidadeass, $dia de $mesd de $ano.\n\n\n\n\n\n                              ______________________________________________________\n                                                                   Assinatura do Empregado \n\n\n"),1,1);
$pdf->Output();

/* \n\nValor desconto: $valdesconto1*/
?>

