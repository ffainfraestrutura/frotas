<?php
require '../181/fpdf.php';
require('../../../control/conecta.php');
header("Content-type: text/html; charset=utf-8");
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('memory_limit', '256M'); // Aumenta para 256MB
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
$link = "$_SERVER[REQUEST_URI]";
$idaux = explode("=", $link);
$id = $idaux[1];
$valordescaux = $row[6] * 8 / 10;
$valordesc = sprintf('%0.2f', round($valordescaux, 2));
$valortotal2 = $valordesc+80;
$valortotal = sprintf('%0.2f', round($valortotal2, 2));
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

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Times','',10);
$pdf->Cell(40);
$pdf->MultiCell(120,5,utf8_decode("\nNome do Empregador: FFA INFRAESTRUTURA E SERVIÇOS LTDA\n\nCNPJ n.º 08.375.450/0001-70\n\n"),1,1);
$pdf->Ln(2);
$pdf->Cell(40);
$pdf->MultiCell(120,5,utf8_decode("\nNome do Empregado: Lucas\n\nCPF: 15780987777\n\n"),1,1);
$pdf->Ln(2);
$pdf->Cell(40);
$pdf->MultiCell(120,5,utf8_decode("\nAdiantamento Salarial para pagamento de MULTA DO VEÍCULO 123456\n\nAuto de infração:123456\n\nValor da multa: R$ 5,00\n\nValor Taxa Administração Locadora: R$ 80,00\n\nTotal: R$ 85,00\n\nDeclaro, para todos os efeitos, ter recebido a título de (Adiantamento Salarial para pagamento de multa), a importância de R$ 85,00. em espécie, e  em consonância com o disposto no art. 462, caput, da CLT, tenho a  plena ciência de que o respectivo valor será descontado, pelo  empregador, quando do pagamento da minha remuneração mensal relativa à folha de pagamento.\n\nRio de Janeiro, 21 de 02 de 2022.\n\n\n\n\n\n\n\n\n\n                                                             ___________________________________\n                                                                                         Assinatura do Empregado"),1,1);
$pdf->Output();
?>

