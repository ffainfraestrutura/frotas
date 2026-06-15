<?php
require '../181/fpdf.php';
require('../../../control/conecta.php');
header("Content-type: text/html; charset=utf-8");

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$hoje = date('Y-m-d');

$id = $_POST['id'];

//echo $id;

$sql = "SELECT * FROM tbmovidatramite where idtbmovidatramite= '$id';";
$resultado = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

$nome = $row['nome'];
$matricula = $row['matricula'];
$cpf = $row['cpf'];
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
$resultado1 = mysqli_query($conn, $sql1) or die(mysqli_error($conn));
$row1 = mysqli_fetch_array($resultado1, MYSQLI_BOTH);
$valor1 = $row1['valor'];
$valdesconto1 = $row1['valdesconto'];
$taxaadm1 = $row1['taxaadm'];
$juros = $row1['juros'];
$valortotal = $row1['valtotal'];
$valorparcelas = $row1['valparcelas'];
$numparcelas = $row1['numparcelas'];
$filial = $row1['filial'];

if ($locadora == '') {
	$sql2 = "SELECT idlocador FROM tbveiculo WHERE placa='$placa';";
	$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
	$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
	$locadora = $row2['idlocador'];
}

$sql2 = "SELECT cnpj, Estado FROM bdffa.tbfilial WHERE idtbfilial = '$filial'; ";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$cnpj = $row2['cnpj'];
$estadofilial = $row2['Estado'];

if ($filial == '') {
	$sql3 = "SELECT unidade FROM tbveiculo WHERE placa='$placa';";
	$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
	$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
	$estadofilial = $row3['unidade'];
}


/*if($filial == 'FFA Sao Paulo' || $filial=='SP' || $filial=='FFA SP'){
	$cnpj='08.375.450/0005-02';
	$cidadeass='SÃO PAULO/SP';
}else{
	$cnpj='08.375.450/0001-70';
	$cidadeass='RIO DE JANEIRO/RJ';
}*/

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

	} elseif ($locadora == '1') {
		$taxaadm1 = '20.70';

	}
}
//if($valortotal == ''){
$valortotal = ($valor1 + $taxaadm1) - $valdesconto1;
//}

if ($taxaadm1 == '') {
	$sql4 = "SELECT tipoposse FROM tbveiculo WHERE placa='$placa';";
	$resultado4 = mysqli_query($conn, $sql4) or die(mysqli_error($conn));
	$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
	$tipoposse = $row4['tipoposse'];

	if ($tipoposse == 'PROPRIO') {
		$taxaadm1 = '20.70';
	} else {
		$taxaadm1 = '0.00';
	}

}


//tratando valor
$valor1 = str_replace('.', ',', $valor1);
$valortotal = str_replace('.', ',', $valortotal);
$valdesconto1 = str_replace('.', ',', $valdesconto1);
$valorparcelas = str_replace('.', ',', $valorparcelas);

$taxaadmf = str_replace('.', ',', $taxaadm1);

$preco = explode(',', $valortotal);
$centavos = $preco[1];
$real = $preco[0];

if (strlen($centavos) == 1) {
	$valortotal = $valortotal . "0";
}


if (strpos($taxaadmf, ",") === false) {
	$taxaadmf = $taxaadmf . ",00";
} else {
	$precotaxaadmf = explode(',', $taxaadmf);
	$centavostaxaadmf = $precotaxaadmf[1];
	$realtaxaadmf = $precotaxaadmf[0];

	if (strlen($centavostaxaadmf) == 1) {
		$taxaadmf = $taxaadmf . "0";
	}
}

if (strpos($valortotal, ",") === false) {
	$valortotal = $valortotal . ",00";
}
if (strpos($valorparcelas, ",") === false) {
	$valorparcelas = $valorparcelas . ",00";
}

$sql5 = "SELECT data_e_hora FROM tblog WHERE placa='$placa' AND mat_autor='$matricula' AND tipo = 'multa' AND acao LIKE '%assinou recibo%';";
$resultado5 = mysqli_query($conn, $sql5) or die(mysqli_error($conn));
$row5 = mysqli_fetch_array($resultado5, MYSQLI_BOTH);
$data_e_hora = $row5['data_e_hora'];

/*$dataassin = new DateTime($data_e_hora);
$dataassinf = $dataassin-> format( 'd-m-Y' );*/

if ($data_e_hora == '') {
	$datah = explode("-", $hoje);
	$ano = $datah[0];
	$mes = $datah[1];
	$dia = $datah[2];

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
} else {
	$datah1 = explode(" ", $data_e_hora);
	$datah = explode("-", $datah1[0]);

	$ano = $datah[0];
	$mes = $datah[1];
	$dia = $datah[2];

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

}




if (!file_exists("../../../assinaturas/docs/colass/$matricula.png")) {

	print "<script> window.alert('Assinatura digital não cadastrada.');
          window.close(); </script>";

} else {
	class PDF extends FPDF
	{
		// Page header
		function Header()
		{
			// Logo
			$this->Image('../../src/images/logo.png', 10, 6, 20);
			// Arial bold 15
			$this->SetFont('Arial', 'B', 12);
			// Cor de fundo
			$this->SetFillColor(215);
			// Line break
			$this->Ln(0.1);
			// Move to the right
			$this->Cell(22);
			// Title
			$this->Cell(160, 20, 'RECIBO DE ADIANTAMENTO SALARIAL', 1, 1, 'C', 'true');
			// Line break
			$this->Ln(2);
		}

	}

	// Instanciation of inherited class
	$pdf = new PDF();
	$pdf->AliasNbPages();
	$pdf->AddPage();
	$pdf->SetFont('Arial', '', 10);
	$pdf->Cell(5);
	$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregador: FFA INFRAESTRUTURA E SERVIÇOS LTDA\nCNPJ n.º $cnpj\n"), 1, 1);

	$pdf->Ln(2);
	$pdf->Cell(5);
	$pdf->MultiCell(177, 5, utf8_decode("Nome do Empregado: $nome\nCPF: $cpf\n"), 1, 1);
	$pdf->Ln(2);
	$pdf->Cell(5);//R$ 15,62
	$pdf->MultiCell(177, 5, utf8_decode("Adiantamento Salarial para pagamento de MULTA DO VEÍCULO $placa\n\nAuto de infração: $autoinfra\nValor da multa: R$ $valor1\nValor Taxa Administração Locadora: R$ $taxaadmf\n\nValor desconto: $valdesconto1\nTotal: R$ $valortotal\nQuantidade de Parcelas: $numparcelas\nValor das Parcelas: R$ $valorparcelas\n\nDeclaro, para os devidos fins, que recebi da empresa, a título de Adiantamento Salarial para pagamento de multa, a importância de R$ $valortotal, em espécie.\nEm conformidade com o disposto no artigo 462, caput, da Consolidação das Leis do Trabalho (CLT), estou ciente de que o referido valor será integralmente descontado da minha remuneração mensal, por meio de $numparcelas parcelas de R$ $valorparcelas, a serem abatidas diretamente na folha de pagamento, até a quitação total do valor antecipado..\n\n$cidadeass, $dia de $mesd de $ano. \n\n\n\n\n______________________________________________________\n     Assinatura do Empregado\n\n\n\n\n\n"), 1, 1);

	//$pdf->Image('../../src/images/logo.png',10,6,20);
	$pdf->Image("../../../assinaturas/docs/colass/$matricula.png", 20, 160, 80);

	$pdf->Output();

	/* \n\nValor desconto: $valdesconto1*/
}
?>