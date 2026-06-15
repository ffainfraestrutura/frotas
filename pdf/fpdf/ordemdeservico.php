<?php
date_default_timezone_set('America/Sao_Paulo');

if (!function_exists('get_magic_quotes_runtime')) {
	function get_magic_quotes_runtime(): bool
	{
		return false;
	}
}

require_once __DIR__ . '/fpdf.php';

if (function_exists('mysqli_report')) {
	mysqli_report(MYSQLI_REPORT_OFF);
}

ob_start();
require_once __DIR__ . '/../../control/conecta.php';
$connectionOutput = ob_get_clean();

$connection = null;
if (isset($conn) && $conn instanceof mysqli) {
	$connection = $conn;
} elseif (isset($con) && $con instanceof mysqli) {
	$connection = $con;
}

$database = (isset($databaseName) && preg_match('/^[A-Za-z0-9_]+$/', (string) $databaseName))
	? (string) $databaseName
	: 'bdautofrotas';

$id = (int) (
	$_POST['num']
	?? $_GET['num']
	?? $_POST['numero']
	?? $_GET['numero']
	?? $_POST['idtbmanprev']
	?? $_GET['idtbmanprev']
	?? 0
);

if (!$connection instanceof mysqli) {
	http_response_code(500);
	exit('Erro ao conectar ao banco de dados.');
}
unset($connectionOutput);


if ($id <= 0) {
	http_response_code(400);
	exit('Ordem de serviço não informada.');
}

function buscarLinha(mysqli $connection, string $sql, string $types = '', array $params = []): ?array
{
	$stmt = mysqli_prepare($connection, $sql);
	if (!$stmt) {
		return null;
	}

	if ($types !== '' && $params !== []) {
		$refs = [];
		foreach ($params as $index => $value) {
			$refs[$index] = &$params[$index];
		}
		mysqli_stmt_bind_param($stmt, $types, ...$refs);
	}

	if (!mysqli_stmt_execute($stmt)) {
		mysqli_stmt_close($stmt);
		return null;
	}

	$result = mysqli_stmt_get_result($stmt);
	$row = $result ? mysqli_fetch_assoc($result) : null;
	mysqli_stmt_close($stmt);

	return $row ?: null;
}

function campo(array $row, string $name): string
{
	return (string) ($row[$name] ?? '');
}

function textoPdf(?string $value): string
{
	return utf8_encode((string) $value);
}

function formatarDataPdf(?string $value): string
{
	if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
		return '';
	}

	$date = date_create($value);

	return $date ? date_format($date, 'd/m/Y') : (string) $value;
}

$row = buscarLinha(
	$connection,
	"SELECT * FROM `{$database}`.`tbmanprev` WHERE idtbmanprev = ? LIMIT 1",
	'i',
	[$id]
);

if (!$row) {
	http_response_code(404);
	exit('Ordem de serviço não encontrada.');
}

$id = campo($row, 'idtbmanprev');
$protocolo = campo($row, 'protocolo');
$placa = campo($row, 'placa');
$data = campo($row, 'data');
$tipo = campo($row, 'tipo');
$hodometro = campo($row, 'hodometro');
$statusveic = campo($row, 'statusveic');
$atualizadoem = campo($row, 'atualizadoem');
$solicitante = textoPdf(campo($row, 'solicitante'));
$status = textoPdf(campo($row, 'status'));
$etapa = textoPdf(campo($row, 'etapa'));
$dataocorrencia = campo($row, 'dataocorrencia');
$modelo = campo($row, 'modelo');
$km = campo($row, 'Km');
$fornman = campo($row, 'fornman');
$descricao = textoPdf(campo($row, 'descricao'));
$ccusto = textoPdf(campo($row, 'ccusto'));
$condutor = campo($row, 'condutor');
$oficina = textoPdf(campo($row, 'oficina'));
$dataagendamento = campo($row, 'dataagendamento');
$prevsaida = campo($row, 'prevsaida');
$dataentrada = campo($row, 'dataentrada');
$dataretirada = campo($row, 'dataretirada');
$tipopagamento = textoPdf(campo($row, 'tipopagamento'));
$reembolsoaprov = campo($row, 'reembolsoaprov');
$valorreembolso = campo($row, 'valorreembolso');
$valoroficina = campo($row, 'valoroficina');
$valordesconto = campo($row, 'valordesconto');
$valormaoobra = campo($row, 'valormaoobra');
$valormaterial = campo($row, 'valormaterial');
$valortransp = campo($row, 'valortransp');
$outrosvalor = campo($row, 'outrosvalor');
$descontarcond = campo($row, 'descontarcond');
$datavencimento = campo($row, 'datavencimento');
$datapagamento = campo($row, 'datapagamento');
$formapagam = textoPdf(campo($row, 'formapagam'));
$condicaopag = textoPdf(campo($row, 'condicaopag'));
$numparc = campo($row, 'numparc');
$valorparcela = campo($row, 'valorparcela');
$dataprimparc = campo($row, 'dataprimparc');
$dataconclusao = campo($row, 'dataconclusao');
$placaanterior = campo($row, 'placaanterior');
$observacao = textoPdf(campo($row, 'observacao'));
$telefone = campo($row, 'telefone');
$tipocolab = campo($row, 'tipocolab');
$numatend = campo($row, 'numatend');
$numcotacao = campo($row, 'numcotacao');
$numpedido = campo($row, 'numpedido');
$numservico = campo($row, 'numservico');

$row2 = $condutor !== ''
	? buscarLinha(
		$connection,
		"SELECT nome, codempresa, codfilial, cargo, status FROM bdautofrotas.tbfuncionario WHERE matricula = ? LIMIT 1",
		's',
		[$condutor]
	)
	: null;
$nome = textoPdf(campo($row2 ?? [], 'nome'));
$codempresa = campo($row2 ?? [], 'codempresa');
$codfilial = campo($row2 ?? [], 'codfilial');
$cargo = textoPdf(campo($row2 ?? [], 'cargo'));
$statusaniel = textoPdf(campo($row2 ?? [], 'status'));
$telefone = $telefone !== '' ? $telefone : campo($row2 ?? [], 'telefone');
$tipocolab = $tipocolab !== '' ? $tipocolab : campo($row2 ?? [], 'tipocolab');

$row3 = ($codempresa !== '' && $codfilial !== '')
	? buscarLinha(
		$connection,
		"SELECT nome FROM bdautofrotas.tbfilial WHERE codempresa = ? AND codfilial = ? LIMIT 1",
		'ss',
		[$codempresa, $codfilial]
	)
	: null;
$filial = textoPdf(campo($row3 ?? [], 'nome'));

$row4 = $placa !== ''
	? buscarLinha(
		$connection,
		"SELECT tipoposse, idlocador, status FROM `{$database}`.`tbveiculo` WHERE placa = ? LIMIT 1",
		's',
		[$placa]
	)
	: null;
$propriedade = textoPdf(campo($row4 ?? [], 'tipoposse'));
$idlocador = campo($row4 ?? [], 'idlocador');
$statusveic = campo($row4 ?? [], 'status');

$row5 = $idlocador !== ''
	? buscarLinha(
		$connection,
		"SELECT fantasia FROM `{$database}`.`tbfornecedor` WHERE idtbfornecedor = ? LIMIT 1",
		's',
		[$idlocador]
	)
	: null;
$fantasia = textoPdf(campo($row5 ?? [], 'fantasia'));

if ($statusveic == '1') {
	$statusf = 'ATIVO';
} elseif ($statusveic == '0') {
	$statusf = 'INATIVO';
} else {
	$statusf = '';
}

//tratando valores
/*if(strpos($valorreembolso) !== false){
	$valorreembolso =str_replace('.', ',', $valorreembolso);
}

if(strpos($valoroficina) !== false){
	$valoroficina =str_replace('.', ',', $valoroficina);
}

if(strpos($valordesconto) !== false){
	$valordesconto =str_replace('.', ',', $valordesconto);
}

if(strpos($valormaoobra) !== false){
	$valormaoobra =str_replace('.', ',', $valormaoobra);
}

if(strpos($valortransp) !== false){
	$valortransp =str_replace('.', ',', $valortransp);
}

if(strpos($valormaterial) !== false){
	$valormaterial =str_replace('.', ',', $valormaterial);
}

if(strpos($outrosvalor) !== false){
	$outrosvalor =str_replace('.', ',', $outrosvalor);
}*/

$data1 = explode(" ", $data);
$horaf = $data1[1] ?? '';
$dataf = formatarDataPdf($data);
$dataagf = formatarDataPdf($dataagendamento);

function localizarLogoOrdemServico(): ?string
{
	$candidatos = [
		__DIR__ . '/logo.jpg',
		__DIR__ . '/logo.jpeg',
		__DIR__ . '/logo.png',
		dirname(__DIR__) . '/logo.jpg',
		dirname(__DIR__) . '/logo.jpeg',
		dirname(__DIR__) . '/logo.png',
	];

	foreach ($candidatos as $arquivo) {
		if (is_file($arquivo) && is_readable($arquivo)) {
			return $arquivo;
		}
	}

	return null;
}


class PDF extends FPDF
{
function __construct()
{
	// FPDF 1.7 usa construtor legado; em PHP 8+ ele nao e chamado automaticamente.
	$this->FPDF('P', 'mm', 'A4');
}

// Page header
function Header()
{
	$logoPath = localizarLogoOrdemServico();
	if ($logoPath !== null) {
		$larguraLogo = 36;
		$larguraPagina = 210; // A4 retrato em mm
		$posicaoX = ($larguraPagina - $larguraLogo) / 2;
		$this->Image($logoPath, $posicaoX, 6, $larguraLogo);
	}
	// Arial bold 15
	$this->SetFont('Arial','B',12);
	// Cor de fundo
	$this->SetFillColor(215);
	// Line break
	$this->Ln(22);
	// Move to the right
	$this->Cell(20);
	// Title
	$this->Cell(155,10,utf8_decode('ORDEM DE SERVIÇO'),1,1,'C','true');
	// Line break
	//$this->Ln(1);
}

}

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

$pdf->SetFont('Times','B',11.5);
$pdf->Cell(20);
$pdf->MultiCell(155,5,utf8_decode("DADOS SOLICITAÇÃO"),1,'C');

$pdf->SetFont('Times','',11.5);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("NÚMERO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$id"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("PROTOCOLO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$protocolo"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("DATA ABERTURA"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode(trim("$dataf $horaf")),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("ABERTO POR"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$solicitante"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("UNIDADE"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$filial"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("CENTRO CUSTO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$ccusto"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("KM ABERTURA"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$hodometro"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("STATUS"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$status"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("ETAPA"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$etapa"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("FORNECEDOR"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$fornman"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,10,utf8_decode("DESCRIÇÃO INICIAL"),1,1);

$pdf->Ln(-20);
$pdf->Cell(60);
$pdf->MultiCell(115,20,utf8_decode("$descricao"),1,1);

$pdf->Ln(5);
$pdf->SetFont('Times','B',11.5);
$pdf->Cell(20);
$pdf->MultiCell(155,5,utf8_decode("DADOS VEÍCULO"),1,'C');

$pdf->SetFont('Times','',11.5);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("PLACA"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$placa"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("MODELO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$modelo"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("PROPRIEDADE"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$propriedade"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("LOCADORA"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$fantasia"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("STATUS"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$statusf"),1,1);

$pdf->Ln(5);
$pdf->SetFont('Times','B',11.5);
$pdf->Cell(20);
$pdf->MultiCell(155,5,utf8_decode("DADOS CONDUTOR"),1,'C');

$pdf->SetFont('Times','',11.5);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("CONDUTOR"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$nome"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("MATRÍCULA"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$condutor"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("CARGO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$cargo"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("TELEFONE"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$telefone"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("TIPO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$tipocolab"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("STATUS"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$statusaniel"),1,1);

$pdf->Ln(5);
$pdf->SetFont('Times','B',11.5);
$pdf->Cell(20);
$pdf->MultiCell(155,5,utf8_decode("DADOS PEDIDO"),1,'C');

$pdf->SetFont('Times','',11.5);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("Nº ATENDIMENTO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$numatend"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("Nº COTAÇÃO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$numcotacao"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("Nº PEDIDO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$numpedido"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("Nº PED SERVIÇO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$numservico"),1,1);

$pdf->Ln(5);
$pdf->SetFont('Times','B',11.5);
$pdf->Cell(20);
$pdf->MultiCell(155,5,utf8_decode("VALORES"),1,'C');

$pdf->SetFont('Times','',11.5);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("FORNECEDOR"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$fornman"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("OFICINA"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$oficina"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("AGENDAMENTO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$dataagf"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("TIPO ORÇAMENTO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$tipopagamento"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("VALOR TOTAL"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$valoroficina"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("VAL M.DE OBRA"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$valormaoobra"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("VALOR MATERIAL"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$valormaterial"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("VAL TRANSPORTE"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$valortransp"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("VALOR OUTROS"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$outrosvalor"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("VAL DESCONTO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$valordesconto"),1,1);

$pdf->Cell(20);
$pdf->MultiCell(40,5,utf8_decode("VAL REEMBOLSO"),1,1);

$pdf->Ln(-5);
$pdf->Cell(60);
$pdf->MultiCell(115,5,utf8_decode("$valorreembolso"),1,1);

$pdf->Output();


?>