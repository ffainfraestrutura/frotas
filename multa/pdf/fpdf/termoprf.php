<?php
require'fpdf.php';
require('../../conecta.php');
require('../../conecta2.php');
header("Content-type: text/html; charset=utf-8");

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
		//$this->Image('../../src/images/logo-detran.png',20,5,45,15);
		// Arial bold (negrito) 11
		$this->SetFont('Arial','B',11);
		// Cor de fundo
		$this->SetFillColor(255);
		// Line break (quebra de linha)
		$this->Ln(-5);
		// Move to the right - espaçamento antes do texto começar
		$this->Cell(10);
		// Título
		$this->MultiCell(0,14.8, utf8_decode('IDENTIFICAÇÃO DE CONDUTOR INFRATOR'),1,1,'R','false');
		// Line break (quebra de linha)
		//$this->Ln(2);
	}

}


$id=$_POST['id'];


//echo $id;

$sql = "SELECT * FROM bdfrota.tbmovidatramite where idtbmovidatramite ='$id';";
$resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);
	$nome= $row['nome'];
	$placa=$row['placa'];
	$autoinfra=$row['autoinfra'];
	$datainfra = $row['dtinfra'];

$datainfra1 = explode(" ", $datainfra);
$datainfra2 = explode("-", $datainfra1[0]);
$datainfraf = "$datainfra2[2]/$datainfra2[1]/$datainfra2[0] $datainfra1[1]";

$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE nome = '$nome'; ";
$resultado2 = mysqli_query($conexao, $sql2) or die(mysqli_error($conexao));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
	$cpf = $row2['cpf'];
	$matricula = $row2['matricula'];

$sql3 = "SELECT numcnh,uf FROM bdfrota.tbcnh WHERE matricula='$matricula'; ";
$resultado3 =  mysqli_query($conexao, $sql3) or die(mysqli_error($conexao));
$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
	$cnh = $row3['numcnh'];
	$uf = $row3['uf'];

$sql4 = "SELECT email,telefone FROM bdfrota.tbusuario WHERE matricula='$matricula'; ";
$resultado4 =  mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
	$email = $row4['email'];
	$telefone = $row4['telefone'];

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

$pdf->Ln(1);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->Cell(0,6,utf8_decode("1. Dados da infração"), 1, 'L', 'J', true);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->SetFont('Arial','',8);
$pdf->Cell(60,8,utf8_decode("Placa: $placa"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(60,8,utf8_decode("Nº do Auto de infração: $autoinfra"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,8,utf8_decode("Data da infração: $datainfraf"),1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(0,6,utf8_decode("2. Dados do Condutor Infrator"), 1, 'L', 'J', true);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->SetFont('Arial','',8);

$pdf->MultiCell(80,6,utf8_decode("Nome:\n $nome"),1, 'J', false);

$pdf->Ln(-12);
$pdf->Cell(90);
$pdf->MultiCell(100,6,utf8_decode("Assinatura (igual ao documento apresentado):\n\n"),1, 'J', false);

$pdf->Ln(0);
$pdf->Cell(10);
$pdf->MultiCell(80,6,utf8_decode("CPF:\n $cpf"),1, 'J', false);

$pdf->Ln(-12);
$pdf->Cell(90);
$pdf->MultiCell(80,6,utf8_decode("Nº Registro da CNH:\n $cnh"),1, 'J', false);

$pdf->Ln(-12);
$pdf->Cell(170);
$pdf->MultiCell(20,6,utf8_decode("UF:\n$uf"),1, 'J', false);

if(empty($uf)){
	$pdf->Ln(6);
}else{
	$pdf->Ln(0);
}

if(empty($email)){
	$pdf->Cell(10);
	$pdf->MultiCell(80,6,utf8_decode("Correio eletrônico(E-mail) - opcional:\n\n"),1, 'J', false);
}else{
	$pdf->Cell(10);
	$pdf->MultiCell(80,6, utf8_decode("Correio eletrônico(E-mail) - opcional:\n$email"),1,'J', false);
}

$pdf->Ln(-12);
$pdf->Cell(90);
$pdf->MultiCell(100,6,utf8_decode("DDD-TELEFONE - opcional:\n$telefone"),1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(0,6,utf8_decode("3. Dados  (  )Proprietátio (  )Condutor"), 1, 'L', 'J', true);

$pdf->SetFont('Arial','',8);
$pdf->Ln(6);
$pdf->Cell(10);
$pdf->MultiCell(80,6,utf8_decode("Nome:\n "),1, 'J', false);

$pdf->Ln(-12);
$pdf->Cell(90);
$pdf->MultiCell(100,6,utf8_decode("Assinatura(igual ao documento apresentado):\n "),1, 'J', false);

$pdf->Ln(0);
$pdf->Cell(10);
$pdf->MultiCell(80,8,utf8_decode("Correio eletrônico(E-mail) - opcional:\n\n"),1, 'J', false);

$pdf->Ln(-16);
$pdf->Cell(90);
$pdf->MultiCell(80,8,utf8_decode("DDD-TELEFONE - opcional:\n\n"),1, 'J', false);

$pdf->Ln(-16);
$pdf->Cell(170);
$pdf->MultiCell(20,8,utf8_decode("UF:\n\n"),1, 'J', false);

$pdf->Ln(8);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(0,6,utf8_decode("APENAS PARA PROTOCOLO PRESENCIAL\nPara uso do Recebedor/PRF"), 1, 'L', 'J', true);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->SetFont('Arial','',8);
$pdf->MultiCell(80, 8,utf8_decode("Recebido em:\n\nNome/Matrícia/RG:\n\nAssinatura/Carimbo:\n\n"),1, 'J', false);

$pdf->Output();
?>

