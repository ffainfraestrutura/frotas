<?php
require'fpdf.php';
require('../../conecta.php');
require('../../conecta2.php');
header("Content-type: text/html; charset=utf-8");

$id=$_POST['id'];
//print $id;

//echo $id;

$sql = "SELECT * FROM bdfrota.tbmovidatramite where idtbmovidatramite = '$id';";
$resultado = mysqli_query($conexao, $sql) or die(mysqli_error($conexao));
$row = mysqli_fetch_array($resultado, MYSQLI_BOTH);
	$matricula= $row['matricula'];
	$placa=$row['placa'];
	$autoinfra=$row['autoinfra'];

$hoje = date('Y-m-d');
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
    	$mesd='';
}




$sql2 = "SELECT nome, rg, cpf, endereco, bairro, cidade, estado, cep, nome FROM bdcorp.tbfuncionario WHERE matricula = '$matricula'; ";
$resultado2 = mysqli_query($conexao, $sql2) or die(mysqli_error($conexao));
$row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
	$nome = $row2['nome'];
	$rg = $row2['rg'];
	$cpf = $row2['cpf'];
	$endereco = utf8_encode($row2['endereco']);
	$bairro = utf8_encode($row2['bairro']);
	$cidade = utf8_encode($row2['cidade']);
	$estado = $row2['estado'];
	$numres = $row2['numres'];
	$cep = $row2['cep'];

$sql4 = "SELECT numcnh FROM bdfrota.tbcnh WHERE matricula='$matricula'; ";
$resultado4 =  mysqli_query($conexao, $sql4) or die(mysqli_error($conexao));
$row4 = mysqli_fetch_array($resultado4, MYSQLI_BOTH);
	$numcnh = $row4['numcnh'];

$sql5 = "SELECT telefone FROM bdfrota.tbusuario WHERE matricula='$matricula'; ";
$resultado5 =  mysqli_query($conexao, $sql5) or die(mysqli_error($conexao));
$row5 = mysqli_fetch_array($resultado5, MYSQLI_BOTH);
	$telefonecond = $row5['telefone'];


class PDF extends FPDF
{
// Page header
function Header()
	{
		// Logo
		$this->Image('../../src/images/logo-detran.png',20,5,45,15);
		// Arial bold (negrito) 11
		$this->SetFont('Arial','B',11);
		// Cor de fundo
		$this->SetFillColor(255);
		// Line break (quebra de linha)
		$this->Ln(-5);
		// Move to the right - espaçamento antes do texto começar
		$this->Cell(55);
		// Título
		$this->MultiCell(0,14.8, utf8_decode('REQUERIMENTO PARA TROCA DE REAL INFRATOR'),1,1,'R','false');
		// Line break (quebra de linha)
		//$this->Ln(2);
	}

}

// Instanciation of inherited class
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->MultiCell(0,3,utf8_decode("Este formulário se aplica quando o notificado é o proprietário, mas outra pessoa dirigia o veículo no momento da infração. Não sendo V.S.ª o condutor do veículo no momento da autuação, será concedido o prazo de 15 (quinze) dias, contados a partir do recebimento da notificação da autuação (Art. 257, § 7º do CTB) para apresentação do real infrator. Não havendo identificação neste prazo ou se a identificação for feita em desacordo com o estabelecido, o proprietário do veículo será considerado responsável pela infração cometida, de acordo com o art. 6º da Resolução CONTRAN Nº 619/2016."),0, 'J');

$pdf->Ln(1);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->Cell(0,6,utf8_decode("Dados do Requerente/Proprietário"), 1, 'L', 'J', true);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,6,utf8_decode("Nome: $nome"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(0,6,utf8_decode("Nome Social:"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(120,6,utf8_decode("Documento de identidade: $rg"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Órgão expedidor: "),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(120,6,utf8_decode("CPF/CNPJ: $cpf"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("CNH: $numcnh"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(120,6,utf8_decode("Nacionalidade:"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Naturalidade:"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(0,6,utf8_decode("Endereço: $endereco"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(40,6,utf8_decode("Nº:"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(80,6,utf8_decode("Complemento:"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("CEP: $cep"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(40,6,utf8_decode("UF: $uf"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(80,6,utf8_decode("Cidade: $cidade"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Bairro: $bairro"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(120,6,utf8_decode("Telefone: ( ) "),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Celular: $telefonecond"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(0,6,utf8_decode("E-mail: "),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(0,6,utf8_decode("Dados do Real Infrator/Condutor"), 1, 'L', 'J', true);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->SetFont('Arial','',8);
$pdf->Cell(0,6,utf8_decode("Nome: $nome"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(0,6,utf8_decode("Nome Social:"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(120,6,utf8_decode("Documento de identidade: $rg"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Órgão expedidor:"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(120,6,utf8_decode("CPF/CNPJ: $cpf"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("CNH: $numcnh"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(120,6,utf8_decode("Nacionalidade:"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Naturalidade:"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(0,6,utf8_decode("Endereço: $endereco"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(40,6,utf8_decode("Nº: $numres"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(80,6,utf8_decode("Complemento:"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("CEP: $cep"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(40,6,utf8_decode("UF: $estado"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(80,6,utf8_decode("Cidade: $cidade"),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Bairro: $bairro"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(120,6,utf8_decode("Telefone: ( ) "),1, 'J', false);
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Celular: $telefonecond"),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->Cell(0,6,utf8_decode("E-mail: "),1, 'J', false);

$pdf->Ln(6);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(0,6,utf8_decode("Declaro ainda, estar ciente de que a falsidade da presente declaração pode implicar na sanção prevista no art. 299 do Código Penal."),0, 'L', 'J', true);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->SetFont('Arial','',8);
$pdf->Cell(140,6,utf8_decode("Auto de Infração:  $autoinfra"),1, 'J', 'false');
$pdf->Cell(0.1);
$pdf->Cell(0,6,utf8_decode("Placa: $placa"),1, 'J', 'false');

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->MultiCell(0,7,utf8_decode("            Rio de Janeiro, $dia/$mesd/$ano\n    ________________________________________________             __________________________________________ \n              Assinatura do requerente/proprietário                                                          Assinatura do real infrator/condutor"),1, 'J', false);

$pdf->Ln(0);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->SetFont('Arial','B',8);
$pdf->Cell(0,6,utf8_decode("Documentos necessários:"), 1, 'L', 'J', true);

$pdf->Ln(6);
$pdf->Cell(10);
$pdf->SetFont('Arial','',8);
$pdf->MultiCell(0,4,utf8_decode("- CNH ou permissão do proprietário e do real infrator. O proprietário (ou seu representante legal), quando não habilitado, deverá apresentar documento de identidade e CPF;\n - Comprovante de residência do real infrator;\n - A representação legal do requerente poderá ser realizada por procuração simples para o Advogado, acompanhada da Carteira da Ordem dos Advogados do Brasil – OAB ou por procuração acompanhada da cópia da identidade do representante; \n - Quando o proprietário notificado for pessoa jurídica, deverá apresentar cópia do CNPJ na validade, dos documentos constitutivos da empresa e dos documentos de identidade e CPF do sócio/representante que solicita o serviço;\n - Original ou cópia da Notificação da Autuação, ou do Auto de Infração;\n - A indicação do condutor infrator somente será acatada e produzirá efeitos legais se o formulário de identificação do condutor estiver corretamente preenchido, sem rasuras, com assinaturas originais do condutor e do proprietário do veículo ou de seu representante legal;\n - Ficam o proprietário e o real infrator responsáveis penal, cível e administrativamente, pela veracidade das informações aqui prestadas e dos documentos fornecidos;\n - Sendo o veículo de propriedade de pessoa jurídica e não havendo a identificação do condutor infrator até o término do prazo fixado na notificação da autuação ou se a identificação for feita em desacordo com o estabelecido, será imposta multa, nos termos do § 8º do art. 257 do CTB, expedindo-se a notificação desta ao proprietário do veículo."),1, 'J', false);

$pdf->Ln(0);
$pdf->Cell(10); //espaçamento a esquerda antes do texto começar
$pdf->SetFillColor(159);
$pdf->SetFont('Arial','B',8);
$pdf->MultiCell(0,4,utf8_decode("Obs.: O usuário deve portar os documentos originais para confronto com as cópias apresentadas podendo ser solicitado a qualquer momento pela diretoria responsável."),1, 'J', 'true');

$pdf->Output();
?>

