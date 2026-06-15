<?php
date_default_timezone_set('America/Sao_Paulo');

// Defina o tempo de vida da sessão para 1 hora
$lifetime = 3600; // 1 hora em segundos

// Configurar os parâmetros do cookie da sessão
session_set_cookie_params($lifetime);

// Iniciar a sessão
session_start();

// Tentar obter dados da sessão, se vazios, usar parâmetros GET
$nome1 = isset($_SESSION['nome']) ? $_SESSION['nome'] : (isset($_GET['nome']) ? $_GET['nome'] : '');
$usuariof = isset($_SESSION['usuario']) ? $_SESSION['usuario'] : (isset($_GET['usuario']) ? $_GET['usuario'] : '');
$matricula1 = isset($_SESSION['matricula']) ? $_SESSION['matricula'] : (isset($_GET['mat']) ? $_GET['mat'] : '');
$perfil = isset($_SESSION['perfil']) ? $_SESSION['perfil'] : (isset($_GET['perfil']) ? $_GET['perfil'] : '');
$tipo = isset($_SESSION['tipo']) ? $_SESSION['tipo'] : (isset($_GET['tipo']) ? $_GET['tipo'] : '');

// Se os dados ainda estão vazios e não estamos em modo iframe, redirecionar
if (($perfil == null || $perfil == '') && !isset($_GET['from_modal'])) {
  echo "<script language='javascript' type='text/javascript'>alert('Você deve logar para ter acesso');window.location='../index.php'</script>";
  exit;
}

// Atualizar a sessão com os valores obtidos
$_SESSION['perfil'] = $perfil;
$_SESSION['matricula'] = $matricula1;
$_SESSION['nome'] = $nome1;
$_SESSION['usuario'] = $usuariof;
$_SESSION['tipo'] = $tipo;

require_once __DIR__ . "/../includes/autofrota_common.php";
$autofrotaSessao = autofrotaInit();
$con = $autofrotaSessao["conn"] ?? null;
$databaseName = (string) ($autofrotaSessao["databaseName"] ?? "bdautofrotas");
//include"conecta.php";
//include_once"./func/log.php";
header("Content-type: text/html; charset=utf-8");
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <meta name="description" content="FFA" />
  <meta name="author" content="FFA" />
  <link rel="icon" type="image/png" href="../src/images/favicon.png" />

  <title> Import de Multas </title>

  <link href="../src/css/styles.css" rel="stylesheet" />
  <script src="https://use.fontawesome.com/releases/v6.1.0/js/all.js" crossorigin="anonymous"></script>

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    /* Cores do sistema - preto */
    :root {
      --primary-color: #000000;
      --primary-hover: #333333;
    }

    /* Override da cor primária do Bootstrap */
    .bg-primary-custom {
      background-color: var(--primary-color) !important;
    }

    .text-primary-custom {
      color: var(--primary-color) !important;
    }

    .btn-primary-custom {
      background-color: var(--primary-color) !important;
      border-color: var(--primary-color) !important;
      color: white !important;
    }

    .btn-primary-custom:hover {
      background-color: var(--primary-hover) !important;
      border-color: var(--primary-hover) !important;
    }

    .btn-outline-primary-custom {
      color: var(--primary-color) !important;
      border-color: var(--primary-color) !important;
    }

    .btn-outline-primary-custom:hover {
      background-color: var(--primary-color) !important;
      color: white !important;
    }

    /* Lista ordenada customizada */
    ol {
      list-style: none;
      counter-reset: custom-counter;
    }

    ol li {
      counter-increment: custom-counter;
      position: relative;
      padding-left: 30px;
    }

    ol li::before {
      content: counter(custom-counter);
      position: absolute;
      left: 0;
      top: 0;
      background-color: var(--primary-color);
      color: white;
      width: 22px;
      height: 22px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: bold;
    }

    /* Estilização simples para o input de arquivo */
    .file-upload-wrapper {
      position: relative;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border: 3px dashed #ced4da;
      border-radius: 12px;
      padding: 30px 20px;
      text-align: center;
      transition: all 0.4s ease;
      cursor: pointer;
    }

    .file-upload-wrapper:hover {
      border-color: var(--primary-color);
      background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .file-upload-wrapper input[type="file"] {
      cursor: pointer;
      font-size: 14px;
    }

    .file-upload-wrapper .upload-icon {
      font-size: 3rem;
      color: #6c757d;
      margin-bottom: 15px;
      transition: all 0.3s ease;
    }

    .file-upload-wrapper:hover .upload-icon {
      color: var(--primary-color);
      transform: scale(1.1);
    }

    .file-upload-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 10px;
      display: block;
      font-size: 1.05rem;
    }

    .file-info-text {
      color: #6c757d;
      font-size: 0.875rem;
      margin-top: 12px;
      font-weight: 500;
    }

    .file-upload-wrapper:hover .file-info-text {
      color: var(--primary-color);
    }

    /* Remove espaçamento do topo */
    main {
      padding-top: 0 !important;
      margin-top: 0 !important;
    }

    .container-fluid {
      padding-top: 0.5rem !important;
    }

    .file-selected {
      color: #198754 !important;
    }

    /* Feedback do arquivo selecionado */
    .file-selected-feedback {
      margin-top: 10px;
      padding: 8px 12px;
      background-color: #d4edda;
      border: 1px solid #c3e6cb;
      border-radius: 6px;
      color: #155724;
      font-size: 0.9rem;
      display: none;
    }

    .file-selected-feedback i {
      margin-right: 8px;
    }
  </style>

  <!-- jQuery -->
  <script src='https://code.jquery.com/jquery-3.6.0.min.js'
    integrity='sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=' crossorigin='anonymous'></script>

</head>

<body class="sb-nav-fixed">
  <?php autofrotaMenu(); ?>

  <div id="layoutSidenav_content">
    <main>
      <div class="container-fluid px-4">

        <div class="row">
          <!-- Card principal de importação -->
          <div class="col-lg-7 mb-4">
            <div class="card shadow-lg border-0 h-100 overflow-hidden">
              <div class="card-header text-white py-3" style="background-color: #000000;">
                <div class="d-flex align-items-center">
                  <i class="fas fa-cloud-upload-alt fa-2x me-3"></i>
                  <div>
                    <h5 class="mb-0">Importação de Multas em Massa</h5>
                    <small>Sistema automatizado de processamento</small>
                  </div>
                </div>
              </div>
              <div class="card-body py-4">
                <div class="row align-items-center">
                  <div class="col-md-12">
                    <h5 class="mb-3">
                      <i class="fas fa-info-circle text-primary-custom"></i> Como funciona?
                    </h5>
                    <ol class="mb-4 ps-3">
                      <li class="mb-2">Baixe o modelo Excel disponível</li>
                      <li class="mb-2">Preencha com os dados das multas</li>
                      <li class="mb-2">Faça upload do arquivo preenchido</li>
                      <li class="mb-2">O sistema processa automaticamente</li>
                    </ol>

                    <!-- Formulário de Upload -->
                    <form method="post" action="../control/importarmultanova.php" enctype="multipart/form-data"
                      id="formImport">
                      <?php print "<input type='hidden' name='matr_autor' id='matr_autor' required value='" . htmlspecialchars($matricula1, ENT_QUOTES, 'UTF-8') . "'>"; ?>

                      <div class="mb-4">
                        <label for="arquivo" class="file-upload-label">
                          <i class="fas fa-file-excel me-2"></i>Selecione o arquivo Excel
                        </label>
                        <div class="file-upload-wrapper">
                          <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                          </div>
                          <input class="form-control" type="file" name="arquivo" id="arquivo" accept=".xls, .xlsx"
                            required>
                          <p class="file-info-text mb-0" id="fileInfoText">
                            <i class="fas fa-hand-pointer me-1"></i>
                            Arraste o arquivo aqui ou clique para selecionar
                          </p>
                        </div>
                        <div class="file-selected-feedback" id="fileSelectedFeedback">
                          <i class="fas fa-check-circle"></i>
                          <span id="fileName"></span>
                        </div>
                      </div>

                      <div class="d-grid gap-2">
                        <button id="btnEnviar" type="submit" class="btn btn-success btn-lg shadow-sm">
                          <i class="fas fa-check me-2"></i> Confirmar Cadastro
                        </button>
                        <a href="./control/docs/modelos/ImportModeloMultas.xlsx" class="btn btn-primary-custom btn-lg">
                          <i class="fas fa-download me-2"></i> Baixar Modelo Excel
                        </a>
                        <a href="multasfrota.php" class="btn btn-outline-secondary btn-lg">Voltar</a>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Card com instruções resumidas -->
          <div class="col-lg-5 mb-4">
            <div class="card border-warning h-100">
              <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Atenção - Requisitos Importantes</h6>
              </div>
              <div class="card-body" overflow-y: auto;">
                <ul class="mb-0 small">
                  <li class="mb-2"><strong>Formato:</strong> Apenas .xlsx (Excel) com uma planilha</li>
                  <li class="mb-2"><strong>Limite:</strong> Máximo de 1000 registros por arquivo</li>
                  <li class="mb-2"><strong>Placas:</strong> Somente placas cadastradas no sistema</li>
                  <li class="mb-2"><strong>Datas:</strong> Siga rigorosamente o formato do modelo
                    <ul class="mt-1">
                      <li>Data/Hora Cadastro: DD/MM/AAAA HH:MM:SS</li>
                      <li>Data/Hora Infração: DD/MM/AAAA HH:MM</li>
                      <li>AP Condutor Data Vencimento: DD/MM/AAAA HH:MM:SS</li>
                    </ul>
                  </li>
                  <li class="mb-2"><strong>Valores:</strong> Sem formatação contábil ou ponto de milhar</li>
                  <li class="mb-2"><strong>Auto de Infração:</strong> Mínimo de 8 dígitos</li>
                  <li class="mb-2"><strong>Duplicados:</strong> Não serão atualizados</li>
                  <li class="mb-2"><strong>UF:</strong> Envie com dois dígitos (ex: SP, RJ, MG)</li>
                  <li class="mb-2"><strong>Código Multa:</strong> Cinco dígitos, sem espaços</li>
                  <li class="mb-2"><strong>Gravidade:</strong> Use "LEVE", "MEDIA", "GRAVE" ou "GRAVISSIMA" (sem
                    acentos)</li>
                  <li class="mb-2"><strong>Pontuação:</strong> Valores aceitos: 3, 4, 5 ou 7</li>
                  <li class="mb-2"><strong>Trâmite:</strong> 'Inserir infrator', 'Imprimir Recibo DP', 'Confirmar
                    Desconto', 'Fazer Pagamento', 'Finalizado Frota', 'DP Finalizado', 'Finalizado Financeiro'</li>
                  <li class="mb-2"><strong>Etapa:</strong> "EM ABERTO", "AGUARDANDO ASSINATURA CONDUTOR", "CONCLUIDO",
                    "MULTA INDEVIDA", "COLABORADOR DEMITIDO", "MULTA ASSINADA", "ENVIADO PARA DESCONTO"</li>
                  <li class="mb-2"><strong>Condutor:</strong> Nome com a mesma grafia do Aniel</li>
                  <li class="mb-2"><strong>Centro de Custo:</strong> Mesma grafia do Aniel</li>
                  <li class="mb-2"><strong>Dados Textuais:</strong> Sem apóstrofo ('). Deixe vazio se não tiver
                    informação</li>
                  <li class="mb-2"><strong>Campos Vazios:</strong> Não envie ' - ' nos campos. Deixe a célula vazia</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
    crossorigin="anonymous"></script>
  <script src="../src/js/scripts.js"></script>

  <script>
    $(document).ready(function () {
      // Feedback visual do arquivo selecionado com validação de tamanho
      $("#arquivo").on("change", function (e) {
        const file = e.target.files[0];
        if (file) {
          // Validação de tamanho (máximo 10MB)
          const maxSize = 10 * 1024 * 1024; // 10MB em bytes
          if (file.size > maxSize) {
            alert('Arquivo muito grande! O tamanho máximo permitido é 10MB.');
            $(this).val('');
            $("#fileSelectedFeedback").hide();
            $("#fileInfoText").html('<i class="fas fa-hand-pointer me-1"></i> Arraste o arquivo aqui ou clique para selecionar').removeClass('file-selected');
            return;
          }

          // Exibir nome e tamanho do arquivo
          const fileSize = (file.size / 1024).toFixed(2);
          $("#fileName").text(file.name + ' (' + fileSize + ' KB)');
          $("#fileSelectedFeedback").fadeIn();
          $("#fileInfoText").html('<i class="fas fa-check-circle me-1"></i> Arquivo pronto para upload').addClass('file-selected');
        } else {
          $("#fileSelectedFeedback").fadeOut();
          $("#fileInfoText").html('<i class="fas fa-hand-pointer me-1"></i> Arraste o arquivo aqui ou clique para selecionar').removeClass('file-selected');
        }
      });

      // Previne duplo envio
      $("form").on("submit", function (event) {
        $("#btnEnviar").prop("disabled", true);
        $("#btnEnviar").html('<i class="fas fa-spinner fa-spin me-2"></i>Processando...');
      });
    });
  </script>

</body>

</html>