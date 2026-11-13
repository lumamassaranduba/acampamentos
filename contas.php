<?php
// contas.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$mensagem_sucesso = '';
$mensagem_erro = '';

require_once 'config/database.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$nome_usuario   = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Coordenador';
$perfil_usuario = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENAÇÃO'));

$edicao_id     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;
$aba_ativa     = $_GET['aba'] ?? 'fornecedores';

// Verificar estado da edição (Read-Only se inativa)
$stmt_ed = $conn->prepare("SELECT status, nome, ano FROM edicoes WHERE id = ?");
$stmt_ed->bind_param("i", $edicao_id);
$stmt_ed->execute();
$edicao = $stmt_ed->get_result()->fetch_assoc();
$is_inativa = ($edicao && strtolower($edicao['status']) === 'inativa');

// 1. GARANTE A CRIAÇÃO DAS TABELAS NECESSÁRIAS
$conn->query("
CREATE TABLE IF NOT EXISTS `contas_fornecedores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `modalidade_id` INT NOT NULL,
  `edicao_id` INT NOT NULL,
  `categoria_servico` VARCHAR(100) NOT NULL,
  `nome_fornecedor` VARCHAR(150) NOT NULL,
  `telefone` VARCHAR(50) DEFAULT NULL,
  `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `observacao` TEXT DEFAULT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("
CREATE TABLE IF NOT EXISTS `contas_compras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `modalidade_id` INT NOT NULL,
  `edicao_id` INT NOT NULL,
  `categoria` ENUM('cozinha', 'materiais') NOT NULL,
  `produto` VARCHAR(150) NOT NULL,
  `quantidade` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unidade` VARCHAR(20) DEFAULT 'un',
  `valor_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `valor_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Função auxiliar para converter valores monetários PT-BR
function parseValorBR($valor) {
    $limpo = trim((string)$valor);
    if ($limpo === '') return 0.0;
    if (strpos($limpo, ',') !== false) {
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);
    }
    return floatval($limpo);
}

// 2. CADASTRO DE NOVO FORNECEDOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_cadastrar_fornecedor'])) {
    $aba_ativa = 'fornecedores';
    if ($is_inativa) {
        $mensagem_erro = "Edições inativas não podem ser alteradas!";
    } else {
        $categoria       = trim($_POST['categoria_servico'] ?? '');
        $nome_fornecedor = trim($_POST['nome_fornecedor'] ?? '');
        $telefone        = trim($_POST['telefone'] ?? '');
        $preco           = parseValorBR($_POST['preco'] ?? '0');
        $observacao      = trim($_POST['observacao'] ?? '');

        if (!empty($nome_fornecedor) && !empty($categoria)) {
            $stmt = $conn->prepare("INSERT INTO contas_fornecedores (modalidade_id, edicao_id, categoria_servico, nome_fornecedor, telefone, preco, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssds", $modalidade_id, $edicao_id, $categoria, $nome_fornecedor, $telefone, $preco, $observacao);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Fornecedor cadastrado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao cadastrar fornecedor.";
            }
        } else {
            $mensagem_erro = "Preencha a categoria e o nome do fornecedor.";
        }
    }
}

// 3. CADASTRO DE ITEM NA LISTA DE COMPRAS (COZINHA OU MATERIAIS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_cadastrar_compra'])) {
    $categoria = $_POST['categoria'] ?? 'cozinha';
    $aba_ativa = $categoria;
    if ($is_inativa) {
        $mensagem_erro = "Edições inativas não podem ser alteradas!";
    } else {
        $produto        = trim($_POST['produto'] ?? '');
        $quantidade     = parseValorBR($_POST['quantidade'] ?? '1');
        $unidade        = trim($_POST['unidade'] ?? 'un');
        $valor_unitario = parseValorBR($_POST['valor_unitario'] ?? '0');
        $valor_total    = $quantidade * $valor_unitario;

        if (!empty($produto) && $quantidade > 0) {
            $stmt = $conn->prepare("INSERT INTO contas_compras (modalidade_id, edicao_id, categoria, produto, quantidade, unidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissdsdd", $modalidade_id, $edicao_id, $categoria, $produto, $quantidade, $unidade, $valor_unitario, $valor_total);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Item adicionado à lista de compras!";
            } else {
                $mensagem_erro = "Erro ao adicionar item de compra.";
            }
        } else {
            $mensagem_erro = "Preencha o nome do produto e a quantidade.";
        }
    }
}

// 4. EXCLUSÃO DE FORNECEDOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_fornecedor'])) {
    $aba_ativa = 'fornecedores';
    if (!$is_inativa) {
        $id_del = intval($_POST['fornecedor_id']);
        $stmt_del = $conn->prepare("DELETE FROM contas_fornecedores WHERE id = ? AND edicao_id = ?");
        $stmt_del->bind_param("ii", $id_del, $edicao_id);
        $stmt_del->execute();
        $mensagem_sucesso = "Fornecedor removido!";
    }
}

// 5. EXCLUSÃO DE ITEM DE COMPRA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_compra'])) {
    $aba_ativa = $_POST['aba_retorno'] ?? 'cozinha';
    if (!$is_inativa) {
        $id_del = intval($_POST['compra_id']);
        $stmt_del = $conn->prepare("DELETE FROM contas_compras WHERE id = ? AND edicao_id = ?");
        $stmt_del->bind_param("ii", $id_del, $edicao_id);
        $stmt_del->execute();
        $mensagem_sucesso = "Item removido da lista!";
    }
}

// 6. BUSCA DADOS E CÁLCULOS DE GASTOS
$stmt_mod = $conn->prepare("SELECT nome FROM modalidades WHERE id = ?");
$stmt_mod->bind_param("i", $modalidade_id);
$stmt_mod->execute();
$modalidade = $stmt_mod->get_result()->fetch_assoc();

// Fornecedores
$stmt_forn = $conn->prepare("SELECT * FROM contas_fornecedores WHERE edicao_id = ? ORDER BY categoria_servico ASC, nome_fornecedor ASC");
$stmt_forn->bind_param("i", $edicao_id);
$stmt_forn->execute();
$fornecedores = $stmt_forn->get_result()->fetch_all(MYSQLI_ASSOC);

// Compras Cozinha
$stmt_cozinha = $conn->prepare("SELECT * FROM contas_compras WHERE edicao_id = ? AND categoria = 'cozinha' ORDER BY produto ASC");
$stmt_cozinha->bind_param("i", $edicao_id);
$stmt_cozinha->execute();
$compras_cozinha = $stmt_cozinha->get_result()->fetch_all(MYSQLI_ASSOC);

// Compras Materiais
$stmt_mat = $conn->prepare("SELECT * FROM contas_compras WHERE edicao_id = ? AND categoria = 'materiais' ORDER BY produto ASC");
$stmt_mat->bind_param("i", $edicao_id);
$stmt_mat->execute();
$compras_materiais = $stmt_mat->get_result()->fetch_all(MYSQLI_ASSOC);

// TOTAIS
$total_cozinha     = array_sum(array_column($compras_cozinha, 'valor_total'));
$total_materiais   = array_sum(array_column($compras_materiais, 'valor_total'));
$gasto_total_geral = $total_cozinha + $total_materiais;

$url_voltar = "dashboard-geral.php";
if ($edicao_id > 0) {
    $url_voltar = "edicao-detalhes.php?edicao_id={$edicao_id}" . ($modalidade_id > 0 ? "&modalidade_id={$modalidade_id}" : "");
}

$subtitulo_edicao = trim(($modalidade['nome'] ?? '') . (!empty($edicao['nome']) ? ' - ' . $edicao['nome'] : ''), ' -');
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Contas e Gastos - <?= htmlspecialchars($modalidade['nome'] ?? 'ÉFATA') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --navy-dark: #0A192F;
            --navy-hover: #172A46;
            --gold-primary: #C5A059;
            --gold-hover: #B28D46;
            --bg-main: #F8FAFC;
            --slate-text: #334155;
            --border-color: #E2E8F0;
        }

        * { box-sizing: border-box; }

        body {
            max-width: 100%;
            overflow-x: hidden;
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-main);
            color: var(--navy-dark);
            margin: 0;
            padding: 0;
        }

        body.modal-open {
            overflow: hidden !important;
        }

        /* NAVBAR PADRONIZADA */
        .top-navbar {
            background-color: var(--navy-dark);
            border-bottom: 2px solid var(--gold-primary);
            padding: 0.75rem 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.15);
        }

        .user-brand-block { min-width: 0; }

        .brand-icon {
            width: 40px;
            height: 40px;
            min-width: 40px;
            background-color: #ffffff;
            border: 2px solid var(--gold-primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 2px;
        }

        .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .user-info-text {
            line-height: 1.2;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .btn-outline-danger-custom {
            border: 1px solid rgba(239, 68, 68, 0.5);
            color: #f87171;
            background: transparent;
            transition: all 0.2s ease;
        }
        .btn-outline-danger-custom:hover {
            background-color: #ef4444;
            color: #ffffff;
        }

        /* BOTÕES NA PALETA ÉFATA */
        .btn-gold-pill {
            background-color: var(--gold-primary);
            color: #ffffff;
            font-weight: 600;
            border: 1.5px solid var(--gold-primary);
            border-radius: 50px;
            padding: 0.5rem 1.2rem;
            transition: all 0.2s ease;
        }
        .btn-gold-pill:hover {
            background-color: var(--gold-hover);
            border-color: var(--gold-hover);
            color: #ffffff;
        }

        .btn-outline-navy-pill {
            background-color: #ffffff;
            color: var(--navy-dark);
            font-weight: 600;
            border: 1.5px solid var(--navy-dark);
            border-radius: 50px;
            padding: 0.5rem 1.2rem;
            transition: all 0.2s ease;
        }
        .btn-outline-navy-pill:hover {
            background-color: var(--navy-dark);
            color: #ffffff;
        }

        .btn-only-arrow {
            color: #475569;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background-color: #F1F5F9;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }
        .btn-only-arrow:hover {
            color: var(--navy-dark);
            background-color: #E2E8F0;
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        /* CARDS MINIMALISTAS */
        .card-total-minimal {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-radius: 16px;
            border-bottom: 3px solid var(--gold-primary);
            padding: 1.4rem;
            box-shadow: 0 6px 20px -6px rgba(10, 25, 47, 0.22);
        }

        .card-subtotal-minimal {
            background: #ffffff;
            border: 1px solid #EAECEF;
            border-top: 3px solid var(--gold-primary);
            border-radius: 16px;
            padding: 1.35rem;
            box-shadow: 0 2px 10px rgba(10, 25, 47, 0.03);
        }

        .panel-minimal {
            background: #ffffff;
            border: 1px solid #EAECEF;
            border-radius: 18px;
            padding: 1.35rem;
        }

        .item-card-mobile {
            background: #ffffff;
            border: 1px solid #EAECEF;
            border-left: 3px solid var(--gold-primary);
            border-radius: 14px;
            padding: 1rem;
        }

        .badge-efata {
            background-color: #FDF8EE;
            color: #8C6A28;
            border: 1px solid #F2E3C2;
            font-weight: 600;
            font-size: 0.75rem;
            padding: 4px 10px;
            border-radius: 50px;
        }

        .valor-gold {
            color: #9A742E;
            font-weight: 700;
        }

        /* ABAS RESPONSIVAS */
        .nav-pills-efata {
            background: #ffffff;
            border: 1px solid #EAECEF;
            border-radius: 16px;
            padding: 6px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
        }
        .nav-pills-efata .nav-item { min-width: 0; }
        .nav-pills-efata .nav-link {
            width: 100%;
            height: 100%;
            color: var(--slate-text);
            font-weight: 600;
            border-radius: 12px;
            padding: 10px 8px;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-align: center;
            line-height: 1.2;
            border: none;
            background: transparent;
            transition: all 0.2s ease;
        }
        .nav-pills-efata .nav-link.active {
            background-color: var(--navy-dark);
            color: #ffffff;
        }
        .nav-pills-efata .nav-link .badge-count {
            background-color: #E2E8F0;
            color: var(--navy-dark);
            font-size: 0.72rem;
            padding: 2px 7px;
            border-radius: 50px;
        }
        .nav-pills-efata .nav-link.active .badge-count {
            background-color: var(--gold-primary);
            color: #ffffff;
        }

        /* TABELA LIMPA DESKTOP */
        .table-minimal th {
            background-color: #F8FAFC;
            color: #475569;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 700;
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #E2E8F0;
        }
        .table-minimal td {
            padding: 0.9rem 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #F1F5F9;
        }
        .table-minimal tbody tr:last-child td {
            border-bottom: none;
        }

        .btn-icon-delete {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            background: #ffffff;
            color: #64748B;
            transition: all 0.15s ease;
        }
        .btn-icon-delete:hover {
            background: #FEF2F2;
            border-color: #FECACA;
            color: #DC2626;
        }

        .delete-icon-circle {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background-color: #FEF2F2;
            color: #DC2626;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 10px;
        }

        @media (max-width: 767.98px) {
            .nav-pills-efata .nav-link {
                flex-direction: column;
                padding: 8px 4px;
                font-size: 0.78rem;
                gap: 3px;
            }
            .panel-minimal {
                padding: 1rem;
            }
        }

        /* ÁREA EXCLUSIVA DE IMPRESSÃO (SOMENTE COZINHA + MATERIAIS + GASTOS) */
        .print-only-report { display: none; }

        @media print {
            @page {
                size: A4 portrait;
                margin: 1.6cm 1.8cm;
            }

            .no-print,
            .top-navbar,
            .page-header-bar,
            main.screen-only-main,
            .modal,
            .modal-backdrop {
                display: none !important;
            }

            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: Arial, Helvetica, sans-serif !important;
                font-size: 11pt !important;
                line-height: 1.45 !important;
                overflow: visible !important;
            }

            .print-only-report {
                display: block !important;
                width: 100% !important;
            }

            .print-header {
                text-align: center;
                border-bottom: 1.5pt solid #000000;
                padding-bottom: 8pt;
                margin-bottom: 16pt;
            }

            .print-header h1 {
                font-size: 15pt;
                font-weight: bold;
                text-transform: uppercase;
                margin: 0 0 3pt 0;
            }

            .print-header p {
                font-size: 10.5pt;
                margin: 0;
                color: #333333;
            }

            .print-section-title {
                font-size: 12pt;
                font-weight: bold;
                text-transform: uppercase;
                margin: 16pt 0 6pt 0;
                padding-bottom: 3pt;
                border-bottom: 1pt solid #000000;
                page-break-after: avoid;
            }

            .print-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 12pt;
            }

            .print-table th,
            .print-table td {
                border-bottom: 0.5pt solid #CCCCCC;
                padding: 5pt 4pt;
                text-align: left;
                font-size: 10.5pt;
            }

            .print-table th {
                font-weight: bold;
                border-bottom: 1pt solid #000000;
            }

            .print-table .text-right {
                text-align: right;
            }

            .print-subtotal-row td {
                font-weight: bold;
                border-top: 1pt solid #000000;
                border-bottom: none;
                padding-top: 6pt;
            }

            .print-resumo-final {
                margin-top: 18pt;
                padding-top: 10pt;
                border-top: 1.5pt solid #000000;
                page-break-inside: avoid;
            }

            .print-resumo-linha {
                display: flex;
                justify-content: space-between;
                font-size: 11pt;
                margin-bottom: 4pt;
            }

            .print-resumo-total {
                display: flex;
                justify-content: space-between;
                font-size: 13pt;
                font-weight: bold;
                margin-top: 6pt;
                padding-top: 6pt;
                border-top: 1pt solid #000000;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR PADRONIZADA -->
    <nav class="top-navbar d-flex align-items-center justify-content-between gap-2 no-print">
        <div class="d-flex align-items-center gap-2 gap-sm-3 user-brand-block">
            <div class="brand-icon shadow-sm">
                <img src="./uploads/logos/logoefata.png" alt="Logo Éfata" onerror="this.onerror=null; this.src='assets/img/logo.png';">
            </div>
            <div class="user-info-text">
                <h6 class="fw-bold m-0 text-white text-truncate" style="font-size: 0.92rem;"><?= htmlspecialchars($nome_usuario) ?></h6>
                <small class="text-light opacity-75 d-block text-truncate" style="font-size: 0.72rem;"><?= htmlspecialchars($perfil_usuario) ?></small>
            </div>
        </div>

        <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout" title="Sair">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Sair</span>
        </button>
    </nav>

    <!-- CABEÇALHO DA PÁGINA -->
    <div class="bg-white border-bottom py-3 mb-4 page-header-bar no-print">
        <div class="container d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3" style="max-width: 1280px;">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($url_voltar) ?>" class="btn-only-arrow" title="Voltar">
                    <i class="bi bi-arrow-left fs-5"></i>
                </a>
                <div>
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-wallet2" style="color: var(--gold-primary);"></i>Gestão de Contas e Gastos
                    </h5>
                    <small class="text-muted fw-medium">
                        <?= !empty($subtitulo_edicao) ? htmlspecialchars($subtitulo_edicao) : 'Fornecedores e Listas de Compras' ?>
                    </small>
                </div>
            </div>

            <button type="button" onclick="window.print()" class="btn btn-outline-navy-pill btn-sm d-inline-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-printer-fill" style="color: var(--gold-primary);"></i>
                <span>Imprimir Lista de Compras</span>
            </button>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL EM TELA -->
    <main class="container pb-5 screen-only-main no-print" style="max-width: 1280px;">

        <!-- ALERTAS -->
        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($mensagem_sucesso) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($mensagem_erro) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- CARDS MINIMALISTAS DE TOTALIZADORES -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-4">
                <div class="card-total-minimal d-flex flex-column justify-content-between h-100">
                    <div>
                        <span class="text-white-50 text-uppercase fw-semibold" style="font-size: 0.74rem; letter-spacing: 0.04em;">Gasto Total de Compras</span>
                        <h2 class="fw-bold m-0 mt-1" style="color: var(--gold-primary);">R$ <?= number_format($gasto_total_geral, 2, ',', '.') ?></h2>
                    </div>
                    <div class="small text-white-50 mt-3 pt-2" style="border-top: 1px solid rgba(255,255,255,0.12); font-size: 0.78rem;">
                        <i class="bi bi-calculator me-1" style="color: var(--gold-primary);"></i> Cozinha + Materiais
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-4">
                <div class="card-subtotal-minimal d-flex flex-column justify-content-between h-100">
                    <div>
                        <span class="text-muted fw-semibold d-block text-uppercase" style="font-size: 0.73rem;">Compras Cozinha</span>
                        <h4 class="fw-bold text-dark mt-1 mb-2">R$ <?= number_format($total_cozinha, 2, ',', '.') ?></h4>
                    </div>
                    <div>
                        <span class="badge-efata"><?= count($compras_cozinha) ?> item(ns)</span>
                    </div>
                </div>
            </div>

            <div class="col-6 col-lg-4">
                <div class="card-subtotal-minimal d-flex flex-column justify-content-between h-100">
                    <div>
                        <span class="text-muted fw-semibold d-block text-uppercase" style="font-size: 0.73rem;">Compras Materiais</span>
                        <h4 class="fw-bold text-dark mt-1 mb-2">R$ <?= number_format($total_materiais, 2, ',', '.') ?></h4>
                    </div>
                    <div>
                        <span class="badge-efata"><?= count($compras_materiais) ?> item(ns)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ABAS RESPONSIVAS -->
        <ul class="nav nav-pills-efata mb-4 shadow-sm" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= ($aba_ativa === 'resumo' || $aba_ativa === 'fornecedores') ? 'active' : '' ?>" id="pills-fornecedores-tab" data-bs-toggle="pill" data-bs-target="#pills-fornecedores" type="button" role="tab">
                    <span><i class="bi bi-truck"></i> Fornecedores</span>
                    <span class="badge-count"><?= count($fornecedores) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $aba_ativa === 'cozinha' ? 'active' : '' ?>" id="pills-cozinha-tab" data-bs-toggle="pill" data-bs-target="#pills-cozinha" type="button" role="tab">
                    <span><i class="bi bi-egg-fried"></i> Cozinha</span>
                    <span class="badge-count"><?= count($compras_cozinha) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $aba_ativa === 'materiais' ? 'active' : '' ?>" id="pills-materiais-tab" data-bs-toggle="pill" data-bs-target="#pills-materiais" type="button" role="tab">
                    <span><i class="bi bi-box-seam"></i> Materiais</span>
                    <span class="badge-count"><?= count($compras_materiais) ?></span>
                </button>
            </li>
        </ul>

        <!-- CONTEÚDO DAS ABAS -->
        <div class="tab-content" id="pills-tabContent">

            <!-- 1. ABA FORNECEDORES -->
            <div class="tab-pane fade <?= ($aba_ativa === 'resumo' || $aba_ativa === 'fornecedores') ? 'show active' : '' ?>" id="pills-fornecedores" role="tabpanel">
                <div class="panel-minimal">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2 mb-3 pb-3 border-bottom">
                        <h6 class="fw-bold m-0 text-dark d-flex align-items-center gap-2" style="font-size: 1.02rem;">
                            <i class="bi bi-truck" style="color: var(--gold-primary);"></i>Fornecedores Contratados
                        </h6>
                        <?php if (!$is_inativa): ?>
                            <button type="button" class="btn btn-gold-pill btn-sm d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovoFornecedor">
                                <i class="bi bi-plus-lg"></i>
                                <span>Adicionar Fornecedor</span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($fornecedores)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-truck fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                            Nenhum fornecedor cadastrado.
                        </div>
                    <?php else: ?>
                        <!-- Tabela Desktop -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-minimal m-0">
                                <thead>
                                    <tr>
                                        <th>Categoria</th>
                                        <th>Fornecedor</th>
                                        <th>Telefone</th>
                                        <th>Orçamento</th>
                                        <th>Observações</th>
                                        <?php if (!$is_inativa): ?>
                                            <th class="text-end">Ação</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fornecedores as $f): ?>
                                        <tr>
                                            <td><span class="badge-efata"><?= htmlspecialchars($f['categoria_servico']) ?></span></td>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($f['nome_fornecedor']) ?></td>
                                            <td class="text-secondary"><?= !empty($f['telefone']) ? htmlspecialchars($f['telefone']) : '-' ?></td>
                                            <td class="valor-gold">R$ <?= number_format($f['preco'], 2, ',', '.') ?></td>
                                            <td class="small text-secondary"><?= !empty($f['observacao']) ? htmlspecialchars($f['observacao']) : '-' ?></td>
                                            <?php if (!$is_inativa): ?>
                                                <td class="text-end">
                                                    <button type="button" class="btn-icon-delete" onclick='abrirModalExclusao("fornecedor", <?= intval($f['id']) ?>, <?= htmlspecialchars(json_encode($f['nome_fornecedor']), ENT_QUOTES, 'UTF-8') ?>, "fornecedores")' title="Excluir">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Cards Mobile -->
                        <div class="d-flex flex-column gap-2 d-md-none">
                            <?php foreach ($fornecedores as $f): ?>
                                <div class="item-card-mobile">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <div>
                                            <span class="badge-efata d-inline-block mb-1"><?= htmlspecialchars($f['categoria_servico']) ?></span>
                                            <h6 class="fw-bold text-dark m-0"><?= htmlspecialchars($f['nome_fornecedor']) ?></h6>
                                        </div>
                                        <?php if (!$is_inativa): ?>
                                            <button type="button" class="btn-icon-delete flex-shrink-0" onclick='abrirModalExclusao("fornecedor", <?= intval($f['id']) ?>, <?= htmlspecialchars(json_encode($f['nome_fornecedor']), ENT_QUOTES, 'UTF-8') ?>, "fornecedores")'>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small pt-2 border-top">
                                        <span class="text-muted"><?= !empty($f['telefone']) ? htmlspecialchars($f['telefone']) : 'Sem telefone' ?></span>
                                        <span class="valor-gold">R$ <?= number_format($f['preco'], 2, ',', '.') ?></span>
                                    </div>
                                    <?php if (!empty($f['observacao'])): ?>
                                        <p class="small text-secondary m-0 mt-2 pt-2 border-top"><?= htmlspecialchars($f['observacao']) ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. ABA COMPRAS COZINHA -->
            <div class="tab-pane fade <?= $aba_ativa === 'cozinha' ? 'show active' : '' ?>" id="pills-cozinha" role="tabpanel">
                <div class="panel-minimal">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2 mb-3 pb-3 border-bottom">
                        <h6 class="fw-bold m-0 text-dark d-flex align-items-center gap-2" style="font-size: 1.02rem;">
                            <i class="bi bi-egg-fried" style="color: var(--gold-primary);"></i>Lista de Compras: Cozinha
                        </h6>
                        <?php if (!$is_inativa): ?>
                            <button type="button" class="btn btn-gold-pill btn-sm d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovaCompraCozinha">
                                <i class="bi bi-plus-lg"></i>
                                <span>Adicionar Item</span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($compras_cozinha)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-egg-fried fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                            Nenhum item cadastrado para a cozinha.
                        </div>
                    <?php else: ?>
                        <!-- Tabela Desktop -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-minimal m-0">
                                <thead>
                                    <tr>
                                        <th>Produto / Item</th>
                                        <th>Quantidade</th>
                                        <th>Valor Unitário</th>
                                        <th>Valor Total</th>
                                        <?php if (!$is_inativa): ?>
                                            <th class="text-end">Ação</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($compras_cozinha as $c): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($c['produto']) ?></td>
                                            <td class="text-secondary"><?= number_format($c['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($c['unidade']) ?></td>
                                            <td class="text-secondary">R$ <?= number_format($c['valor_unitario'], 2, ',', '.') ?></td>
                                            <td class="valor-gold">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                                            <?php if (!$is_inativa): ?>
                                                <td class="text-end">
                                                    <button type="button" class="btn-icon-delete" onclick='abrirModalExclusao("compra", <?= intval($c['id']) ?>, <?= htmlspecialchars(json_encode($c['produto']), ENT_QUOTES, 'UTF-8') ?>, "cozinha")' title="Excluir">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Cards Mobile -->
                        <div class="d-flex flex-column gap-2 d-md-none">
                            <?php foreach ($compras_cozinha as $c): ?>
                                <div class="item-card-mobile">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <h6 class="fw-bold text-dark m-0"><?= htmlspecialchars($c['produto']) ?></h6>
                                        <?php if (!$is_inativa): ?>
                                            <button type="button" class="btn-icon-delete flex-shrink-0" onclick='abrirModalExclusao("compra", <?= intval($c['id']) ?>, <?= htmlspecialchars(json_encode($c['produto']), ENT_QUOTES, 'UTF-8') ?>, "cozinha")'>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small pt-2 border-top">
                                        <span class="text-muted"><?= number_format($c['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($c['unidade']) ?> × R$ <?= number_format($c['valor_unitario'], 2, ',', '.') ?></span>
                                        <span class="valor-gold">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. ABA COMPRAS MATERIAIS -->
            <div class="tab-pane fade <?= $aba_ativa === 'materiais' ? 'show active' : '' ?>" id="pills-materiais" role="tabpanel">
                <div class="panel-minimal">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center gap-2 mb-3 pb-3 border-bottom">
                        <h6 class="fw-bold m-0 text-dark d-flex align-items-center gap-2" style="font-size: 1.02rem;">
                            <i class="bi bi-box-seam" style="color: var(--gold-primary);"></i>Lista de Compras: Materiais
                        </h6>
                        <?php if (!$is_inativa): ?>
                            <button type="button" class="btn btn-gold-pill btn-sm d-inline-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovaCompraMateriais">
                                <i class="bi bi-plus-lg"></i>
                                <span>Adicionar Material</span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($compras_materiais)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                            Nenhum material cadastrado.
                        </div>
                    <?php else: ?>
                        <!-- Tabela Desktop -->
                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-minimal m-0">
                                <thead>
                                    <tr>
                                        <th>Produto / Material</th>
                                        <th>Quantidade</th>
                                        <th>Valor Unitário</th>
                                        <th>Valor Total</th>
                                        <?php if (!$is_inativa): ?>
                                            <th class="text-end">Ação</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($compras_materiais as $m): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?= htmlspecialchars($m['produto']) ?></td>
                                            <td class="text-secondary"><?= number_format($m['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade']) ?></td>
                                            <td class="text-secondary">R$ <?= number_format($m['valor_unitario'], 2, ',', '.') ?></td>
                                            <td class="valor-gold">R$ <?= number_format($m['valor_total'], 2, ',', '.') ?></td>
                                            <?php if (!$is_inativa): ?>
                                                <td class="text-end">
                                                    <button type="button" class="btn-icon-delete" onclick='abrirModalExclusao("compra", <?= intval($m['id']) ?>, <?= htmlspecialchars(json_encode($m['produto']), ENT_QUOTES, 'UTF-8') ?>, "materiais")' title="Excluir">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Cards Mobile -->
                        <div class="d-flex flex-column gap-2 d-md-none">
                            <?php foreach ($compras_materiais as $m): ?>
                                <div class="item-card-mobile">
                                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                        <h6 class="fw-bold text-dark m-0"><?= htmlspecialchars($m['produto']) ?></h6>
                                        <?php if (!$is_inativa): ?>
                                            <button type="button" class="btn-icon-delete flex-shrink-0" onclick='abrirModalExclusao("compra", <?= intval($m['id']) ?>, <?= htmlspecialchars(json_encode($m['produto']), ENT_QUOTES, 'UTF-8') ?>, "materiais")'>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center small pt-2 border-top">
                                        <span class="text-muted"><?= number_format($m['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade']) ?> × R$ <?= number_format($m['valor_unitario'], 2, ',', '.') ?></span>
                                        <span class="valor-gold">R$ <?= number_format($m['valor_total'], 2, ',', '.') ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- RELATÓRIO DE IMPRESSÃO EXCLUSIVO: LISTA DE COMPRAS COZINHA + MATERIAIS + GASTOS -->
    <div class="print-only-report">
        <div class="print-header">
            <h1>Lista de Compras e Gastos</h1>
            <?php if (!empty($subtitulo_edicao)): ?>
                <p><?= htmlspecialchars($subtitulo_edicao) ?></p>
            <?php endif; ?>
        </div>

        <div class="print-section-title">1. Lista de Compras — Cozinha</div>
        <?php if (!empty($compras_cozinha)): ?>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Produto / Item</th>
                        <th class="text-right">Qtd.</th>
                        <th class="text-right">Valor Unit.</th>
                        <th class="text-right">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras_cozinha as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['produto']) ?></td>
                            <td class="text-right"><?= number_format($c['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($c['unidade']) ?></td>
                            <td class="text-right">R$ <?= number_format($c['valor_unitario'], 2, ',', '.') ?></td>
                            <td class="text-right">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="print-subtotal-row">
                        <td colspan="3">Subtotal Cozinha (<?= count($compras_cozinha) ?> item(ns))</td>
                        <td class="text-right">R$ <?= number_format($total_cozinha, 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom: 14pt;">Nenhum item cadastrado para a cozinha.</p>
        <?php endif; ?>

        <div class="print-section-title">2. Lista de Compras — Materiais</div>
        <?php if (!empty($compras_materiais)): ?>
            <table class="print-table">
                <thead>
                    <tr>
                        <th>Produto / Material</th>
                        <th class="text-right">Qtd.</th>
                        <th class="text-right">Valor Unit.</th>
                        <th class="text-right">Valor Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compras_materiais as $m): ?>
                        <tr>
                            <td><?= htmlspecialchars($m['produto']) ?></td>
                            <td class="text-right"><?= number_format($m['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade']) ?></td>
                            <td class="text-right">R$ <?= number_format($m['valor_unitario'], 2, ',', '.') ?></td>
                            <td class="text-right">R$ <?= number_format($m['valor_total'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="print-subtotal-row">
                        <td colspan="3">Subtotal Materiais (<?= count($compras_materiais) ?> item(ns))</td>
                        <td class="text-right">R$ <?= number_format($total_materiais, 2, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        <?php else: ?>
            <p style="margin-bottom: 14pt;">Nenhum material cadastrado.</p>
        <?php endif; ?>

        <div class="print-resumo-final">
            <div class="print-resumo-linha">
                <span>Total Compras Cozinha:</span>
                <span>R$ <?= number_format($total_cozinha, 2, ',', '.') ?></span>
            </div>
            <div class="print-resumo-linha">
                <span>Total Compras Materiais:</span>
                <span>R$ <?= number_format($total_materiais, 2, ',', '.') ?></span>
            </div>
            <div class="print-resumo-total">
                <span>GASTO TOTAL (COZINHA + MATERIAIS):</span>
                <span>R$ <?= number_format($gasto_total_geral, 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <!-- MODAL NOVO FORNECEDOR -->
    <div class="modal fade no-print" id="modalNovoFornecedor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header modal-header-custom p-3 p-md-4">
                    <h5 class="modal-title fw-bold text-white m-0 d-flex align-items-center gap-2">
                        <i class="bi bi-truck" style="color: var(--gold-primary);"></i> Novo Fornecedor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="contas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&aba=fornecedores" method="POST">
                    <input type="hidden" name="acao_cadastrar_fornecedor" value="1">
                    <div class="modal-body p-3 p-md-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Categoria / Serviço *</label>
                            <input type="text" name="categoria_servico" class="form-control" placeholder="Ex: Camisetas, Gráfica, Som, Transporte" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nome do Fornecedor / Empresa *</label>
                            <input type="text" name="nome_fornecedor" class="form-control" placeholder="Ex: Malharia Silva, Gráfica Central" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Telefone / Contato</label>
                                <input type="text" name="telefone" class="form-control" placeholder="(18) 99999-9999">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Preço / Valor Total (R$)</label>
                                <input type="text" name="preco" class="form-control" placeholder="0,00">
                            </div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label small fw-semibold">Observações</label>
                            <textarea name="observacao" class="form-control" rows="2" placeholder="Ex: 150 camisetas modelo dryfit azul..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3 justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold-pill btn-sm px-4">Salvar Fornecedor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL NOVA COMPRA COZINHA -->
    <div class="modal fade no-print" id="modalNovaCompraCozinha" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header modal-header-custom p-3 p-md-4">
                    <h5 class="modal-title fw-bold text-white m-0 d-flex align-items-center gap-2">
                        <i class="bi bi-egg-fried" style="color: var(--gold-primary);"></i> Adicionar Item de Cozinha
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="contas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&aba=cozinha" method="POST">
                    <input type="hidden" name="acao_cadastrar_compra" value="1">
                    <input type="hidden" name="categoria" value="cozinha">

                    <div class="modal-body p-3 p-md-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nome do Produto *</label>
                            <input type="text" name="produto" class="form-control" placeholder="Ex: Arroz 5kg, Carne moída, Leite" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Quantidade *</label>
                                <input type="text" name="quantidade" class="form-control" placeholder="Ex: 10" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Unidade de Medida</label>
                                <input type="text" name="unidade" class="form-control" placeholder="Ex: kg, un, pct, caixa" value="un">
                            </div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label small fw-semibold">Valor Unitário (R$)</label>
                            <input type="text" name="valor_unitario" class="form-control" placeholder="0,00">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3 justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold-pill btn-sm px-4">Adicionar Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL NOVA COMPRA MATERIAIS -->
    <div class="modal fade no-print" id="modalNovaCompraMateriais" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
                <div class="modal-header modal-header-custom p-3 p-md-4">
                    <h5 class="modal-title fw-bold text-white m-0 d-flex align-items-center gap-2">
                        <i class="bi bi-box-seam" style="color: var(--gold-primary);"></i> Adicionar Material
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="contas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&aba=materiais" method="POST">
                    <input type="hidden" name="acao_cadastrar_compra" value="1">
                    <input type="hidden" name="categoria" value="materiais">

                    <div class="modal-body p-3 p-md-4">
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nome do Material / Produto *</label>
                            <input type="text" name="produto" class="form-control" placeholder="Ex: Cabo de aço 8mm, Lona preta, Bexigas" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Quantidade *</label>
                                <input type="text" name="quantidade" class="form-control" placeholder="Ex: 5" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-semibold">Unidade de Medida</label>
                                <input type="text" name="unidade" class="form-control" placeholder="Ex: metros, un, rolo" value="un">
                            </div>
                        </div>

                        <div class="mb-1">
                            <label class="form-label small fw-semibold">Valor Unitário (R$)</label>
                            <input type="text" name="valor_unitario" class="form-control" placeholder="0,00">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3 justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold-pill btn-sm px-4">Adicionar Material</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL CONFIRMAR EXCLUSÃO -->
    <div class="modal fade no-print" id="modalConfirmarExclusao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form method="POST" class="modal-content border-0 rounded-4 overflow-hidden shadow-lg text-center" id="formExcluirItem">
                <input type="hidden" name="acao_excluir_fornecedor" id="inputAcaoExcluirForn" value="1" disabled>
                <input type="hidden" name="fornecedor_id" id="inputFornId" value="0" disabled>

                <input type="hidden" name="acao_excluir_compra" id="inputAcaoExcluirCompra" value="1" disabled>
                <input type="hidden" name="compra_id" id="inputCompraId" value="0" disabled>
                <input type="hidden" name="aba_retorno" id="inputAbaRetorno" value="cozinha" disabled>

                <div class="modal-header modal-header-custom p-3 justify-content-center">
                    <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-trash3 text-warning fs-5"></i> Confirmar Exclusão
                    </h5>
                </div>

                <div class="modal-body p-4">
                    <div class="delete-icon-circle">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Excluir este item?</h6>
                    <p class="fw-semibold text-dark small mb-2" id="excluir_preview" style="word-break: break-word;"></p>
                    <p class="text-muted small mb-0">Esta ação não poderá ser desfeita.</p>
                </div>

                <div class="modal-footer bg-light p-3 border-top justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-3 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger rounded-pill px-4 btn-sm fw-semibold">
                        <i class="bi bi-trash me-1"></i> Excluir
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CONFIRMAR LOGOUT -->
    <div class="modal fade no-print" id="modalConfirmarLogout" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg text-center">
                <div class="modal-header modal-header-custom p-3 justify-content-center">
                    <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-right text-warning fs-5"></i> Confirmar Saída
                    </h5>
                </div>
                <div class="modal-body p-4">
                    <p class="text-secondary mb-0">Deseja realmente encerrar a sua sessão e sair do sistema?</p>
                </div>
                <div class="modal-footer bg-light p-3 border-top justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <a href="logout.php" class="btn btn-danger rounded-3 px-3 btn-sm fw-semibold">Sair do Sistema</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function abrirModalExclusao(tipo, id, nome, abaRetorno) {
            const acaoForn   = document.getElementById('inputAcaoExcluirForn');
            const fornId     = document.getElementById('inputFornId');
            const acaoCompra = document.getElementById('inputAcaoExcluirCompra');
            const compraId   = document.getElementById('inputCompraId');
            const abaInput   = document.getElementById('inputAbaRetorno');

            acaoForn.disabled   = true;
            fornId.disabled     = true;
            acaoCompra.disabled = true;
            compraId.disabled   = true;
            abaInput.disabled   = true;

            if (tipo === 'fornecedor') {
                acaoForn.disabled = false;
                fornId.disabled   = false;
                fornId.value      = id;
            } else {
                acaoCompra.disabled = false;
                compraId.disabled   = false;
                abaInput.disabled   = false;
                compraId.value      = id;
                abaInput.value      = abaRetorno || 'cozinha';
            }

            document.getElementById('excluir_preview').innerText = '“' + nome + '”';
            new bootstrap.Modal(document.getElementById('modalConfirmarExclusao')).show();
        }
    </script>
</body>
</html>