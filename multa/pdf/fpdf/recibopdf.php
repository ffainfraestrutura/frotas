<?php
require '../181/fpdf.php';
require('../../../control/conecta.php');
header("Content-type: text/html; charset=utf-8");
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('memory_limit', '256M'); // Aumenta para 256MB
$link = "$_SERVER[REQUEST_URI]";
$idaux = explode("=", $link);
$id = $idaux[1];
$sql = "SELECT * FROM bdfrota.tbsmultas where id=" . $id . "";
$resultado = mysql_query($sql) or die(mysql_error());
$row = mysql_fetch_array($resultado);
$valordescaux = $row[6] * 8 / 10;
$valordesc = sprintf('%0.2f', round($valordescaux, 2));
$valortotal2 = $valordesc + 80;
$valortotal = sprintf('%0.2f', round($valortotal2, 2));
$hoje = date('Y-m-d');
$datah = explode("-", $hoje);
$ano = $datah[0];
$mes = $datah[1];
$taxaadm1 = $row['taxaadm'];
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

if ($taxaadm1 == '') {
    $sql4 = "SELECT tipoposse FROM bdfrota.tbveiculo WHERE placa='$placa';";
    $resultado4 = mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));
    $row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
    $tipoposse = $row4['tipoposse'];

    if ($tipoposse == 'PROPRIO') {
        $taxaadm1 = '20.70';
    } else {
        $taxaadm1 = '0.00';
    }

}

$taxaadmf = str_replace('.', ',', $taxaadm1);

// Instanciation of inherited class
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
$pdf->MultiCell(177, 5, utf8_decode("Adiantamento Salarial para pagamento de MULTA DO VEÍCULO $row[1]\n\nAuto de infração: $row[4]\nValor da multa: R$ $valordesc\nValor Taxa Administração Locadora: R$ $taxaadmf\n\nTotal: R$ $valortotal\nQuantidade de Parcelas: $row[26]\nValor das Parcelas: R$ $row[27]\n\nDeclaro, para os devidos fins, que recebi da empresa, a título de Adiantamento Salarial para pagamento de multa, a importância de R$ $valortotal, em espécie.\nEm conformidade com o disposto no artigo 462, caput, da Consolidação das Leis do Trabalho (CLT), estou ciente de que o referido valor será integralmente descontado da minha remuneração mensal, por meio de $row[26] parcelas de R$ $row[27], a serem abatidas diretamente na folha de pagamento, até a quitação total do valor antecipado..\n\nRio de Janeiro, $dia de $mesd de $ano. \n\n\n\n\n______________________________________________________\n     Assinatura do Empregado\n\n\n\n\n\n"), 1, 1);
//$pdf->Image('../../src/images/logo.png',10,6,20);
$pdf->Image("../../../assinaturas/docs/colass/$matricula.png", 20, 160, 80);

$pdf->Output();

/* \n\nValor desconto: $valdesconto1*/
?>