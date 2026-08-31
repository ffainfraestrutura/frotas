<?php
date_default_timezone_set('America/Sao_Paulo');
include_once"../../func/log4.php";
include("../../conecta03.php");

$hoje= date('Y-m-d H:i:s');

$idtbveiculo = $_POST['idtbveiculo'];
$placa = strtoupper($_POST['placa'] ?? '');
$uf = $_POST['uf'] ?? '';
$aplicacaofrota = $_POST['aplicacaofrota'] ?? '';
$marca = $_POST['marca'] ?? '';
$modelo = $_POST['modelo'] ?? '';
$versao = $_POST['versao'] ?? '';

/*$categoria = utf8_decode($_POST['categoria']);
$tipoveic = utf8_decode($_POST['tipoveic']);
$cor = utf8_decode($_POST['cor']);*/

$categoria = $_POST['categoria'] ?? '';
$tipoveic = $_POST['tipoveic'] ?? '';
$cor = $_POST['cor'] ?? '';

$zerokm = $_POST['zerokm'] ?? '';
$anofabric = $_POST['anofabric'] ?? '';
$anomodelo = $_POST['anomodelo'] ?? '';
$velmax = $_POST['velmax'] ?? '';
$renavam = $_POST['renavam'] ?? '';
$chassi = $_POST['chassi'] ?? '';
$nummotor = $_POST['nummotor'] ?? '';

//$combustivel = utf8_decode($_POST['combustivel']);
$combustivel = $_POST['combustivel'] ?? '';

$tanque = $_POST['tanque'] ?? '';
$motorizacao = $_POST['motorizacao'] ?? '';
$nportas = $_POST['nportas'] ?? '';
$npassageiros = $_POST['npassageiros'] ?? '';
$calibragem = $_POST['calibragem'] ?? '';
$aro = $_POST['aro'] ?? '';
$qtdpneus = $_POST['qtdpneus'] ?? '';
$qtdestepes = $_POST['qtdestepes'] ?? '';
$qtdeixos = $_POST['qtdeixos'] ?? '';
$gnv = $_POST['gnv'] ?? '';
$gps = $_POST['gps'] ?? '';
$tagpedagio = $_POST['tagpedagio'] ?? '';
$status = $_POST['status'] ?? '1';
$hodometro = $_POST['hodometro'] ?? '';

//$tipoposse = utf8_decode($_POST['tipoposse']);
$tipoposse = $_POST['tipoposse'] ?? '';

$locador = $_POST['locador'] ?? '';
$matr_autor = $_POST['matr_autor'] ?? '';
$hodometroinicial = $_POST['hodometroinicial'] ?? '';

$obsveiculo = $_POST['obsveiculo'] ?? '';
$statusvel = $_POST['statusvel'] ?? 1;

// Combinar data e hora de movimentação em um único campo
$datamov_date = $_POST['datamovimentacao'] ?? date('Y-m-d');
$horamov_time = $_POST['horamovimentacao'] ?? '00:00:00';
$datamovimentacao = $datamov_date . ' ' . $horamov_time;
$matcond = $_POST['matcond'] ?? '';

//$oficina = utf8_decode($_POST['oficina']);
$oficina = $_POST['oficina'] ?? '';

$dtentrega = $_POST['dtentrega'] ?? '';
$dtdevolucao = $_POST['dtdevolucao'] ?? '';

/*$tipovel = utf8_decode($_POST['tipovel']);
$situacao = utf8_decode($_POST['situacao']);
$doccrlv = utf8_decode($_POST['doccrlv']);*/

$tipovel = $_POST['tipovel'] ?? '';
$situacao = $_POST['situacao'] ?? '';
$doccrlv = $_POST['doccrlv'] ?? '';

$airbag = $_POST['airbag'] ?? '';

//$gpsemp = utf8_decode($_POST['gpsemp']);
$gpsemp = $_POST['gpsemp'] ?? '';

$rack = $_POST['rack'] ?? '';

//$ncontloc = utf8_decode($_POST['ncontloc']);
$ncontloc = $_POST['ncontloc'] ?? '';

$dtdisponivelloc = $_POST['dtdisponivelloc'] ?? '';
$dtdevolucaoloc = $_POST['dtdevolucaoloc'] ?? '';
$valaquisicao = $_POST['valaquisicao'] ?? '';
$totkmmensal = $_POST['totkmmensal'] ?? '';

//$blindagem = utf8_decode($_POST['blindagem']);
$blindagem = $_POST['blindagem'] ?? '';

$baseffa = $_POST['baseffa'] ?? '';
$basegestao = $_POST['basegestao'] ?? '';
$centrocusto = $_POST['centrocusto'] ?? '';
$unidade = $_POST['unidade'] ?? '';

$dttermo = $_POST['dttermo'] ?? '';
$dtdisp = $_POST['dtdisp'] ?? '';
$dir_finald1 = '';

if($status==0){
  $matcond = '';
}

/*if($statusvel == '5' || $statusvel== '18' || $statusvel== '2' || $statusvel== '3' || $statusvel== '6' || $statusvel== '7' || $statusvel== '8' || $statusvel== '9' || $statusvel== '10' || $statusvel== '11' || $statusvel== '12' || $statusvel== '13' || $statusvel== '15' || $statusvel== '19' || $statusvel== '23' || $statusvel== '34' || $statusvel== '36' || $statusvel== '44') {
  $matcond = '';
}*/

/*if($statusvel == '32' && $matcond !=''){
  echo "<script language='javascript' type='text/javascript'> alert('Para status de Veículo INDISPONÍVEL, o campo matrícula do condutor não pode estar preenchido. Por favor, refaça o processo e se atente ao campo matrícula de condutor.');window.close(); </script>";
}else{*/

  if (!empty($_FILES['doc01']['tmp_name']) && is_uploaded_file($_FILES['doc01']['tmp_name'])) {

    $dir1 = '/docs/';
    
    $_UP['pasta'] = '../docs/';
    $_UP['tamanho'] = 1024 * 1024 * 32; // 32Mb
    $_UP['extensoes'] = array('jpg', 'jpeg', 'png', 'gif', 'pdf');
    $_UP['renomeia'] = false;
    
    $_UP['erros'][0] = 'Não houve erro';
    $_UP['erros'][1] = 'O arquivo no upload é maior do que o limite do PHP';
    $_UP['erros'][2] = 'O arquivo ultrapassa o limite de tamanho especifiado no HTML';
    $_UP['erros'][3] = 'O upload do arquivo foi feito parcialmente';
    $_UP['erros'][4] = 'Não foi feito o upload do arquivo';
     
    
    if ($_FILES['doc01']['error'] != 0) {
      die("Não foi possível fazer o upload, erro:<br />" . $_UP['erros'][$_FILES['doc01']['error']]);
      exit; // Para a execução do script
    }

    $nomeArquivo = $_FILES['doc01']['name'];
    $partesNomeArquivo = explode('.', $nomeArquivo);
    $extensao = strtolower(end($partesNomeArquivo));
    if (array_search($extensao, $_UP['extensoes']) === false) {
      echo "Por favor, envie arquivos com as seguintes extensões: jpg, png, pdf ou gif";
    }else if ($_UP['tamanho'] < $_FILES['doc01']['size']) {
      echo "O arquivo enviado é muito grande, envie arquivos de até 32Mb.";
    }else {
      
      if ($_UP['renomeia'] == true) {
      
        $nome_final = time().'.jpg';
      } else {
      
        $nomeaux = explode(".", $_FILES['doc01']['name']);

        $nome_final = $placa."docbo.".$nomeaux[1];
        $dir_finald1 = $dir1.$nome_final;
      }

      if (move_uploaded_file($_FILES['doc01']['tmp_name'], $_UP['pasta'] . $nome_final)) {

      } else {
      // Não foi possível fazer o upload, provavelmente a pasta está incorreta
      echo "<script>alert('erro no envio do documento 01');</script>";
      echo "<script>window.close();\"</script>";
      }  
    }
  }

  $dtmovimentacao = $datamovimentacao;

  //tratando valores
  $partesValorAquisicao = explode(" ", (string) $valaquisicao);
  $valorAquisicaoBruto = $partesValorAquisicao[1] ?? $partesValorAquisicao[0] ?? '0';
  $valaquisicao = str_replace(".", "", $valorAquisicaoBruto);
  $valaquisicao = str_replace(",", ".", $valaquisicao);

  if(strpos($placa, "-") !== false){
    $placa = str_replace("-", "", $placa);
  } else {
    $placa = str_replace(" ", "", $placa);
  }

  if(strpos($hodometro, ".") !== false){
    $hodometro = str_replace(".", "", $hodometro);
  }

  $sql1 = "UPDATE bdfrota.tbveiculo SET placa = '$placa', uf = '$uf', aplicacao = '$aplicacaofrota', marca = '$marca', modelo='$modelo', versao='$versao', categoria ='$categoria', tipo='$tipoveic', cor='$cor', zerokm='$zerokm', anofabr='$anofabric', anomodelo='$anomodelo', velocmax='$velmax', renavam='$renavam', chassi='$chassi', nummotor='$nummotor', combustivel='$combustivel', tanque='$tanque', motorizacao='$motorizacao', nportas='$nportas', npassageiros='$npassageiros', calibragem= '$calibragem', aro='$aro', qtdpneus='$qtdpneus', qtdestepe='$qtdestepes', qtdeixos= '$qtdeixos', gnv='$gnv', gps='$gps',tagpedagio = '$tagpedagio', status='$status', hodometro = '$hodometro', tipoposse='$tipoposse', idlocador='$locador', hodometroinicial = '$hodometroinicial', statusvel = '$statusvel', obsveiculo = '$obsveiculo', datamovimentacao = '$dtmovimentacao',  oficina = '$oficina', dtentrega = '$dtentrega', dtdevolucao = '$dtdevolucao', tipovel = '$tipovel', situacao = '$situacao', doccrlv = '$doccrlv', airbag = '$airbag', gpsemp = '$gpsemp', rack = '$rack', ncontloc = '$ncontloc', dtdisponivelloc = '$dtdisponivelloc', dtdevolucaoloc = '$dtdevolucaoloc', valaquisicao = '$valaquisicao', blindagem = '$blindagem', bo = '$dir_finald1', baseffa='$baseffa', basegestao='$basegestao', ccusto='$centrocusto', dttermodisp='$dttermo',dtdesmobilizacao='$dtdisp', unidade='$unidade' where idtbveiculo = '$idtbveiculo';";

  $resultado1 = mysqli_query($con, $sql1) or die(mysqli_error($con));;

  /*if($matcond != ''){
    $sql3="SELECT nome, codempresa, codfilial, ccusto FROM bdaniel.tbfuncionario WHERE matricula='$matcond' ";
    $resultado3 = mysqli_query($con, $sql3) or die(mysqli_error($con));
    $row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
      $nomecond = $row3['nome'];
      $codempresa = $row3['codempresa'];
      $codfilial = $row3['codfilial'];
      $ccustofunc = $row3['ccusto'];

    $sql2="SELECT * FROM bdfrota.tbcondutor WHERE placaassoc='$placa' AND ativo='1'; ";
    $resultado2 = mysqli_query($con, $sql2) or die(mysqli_error($con));
    $linhas2 = mysqli_num_rows($resultado2);
    $row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
      $id = $row2['idtbcondutor'];
    
    if($linhas2<=0){

      $sql3="INSERT INTO bdfrota.tbcondutor (nome, matricula, ativo, placaassoc, dataassoc, statuscond) VALUES ('$nomecond', '$matcond', 1, '$placa','$hoje', 'COM VEICULO VINCULADO') ";
      $resultado3 = mysqli_query($con, $sql3) or die(mysqli_error($con));
    
    }else{//$datavistoriaf
      $sql3="UPDATE bdfrota.tbcondutor SET datadissoc ='$hoje', ativo = 0, statuscond='' WHERE idtbcondutor='$id'; ";
      $resultado3 = mysqli_query($con, $sql3) or die(mysqli_error($con));

      $sql5="INSERT INTO bdfrota.tbcondutor (nome, matricula, ativo, placaassoc, dataassoc, statuscond) VALUES ('$nomecond', '$matcond', 1, '$placa','$hoje', 'COM VEICULO VINCULADO') ";
      $resultado5 = mysqli_query($con, $sql5) or die(mysqli_error($con));
    }
    
  }else{
    $sql2="SELECT * FROM bdfrota.tbcondutor WHERE placaassoc='$placa' AND ativo='1'; ";
    $resultado2 = mysqli_query($con, $sql2) or die(mysqli_error($con));
    $linhas2 = mysqli_num_rows($resultado2);
    $row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
      $id = $row2['idtbcondutor'];
    
    if($linhas2<=0){
      
    
    }else{
      
      $sql3="UPDATE bdfrota.tbcondutor SET datadissoc ='$hoje', ativo = 0, statuscond='' WHERE idtbcondutor='$id'; ";
      $resultado3 = mysqli_query($con, $sql3) or die(mysqli_error($con));

    }

  }*/

  if($status==0){

    $sql2="SELECT * FROM bdfrota.tbcondutor WHERE placaassoc='$placa' AND ativo='1'; ";
    $resultado2 = mysqli_query($con, $sql2);
    if ($resultado2 && mysqli_num_rows($resultado2) > 0) {
      $row2 = mysqli_fetch_array($resultado2, MYSQLI_ASSOC);
      $id = $row2['idtbcondutor'];

      $sql3="UPDATE bdfrota.tbcondutor SET datadissoc ='$hoje', ativo = 0, statuscond='' WHERE idtbcondutor='$id'; ";
      
      // UPDATE
      $resultado3 = mysqli_query($con, $sql3);
      if (!$resultado3) {
        die("<script>alert('ERRO 5: Falha ao desativar condutor.\n\nDetalhes: " . addslashes(mysqli_error($con)) . "');</script>");
      }
    }
  }

  /*if($statusvel == 2){
    $sql2="SELECT max(idtbmanprev) AS idtbmanprev FROM bdfrota.tbmanprev WHERE placa='$placa';";
    $resultado2 = mysqli_query($con, $sql2) or die(mysqli_error($con));
    $row2 = mysqli_fetch_array($resultado2, MYSQLI_BOTH);
      $maxid = $row2['idtbmanprev'];

    //print "$sql2 - $maxid";

    $sql3="SELECT status FROM bdfrota.tbmanprev WHERE idtbmanprev='$maxid';";
    $resultado3 = mysqli_query($con, $sql3) or die(mysqli_error($con));
    $row3 = mysqli_fetch_array($resultado3, MYSQLI_BOTH);
      $statusman = $row3['status'];

    if($statusman=='ABERTO' || $statusman=='ABERTA'){
      $sql4="UPDATE bdfrota.tbmanprev SET status='CONCLUIDO' WHERE idtbmanprev='$maxid';";
      $resultado4 = mysqli_query($con, $sql4) or die(mysqli_error($con));
    }

  }*/

  $log = new Log($con);
  //enviarlognovo($datahora, $acao, $matricula, $mat_autor, $tipo, $placa)
  $acao='Editou veiculo';
  $tipo = 'cadastro';
  $logresult = $log->enviarlognovo($hoje, $acao, $matcond, $matr_autor, $tipo, $placa);
  //print "$hoje, $acao, $matricula, $matr_autor, $tipo, $placa";*/

  $debug_file = fopen('/tmp/debug_veiculo.txt', 'a');
  fwrite($debug_file, "Chegou ao final com sucesso! Salvando dados.\n");
  fclose($debug_file);

  echo "<script language='javascript' type='text/javascript'> alert('Cadastro de veículo editado com sucesso.');window.close(); </script>";

  //window.location=\"../listagemveiculos.php\"*/
//}
?>
