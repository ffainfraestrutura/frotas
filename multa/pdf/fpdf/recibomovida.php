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
		// Logo - CORRIGIDO: adicionado o 4º parâmetro (altura) como 0
		$this->Image('../../../src/logo/logo-movida.png', 10, 6, 30, 0);
		// Arial bold (negrito) 11
		$this->SetFont('Arial', 'B', 11);
		// Cor de fundo
		$this->SetFillColor(255);
		// Line break (quebra de linha)
		$this->Ln(10);
		// Move to the right - espaçamento antes do texto começar
		$this->Cell(10);
		// Título
		$this->Cell(0, 10, utf8_decode('TERMO DE DECLARAÇÃO DE RESPONSABILIDADE POR INFRAÇÃO DE TRÂNSITO E OUTRAS'), 0, 1, 'C', 'false');
		// Line break (quebra de linha)
		$this->Ln(2);
	}

}


// CORRIGIDO: Verificar se o parâmetro 'id' existe
$id = $_GET['id'] ?? '';

if (empty($id)) {
	die('ID não informado');
}

$sql = "SELECT * FROM tbmovidatramite where idtbmovidatramite='$id';";
$resultado = mysqli_query($conn, $sql) or die(mysqli_error($conn));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);

// CORRIGIDO: Verificar se a consulta retornou dados
if (!$row) {
	die('Registro não encontrado');
}

$nome = $row['nome'] ?? '';
$cpf = $row['cpf'] ?? '';
$placa = $row['placa'] ?? '';
$autoinfra = $row['autoinfra'] ?? '';
$valor = $row['valor'] ?? 0;
$idmovida = $row['idmovida'] ?? '';
$autoinfracao = $row['autoinfra'] ?? '';
$idmulta = $row['idmulta'] ?? '';


$valorlocador = '15.62';
$valortotal = floatval($valor) + floatval($valorlocador);

//tratando valor
$valor = str_replace('.', ',', $valor);
$valortotal = str_replace('.', ',', $valortotal);

$hoje = date('Y-m-d');
$datah = explode("-", $hoje);
$ano = $datah[0];
$mes = $datah[1];

// CORRIGIDO: Array mais limpo para os meses
$meses = [
	1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
	5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
	9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
];
$mesd = $meses[(int)$mes] ?? '';
$dia = $datah[2];

$sql2 = "SELECT rg, cpf, endereco, bairro, cidade, estado, cep, matricula FROM bdcorp.tbfuncionario WHERE nome = '$nome' /*AND status<>'Demitido'*/; ";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);

$rg = $row2['rg'] ?? '';
$cpf = $row2['cpf'] ?? '';
$endereco = isset($row2['endereco']) ? utf8_encode($row2['endereco']) : '';
$bairro = isset($row2['bairro']) ? utf8_encode($row2['bairro']) : '';
$cidade = isset($row2['cidade']) ? utf8_encode($row2['cidade']) : '';
$estado = $row2['estado'] ?? '';
$cep = $row2['cep'] ?? '';
$matricula = $row2['matricula'] ?? '';

if ($nome == 'WALLACE GONCALVES DE OLIVEIRA') {
	$rg = '0203954557';
}

if ($nome == 'MAXIMIANO BARBOSA DE JESUS NETO') {
	$rg = '122126055';
}

$sql3 = "SELECT renavam, chassi FROM tbveiculo WHERE placa='$placa'; ";
$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);

$chassi = $row3['chassi'] ?? '';
$renavam = $row3['renavam'] ?? '';

$sql4 = "SELECT numcnh FROM tbcnh WHERE matricula='$matricula'; ";
$resultado4 = mysqli_query($conn, $sql4) or die(mysqli_error($conn));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);

$cnh = $row4['numcnh'] ?? '';



if ($idmovida != '') {
	$sql5 = "SELECT dataInfra, descricao, endereco, cidade, uf, renavam FROM tbmovidamultas WHERE idmovida = '$idmovida' ; ";
} else {
	if ($idmulta == '') {
		$sql5 = "SELECT datainfracao, descricaoinfra, endereco, municipio, filial  FROM tbmulta WHERE placa='$placa' AND autoinfracao='$autoinfracao'";
	} else {
		$sql5 = "SELECT datainfracao, descricaoinfra, endereco, municipio, filial  FROM tbmulta WHERE idtbmulta='$idmulta'";
	}
}

$resultado5 = mysqli_query($conn, $sql5) or die(mysqli_error($conn));
$row5 = mysqli_fetch_array($resultado5, MYSQLI_BOTH);

// CORRIGIDO: Verificar se $row5 existe antes de acessar
$dataInfra = $row5[0] ?? '';
$descricao = isset($row5[1]) ? utf8_encode($row5[1]) : '';
$enderecoinfra = isset($row5[2]) ? utf8_encode($row5[2]) : '';
$cidadeinfra = isset($row5[3]) ? utf8_encode($row5[3]) : '';
$filial = $row5['filial'] ?? '';

$sql2 = "SELECT cnpj, Estado FROM bdcorp.tbfilial WHERE nome='$filial'; ";
$resultado2 = mysqli_query($conn, $sql2) or die(mysqli_error($conn));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
$cnpj = $row2['cnpj'] ?? '';
$estadofilial = $row2['Estado'] ?? '';

if ($filial == '') {
	$sql3 = "SELECT unidade FROM tbveiculo WHERE placa='$placa';";
	$resultado3 = mysqli_query($conn, $sql3) or die(mysqli_error($conn));
	$row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
	$estadofilial = $row3['unidade'] ?? '';
}

if ($estadofilial == 'SP') {
	$cidadeass = 'São Paulo';
} elseif ($estadofilial == 'RJ') {
	$cidadeass = 'Rio de Janeiro';
} elseif ($estadofilial == 'PR') {
	$cidadeass = 'Curitiba';
} else {
	$cidadeass = 'São Paulo'; // valor padrão
}

//tratando data
if (!empty($dataInfra)) {
	$dataInfra1 = explode(" ", $dataInfra);
	$dataInfra2 = explode("-", $dataInfra1[0]);
	$dataInfraf = $dataInfra2[2] . "/" . $dataInfra2[1] . "/" . $dataInfra2[0] . " " . ($dataInfra1[1] ?? '');
} else {
	$dataInfraf = 'Data não informada';
}

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10.5);

$pdf->Cell(10); //espaçamento a esquerda antes do texto começar

// CORRIGIDO: Texto principal com todas as variáveis tratadas
$texto = "\nPelo presente instrumento, eu $nome, portador(a) da Carteira Nacional de Habilitação (CNH) nº $cnh, da Cédula de Identidade RG n° $rg e inscrito(a) no CPF/MF sob o n° $cpf, residente e domiciliado na $endereco, $bairro - $cidade - $estado - CEP $cep, DECLARO, para os devidos fins de direito, ser o condutor do veículo de placa $placa - renavam $renavam - chassi $chassi, e único responsável pela infração de trânsito abaixo:\n\nAUTO INFRAÇÃO: $autoinfra   -    DATA HORA $dataInfraf \nDESCRIÇÃO: $descricao \nENDEREÇO INFRAÇÃO: $enderecoinfra - $cidadeinfra \n\nbem como nomeio e constituo como minha bastante procuradora a empresa Movida Locacao de Veiculos S.A., Sociedade Anonima Fechada, inscrita no CNPJ sob o nº 07976147002295, com sede na Av Bias Fortes, 704, Lourdes, Município de Belo Horizonte, Estado de Minas Gerais, CEP: 30170-011, legítima proprietária do veículo acima descrito, para que em meu nome assine o \"Termo de Apresentação do Condutor Infrator\", relativo à multa acima descrita nos termos do artigo 257, parágrafo 7° do Código de Trânsito Brasileiro e a resolução do Contran n° 149, de 19 de setembro de 2009.\n\nDECLARO, ainda, ciência quanto aos termos do artigo 257, parágrafo 8° do Código de Trânsito Brasileiro, que prevê que em caso de não identificação do condutor infrator e, em sendo o veículo de propriedade de pessoa jurídica, será lavrada nova multa, cujo valor será o da multa originária multiplicada pelo número de infrações iguais, cometidas no período subsequente de 12 (doze) meses, bem como ser responsável pelas referidas multas até a formalização de minha indicação como condutor infrator junto ao órgão de trânsito responsável. \n\n $cidadeass, $dia de $mesd de $ano.\n\n\n\n\n\n____________________________________________________________\n$nome \n\n\n";

$pdf->MultiCell(0, 6, utf8_decode($texto), 0, 'J');

$pdf->Output();
?>