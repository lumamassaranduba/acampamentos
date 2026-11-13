<?php
// recreacao.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nome_usuario   = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil_usuario = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL'));

$edicao_id_param     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id_param = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

$mensagem_sucesso = '';
$mensagem_erro    = '';

// 1. Criar tabela unificada para Recreação
$conn->query("
    CREATE TABLE IF NOT EXISTS `recreacao` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `edicao_id` INT DEFAULT 0,
        `tipo` ENUM('brincadeira', 'teatro', 'olimpiada') NOT NULL,
        `nome` VARCHAR(255) DEFAULT NULL,
        `explicacao` TEXT DEFAULT NULL,
        `momento` VARCHAR(255) DEFAULT NULL,
        `texto_teatro` LONGTEXT DEFAULT NULL,
        `tema` VARCHAR(255) DEFAULT NULL,
        `personagens` TEXT DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2. Processar Ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    $acao = $_POST['acao'];

    if ($acao === 'salvar_brincadeira') {
        $id         = intval($_POST['id'] ?? 0);
        $nome       = trim($_POST['nome'] ?? '');
        $explicacao = trim($_POST['explicacao'] ?? '');

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE recreacao SET nome = ?, explicacao = ? WHERE id = ?");
            $stmt->bind_param("ssi", $nome, $explicacao, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO recreacao (edicao_id, tipo, nome, explicacao) VALUES (?, 'brincadeira', ?, ?)");
            $stmt->bind_param("iss", $edicao_id_param, $nome, $explicacao);
        }
        if ($stmt->execute()) {
            $mensagem_sucesso = "Brincadeira salva com sucesso!";
        } else {
            $mensagem_erro = "Erro ao salvar brincadeira.";
        }
    }

    if ($acao === 'salvar_teatro') {
        $id           = intval($_POST['id'] ?? 0);
        $nome         = trim($_POST['nome'] ?? '');
        $momento      = trim($_POST['momento'] ?? '');
        $texto_teatro = trim($_POST['texto_teatro'] ?? '');

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE recreacao SET nome = ?, momento = ?, texto_teatro = ? WHERE id = ?");
            $stmt->bind_param("sssi", $nome, $momento, $texto_teatro, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO recreacao (edicao_id, tipo, nome, momento, texto_teatro) VALUES (?, 'teatro', ?, ?, ?)");
            $stmt->bind_param("isss", $edicao_id_param, $nome, $momento, $texto_teatro);
        }
        if ($stmt->execute()) {
            $mensagem_sucesso = "Teatro salvo com sucesso!";
        } else {
            $mensagem_erro = "Erro ao salvar teatro.";
        }
    }

    if ($acao === 'salvar_olimpiada') {
        $id          = intval($_POST['id'] ?? 0);
        $tema        = trim($_POST['tema'] ?? '');
        $personagens = trim($_POST['personagens'] ?? '');
        $explicacao  = trim($_POST['explicacao'] ?? '');

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE recreacao SET tema = ?, personagens = ?, explicacao = ? WHERE id = ?");
            $stmt->bind_param("sssi", $tema, $personagens, $explicacao, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO recreacao (edicao_id, tipo, tema, personagens, explicacao) VALUES (?, 'olimpiada', ?, ?, ?)");
            $stmt->bind_param("isss", $edicao_id_param, $tema, $personagens, $explicacao);
        }
        if ($stmt->execute()) {
            $mensagem_sucesso = "Olimpíada salva com sucesso!";
        } else {
            $mensagem_erro = "Erro ao salvar olimpíada.";
        }
    }

    if ($acao === 'excluir_item') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM recreacao WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Item removido com sucesso!";
            } else {
                $mensagem_erro = "Erro ao remover item.";
            }
        }
    }

    if ($acao === 'resgatar_recreacao') {
        $edicao_origem_id = intval($_POST['edicao_origem_id'] ?? 0);
        $tipo_filtro      = $_POST['tipo_resgate'] ?? 'todos';

        if ($edicao_origem_id > 0 && $edicao_id_param > 0) {
            $tipos_validos = ['teatro', 'brincadeira', 'olimpiada'];
            if (in_array($tipo_filtro, $tipos_validos)) {
                $stmt_copy = $conn->prepare("
                    INSERT INTO recreacao (edicao_id, tipo, nome, explicacao, momento, texto_teatro, tema, personagens)
                    SELECT ?, tipo, nome, explicacao, momento, texto_teatro, tema, personagens
                    FROM recreacao
                    WHERE edicao_id = ? AND tipo = ?
                    ORDER BY id ASC
                ");
                $stmt_copy->bind_param("iis", $edicao_id_param, $edicao_origem_id, $tipo_filtro);
            } else {
                $stmt_copy = $conn->prepare("
                    INSERT INTO recreacao (edicao_id, tipo, nome, explicacao, momento, texto_teatro, tema, personagens)
                    SELECT ?, tipo, nome, explicacao, momento, texto_teatro, tema, personagens
                    FROM recreacao
                    WHERE edicao_id = ?
                    ORDER BY id ASC
                ");
                $stmt_copy->bind_param("ii", $edicao_id_param, $edicao_origem_id);
            }

            if ($stmt_copy->execute()) {
                $qtd = $stmt_copy->affected_rows;
                if ($qtd > 0) {
                    $mensagem_sucesso = "{$qtd} registro(s) de recreação resgatado(s) com sucesso!";
                } else {
                    $mensagem_erro = "Nenhum item encontrado na edição/categoria selecionada.";
                }
            } else {
                $mensagem_erro = "Erro ao resgatar dados: " . $conn->error;
            }
        }
    }
}

// 3. Buscar Nome da Edição
$nome_completo_edicao = "";
if ($edicao_id_param > 0) {
    $stmt_info = $conn->prepare("SELECT e.nome AS edicao_nome, m.nome AS modalidade_nome, e.modalidade_id 
                                 FROM edicoes e 
                                 LEFT JOIN modalidades m ON e.modalidade_id = m.id 
                                 WHERE e.id = ?");
    if ($stmt_info) {
        $stmt_info->bind_param("i", $edicao_id_param);
        if ($stmt_info->execute()) {
            $res = $stmt_info->get_result()->fetch_assoc();
            if ($res) {
                $mod = $res['modalidade_nome'] ?? '';
                $ed  = $res['edicao_nome'] ?? '';
                if ($modalidade_id_param === 0 && !empty($res['modalidade_id'])) {
                    $modalidade_id_param = intval($res['modalidade_id']);
                }
                $nome_completo_edicao = trim("$mod - $ed", " -");
            }
        }
    }
}

// 4. Buscar Edições Anteriores da Mesma Modalidade para Resgate
$edicoes_anteriores = [];
if ($edicao_id_param > 0 && $modalidade_id_param > 0) {
    $stmt_ed_ant = $conn->prepare("
        SELECT DISTINCT e.id, e.ano, e.nome 
        FROM edicoes e
        INNER JOIN recreacao r ON r.edicao_id = e.id
        WHERE e.modalidade_id = ? AND e.id != ?
        ORDER BY e.ano DESC, e.id DESC
    ");
    $stmt_ed_ant->bind_param("ii", $modalidade_id_param, $edicao_id_param);
    $stmt_ed_ant->execute();
    $edicoes_anteriores = $stmt_ed_ant->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 5. Buscar Dados da Edição Atual
$filtro_edicao = $edicao_id_param > 0 ? "AND edicao_id = $edicao_id_param " : "";
$brincadeiras  = $conn->query("SELECT * FROM recreacao WHERE tipo = 'brincadeira' {$filtro_edicao}ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$teatros       = $conn->query("SELECT * FROM recreacao WHERE tipo = 'teatro' {$filtro_edicao}ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$olimpiadas    = $conn->query("SELECT * FROM recreacao WHERE tipo = 'olimpiada' {$filtro_edicao}ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

$url_voltar = "dashboard-geral.php";
if ($edicao_id_param > 0) {
    $url_voltar = "edicao-detalhes.php?edicao_id={$edicao_id_param}" . ($modalidade_id_param > 0 ? "&modalidade_id={$modalidade_id_param}" : "");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Recreação - ÉFATA</title>
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
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-main);
            color: var(--navy-dark);
            margin: 0;
            padding: 0;
        }

        body.modal-open {
            overflow: hidden !important;
        }

        .modal-body-scroll {
            max-height: 70vh;
            overflow-y: auto;
            overscroll-behavior: contain;
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

        /* BOTÕES DA PALETA ÉFATA */
        .btn-gold {
            background-color: var(--gold-primary);
            color: #ffffff;
            font-weight: 600;
            border: 1.5px solid var(--gold-primary);
            transition: all 0.2s ease;
        }
        .btn-gold:hover {
            background-color: var(--gold-hover);
            border-color: var(--gold-hover);
            color: #ffffff;
        }

        .btn-navy {
            background-color: var(--navy-dark);
            color: #ffffff;
            font-weight: 600;
            border: 1.5px solid var(--navy-dark);
            transition: all 0.2s ease;
        }
        .btn-navy:hover {
            background-color: var(--navy-hover);
            border-color: var(--navy-hover);
            color: #ffffff;
        }

        .btn-outline-navy {
            border: 1.5px solid var(--navy-dark);
            color: var(--navy-dark);
            background: #ffffff;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-outline-navy:hover {
            background-color: var(--navy-dark);
            color: #ffffff;
        }

        .btn-outline-gold {
            border: 1.5px solid var(--gold-primary);
            color: #8C6A28;
            background: #FFFDF8;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-outline-gold:hover {
            background-color: var(--gold-primary);
            color: #ffffff;
        }

        /* BOTÃO DISCRETO RESGATAR EDIÇÃO */
        .btn-resgatar-discreto {
            background-color: transparent;
            color: #64748B;
            border: 1px solid #E2E8F0;
            font-weight: 500;
            font-size: 0.82rem;
            transition: all 0.2s ease;
        }
        .btn-resgatar-discreto:hover {
            background-color: rgba(197, 160, 89, 0.08);
            border-color: var(--gold-primary);
            color: var(--navy-dark);
        }
        .btn-resgatar-discreto i {
            color: var(--gold-primary);
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

        /* ABAS RESPONSIVAS (CABEM SEM CORTAR NO CELULAR) */
        .nav-pills-efata {
            background: #ffffff;
            border: 1.5px solid var(--border-color);
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
            font-size: 0.9rem;
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
            box-shadow: 0 4px 10px rgba(10, 25, 47, 0.14);
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

        /* CARDS MINIMALISTAS */
        .panel-minimal {
            background: #ffffff;
            border: 1px solid #EAECEF;
            border-radius: 18px;
        }

        .card-minimal {
            background: #ffffff;
            border: 1px solid #EAECEF;
            border-top: 3px solid var(--gold-primary);
            border-radius: 14px;
            padding: 1.25rem;
            transition: all 0.2s ease;
        }
        .card-minimal:hover {
            border-color: #CBD5E1;
            box-shadow: 0 6px 16px -4px rgba(10, 25, 47, 0.06);
        }

        .badge-momento {
            background-color: #FDF8EE;
            color: #8C6A28;
            border: 1px solid #F2E3C2;
            font-weight: 600;
            font-size: 0.74rem;
            padding: 4px 10px;
            border-radius: 50px;
        }

        .btn-icon-minimal {
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
        .btn-icon-minimal:hover {
            background: #F1F5F9;
            color: var(--navy-dark);
        }
        .btn-icon-minimal.delete:hover {
            background: #FEF2F2;
            border-color: #FECACA;
            color: #DC2626;
        }

        .texto-conteudo {
            white-space: pre-line;
            word-break: break-word;
            overflow-wrap: break-word;
            line-height: 1.65;
        }

        .teatro-preview {
            max-height: 88px;
            overflow: hidden;
            position: relative;
            color: #475569;
        }
        .teatro-preview::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 36px;
            background: linear-gradient(transparent, #ffffff);
        }

        .section-actions-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .delete-icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background-color: #FEF2F2;
            color: #DC2626;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 12px;
        }

        @media (max-width: 767.98px) {
            .nav-pills-efata .nav-link {
                flex-direction: column;
                padding: 8px 4px;
                font-size: 0.78rem;
                gap: 3px;
            }
            .section-actions-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
            }
            .section-actions-group .btn {
                justify-content: center;
                padding: 9px 8px;
                font-size: 0.82rem;
            }
        }

        /* IMPRESSÃO / PDF ESTILO DOCUMENTO WORD (SEM BORDAS, ARIAL 14) */
        .print-only-sheet { display: none; }

        @media print {
            @page {
                size: A4 portrait;
                margin: 1.8cm 2cm;
            }

            .no-print,
            .top-navbar,
            .page-header-bar,
            .nav-pills-efata,
            .alert,
            main.screen-only-main,
            .modal,
            .modal-backdrop {
                display: none !important;
            }

            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: Arial, Helvetica, sans-serif !important;
                font-size: 14pt !important;
                line-height: 1.6 !important;
                overflow: visible !important;
            }

            .print-only-sheet {
                display: block !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .word-header {
                text-align: center;
                border-bottom: 1.5pt solid #000000;
                padding-bottom: 10pt;
                margin-bottom: 20pt;
            }

            .word-header h1 {
                font-size: 18pt;
                font-weight: bold;
                text-transform: uppercase;
                margin: 0 0 4pt 0;
                color: #000000;
            }

            .word-header p {
                font-size: 12pt;
                margin: 0;
                color: #333333;
            }

            .word-item {
                margin-bottom: 20pt;
                padding-bottom: 14pt;
                border-bottom: 0.75pt solid #CCCCCC;
                page-break-inside: auto;
            }

            .word-item:last-child {
                border-bottom: none;
            }

            .word-item-title {
                font-size: 15pt;
                font-weight: bold;
                color: #000000;
                margin: 0 0 4pt 0;
                page-break-after: avoid;
            }

            .word-item-meta {
                font-size: 12pt;
                font-weight: bold;
                color: #333333;
                margin: 0 0 8pt 0;
                page-break-after: avoid;
            }

            .word-item-body {
                font-family: Arial, Helvetica, sans-serif;
                font-size: 14pt;
                white-space: pre-line;
                text-align: justify;
                color: #000000;
                word-break: break-word;
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

    <!-- CABEÇALHO -->
    <div class="bg-white border-bottom py-3 mb-4 page-header-bar no-print">
        <div class="container d-flex flex-wrap align-items-center justify-content-between gap-3" style="max-width: 1140px;">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($url_voltar) ?>" class="btn-only-arrow" title="Voltar">
                    <i class="bi bi-arrow-left fs-5"></i>
                </a>
                <div>
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-controller" style="color: var(--gold-primary);"></i>Recreação
                    </h5>
                    <small class="text-muted fw-medium">
                        <?= !empty($nome_completo_edicao) ? htmlspecialchars($nome_completo_edicao) : 'Teatros, Brincadeiras e Olimpíadas' ?>
                    </small>
                </div>
            </div>

            <?php if ($edicao_id_param > 0): ?>
                <button type="button" class="btn btn-resgatar-discreto rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-1" onclick="abrirModalResgatar('todos')" title="Importar recreação de outra edição">
                    <i class="bi bi-arrow-repeat"></i>
                    <span>Resgatar edição</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL EM TELA -->
    <main class="container pb-5 screen-only-main no-print" style="max-width: 1140px;">
        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensagem_sucesso) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($mensagem_erro) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- NAVEGAÇÃO POR ABAS -->
        <ul class="nav nav-pills-efata mb-4 shadow-sm" id="pills-tab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pills-teatros-tab" data-bs-toggle="pill" data-bs-target="#pills-teatros" type="button" role="tab">
                    <span><i class="bi bi-masks"></i> Teatros</span>
                    <span class="badge-count"><?= count($teatros) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-brincadeiras-tab" data-bs-toggle="pill" data-bs-target="#pills-brincadeiras" type="button" role="tab">
                    <span><i class="bi bi-controller"></i> Brincadeiras</span>
                    <span class="badge-count"><?= count($brincadeiras) ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pills-olimpiadas-tab" data-bs-toggle="pill" data-bs-target="#pills-olimpiadas" type="button" role="tab">
                    <span><i class="bi bi-trophy"></i> Olimpíadas</span>
                    <span class="badge-count"><?= count($olimpiadas) ?></span>
                </button>
            </li>
        </ul>

        <!-- CONTEÚDO DAS ABAS -->
        <div class="tab-content" id="pills-tabContent">

            <!-- TAB 1: TEATROS -->
            <div class="tab-pane fade show active" id="pills-teatros" role="tabpanel">
                <div class="panel-minimal p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3 mb-4 border-bottom pb-3">
                        <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-masks" style="color: var(--gold-primary);"></i>Teatros
                        </h5>
                        <div class="section-actions-group">
                            <button type="button" class="btn btn-outline-navy rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2" onclick="imprimirCategoria('teatros')">
                                <i class="bi bi-file-earmark-pdf-fill" style="color: var(--gold-primary);"></i>
                                <span>PDF Teatros</span>
                            </button>
                            <button type="button" class="btn btn-gold rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2" onclick="abrirModalTeatro()">
                                <i class="bi bi-plus-lg"></i>
                                <span>Novo Teatro</span>
                            </button>
                        </div>
                    </div>

                    <?php if (empty($teatros)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-masks fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                            Nenhum teatro cadastrado.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($teatros as $t): ?>
                                <div class="col-12 col-md-6">
                                    <div class="card-minimal h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                                <h6 class="fw-bold text-dark m-0 texto-conteudo" style="font-size: 1.05rem;"><?= htmlspecialchars($t['nome']) ?></h6>
                                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                    <button type="button" class="btn-icon-minimal" onclick='editarTeatro(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)' title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button type="button" class="btn-icon-minimal delete" onclick='confirmarExclusao(<?= intval($t['id']) ?>, <?= htmlspecialchars(json_encode($t['nome']), ENT_QUOTES, 'UTF-8') ?>)' title="Excluir">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <span class="badge-momento">Momento: <?= htmlspecialchars($t['momento']) ?></span>
                                            </div>

                                            <div class="teatro-preview small texto-conteudo mb-3">
                                                <?= htmlspecialchars($t['texto_teatro']) ?>
                                            </div>
                                        </div>

                                        <div class="pt-2 border-top">
                                            <button type="button" class="btn btn-outline-navy w-100 rounded-pill fw-semibold btn-sm py-2" onclick='visualizarTeatroCompleto(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)'>
                                                <i class="bi bi-book me-1" style="color: var(--gold-primary);"></i> Ler Roteiro Completo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 2: BRINCADEIRAS -->
            <div class="tab-pane fade" id="pills-brincadeiras" role="tabpanel">
                <div class="panel-minimal p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3 mb-4 border-bottom pb-3">
                        <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-controller" style="color: var(--gold-primary);"></i>Lista de Brincadeiras
                        </h5>
                        <div class="section-actions-group">
                            <button type="button" class="btn btn-outline-navy rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2" onclick="imprimirCategoria('brincadeiras')">
                                <i class="bi bi-file-earmark-pdf-fill" style="color: var(--gold-primary);"></i>
                                <span>PDF Brincadeiras</span>
                            </button>
                            <button type="button" class="btn btn-gold rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2" onclick="abrirModalBrincadeira()">
                                <i class="bi bi-plus-lg"></i>
                                <span>Nova Brincadeira</span>
                            </button>
                        </div>
                    </div>

                    <?php if (empty($brincadeiras)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-controller fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                            Nenhuma brincadeira cadastrada.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($brincadeiras as $b): ?>
                                <div class="col-12 col-md-6">
                                    <div class="card-minimal h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <h6 class="fw-bold text-dark m-0 texto-conteudo" style="font-size: 1.05rem;"><?= htmlspecialchars($b['nome']) ?></h6>
                                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                <button type="button" class="btn-icon-minimal" onclick='editarBrincadeira(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)' title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn-icon-minimal delete" onclick='confirmarExclusao(<?= intval($b['id']) ?>, <?= htmlspecialchars(json_encode($b['nome']), ENT_QUOTES, 'UTF-8') ?>)' title="Excluir">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="small text-secondary m-0 texto-conteudo"><?= htmlspecialchars($b['explicacao']) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- TAB 3: OLIMPÍADAS -->
            <div class="tab-pane fade" id="pills-olimpiadas" role="tabpanel">
                <div class="panel-minimal p-3 p-md-4">
                    <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3 mb-4 border-bottom pb-3">
                        <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                            <i class="bi bi-trophy-fill" style="color: var(--gold-primary);"></i>Olimpíadas Cadastradas
                        </h5>
                        <div class="section-actions-group">
                            <button type="button" class="btn btn-outline-navy rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2" onclick="imprimirCategoria('olimpiadas')">
                                <i class="bi bi-file-earmark-pdf-fill" style="color: var(--gold-primary);"></i>
                                <span>PDF Olimpíadas</span>
                            </button>
                            <button type="button" class="btn btn-gold rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2" onclick="abrirModalOlimpiada()">
                                <i class="bi bi-plus-lg"></i>
                                <span>Nova Olimpíada</span>
                            </button>
                        </div>
                    </div>

                    <?php if (empty($olimpiadas)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-trophy fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                            Nenhuma olimpíada cadastrada.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($olimpiadas as $o): ?>
                                <div class="col-12 col-md-6">
                                    <div class="card-minimal h-100">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                            <h6 class="fw-bold text-dark m-0 texto-conteudo" style="font-size: 1.05rem;">Tema: <?= htmlspecialchars($o['tema']) ?></h6>
                                            <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                                <button type="button" class="btn-icon-minimal" onclick='editarOlimpiada(<?= htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8') ?>)' title="Editar">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button type="button" class="btn-icon-minimal delete" onclick='confirmarExclusao(<?= intval($o['id']) ?>, <?= htmlspecialchars(json_encode($o['tema']), ENT_QUOTES, 'UTF-8') ?>)' title="Excluir">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="small mb-2 texto-conteudo">
                                            <strong class="text-dark">Personagens:</strong><br>
                                            <span class="text-secondary"><?= htmlspecialchars($o['personagens']) ?></span>
                                        </div>
                                        <div class="small texto-conteudo pt-2 border-top">
                                            <strong class="text-dark">Brincadeiras:</strong><br>
                                            <span class="text-secondary"><?= htmlspecialchars($o['explicacao']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- ÁREA DE IMPRESSÃO / PDF ESTILO WORD (UM DOCUMENTO POR CATEGORIA) -->
    <div id="printAreaWord" class="print-only-sheet"></div>

    <!-- MODAL RESGATAR RECREAÇÃO DE OUTRA EDIÇÃO -->
    <div class="modal fade no-print" id="modalResgatarEdicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <input type="hidden" name="acao" value="resgatar_recreacao">

                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat text-warning fs-5"></i>
                        <span>Resgatar Recreação de Outra Edição</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <?php if (empty($edicoes_anteriores)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-info-circle fs-2 d-block mb-2" style="color: var(--gold-primary);"></i>
                            <p class="mb-0 small">Nenhuma outra edição desta modalidade possui teatros, brincadeiras ou olimpíadas cadastradas ainda.</p>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small mb-2">Selecione o ano / edição de origem:</label>
                            <select name="edicao_origem_id" class="form-select rounded-3 p-2" required>
                                <option value="">Escolha uma edição...</option>
                                <?php foreach ($edicoes_anteriores as $ed_ant): ?>
                                    <option value="<?= $ed_ant['id'] ?>">
                                        <?= htmlspecialchars($ed_ant['ano'] . ' - ' . $ed_ant['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small mb-2">O que deseja importar?</label>
                            <select name="tipo_resgate" id="select_tipo_resgate" class="form-select rounded-3 p-2">
                                <option value="todos">Tudo (Teatros, Brincadeiras e Olimpíadas)</option>
                                <option value="teatro">Apenas Teatros</option>
                                <option value="brincadeira">Apenas Brincadeiras</option>
                                <option value="olimpiada">Apenas Olimpíadas</option>
                            </select>
                        </div>

                        <div class="alert alert-warning border-0 rounded-3 small mb-0 d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-circle-fill mt-1"></i>
                            <div>
                                Os itens selecionados serão duplicados para esta edição. Alterações feitas aqui <strong>não</strong> afetarão a edição anterior.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <?php if (!empty($edicoes_anteriores)): ?>
                        <button type="submit" class="btn btn-gold rounded-pill px-4">
                            <i class="bi bi-check2-circle me-1"></i> Importar
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PARA LEITURA CONFORTÁVEL DO TEATRO COMPLETO -->
    <div class="modal fade no-print" id="modalVerTeatro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <div class="modal-header modal-header-custom p-3 p-md-4">
                    <div>
                        <span class="badge mb-1" style="background-color: var(--gold-primary); color: #ffffff;" id="v_t_momento"></span>
                        <h5 class="modal-title fw-bold text-white m-0" id="v_t_nome"></h5>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-body-scroll p-3 p-md-4 fs-6 texto-conteudo bg-white" id="v_t_texto"></div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" onclick="imprimirTeatroUnico()" class="btn btn-outline-navy rounded-pill px-4 btn-sm">
                        <i class="bi bi-printer me-1" style="color: var(--gold-primary);"></i> Imprimir Este Teatro
                    </button>
                    <button type="button" class="btn btn-navy rounded-pill px-4 btn-sm" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL BRINCADEIRA -->
    <div class="modal fade no-print" id="modalBrincadeira" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form method="POST" class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <input type="hidden" name="acao" value="salvar_brincadeira">
                <input type="hidden" name="id" id="b_id" value="0">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold text-white m-0" id="modalBrincadeiraTitulo">Cadastrar Brincadeira</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-body-scroll p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome da Brincadeira </label>
                        <input type="text" name="nome" id="b_nome" class="form-control" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Explicação da Brincadeira </label>
                        <textarea name="explicacao" id="b_explicacao" class="form-control" rows="5" required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold rounded-pill px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL TEATRO (CADASTRO / EDIÇÃO) -->
    <div class="modal fade no-print" id="modalTeatro" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <form method="POST" class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <input type="hidden" name="acao" value="salvar_teatro">
                <input type="hidden" name="id" id="t_id" value="0">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold text-white m-0" id="modalTeatroTitulo">Cadastrar Teatro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-body-scroll p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nome do Teatro </label>
                        <input type="text" name="nome" id="t_nome" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Em que momento é usado </label>
                        <input type="text" name="momento" id="t_momento" class="form-control" placeholder="Ex: Sábado à Noite / Abertura" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Texto do Teatro </label>
                        <textarea name="texto_teatro" id="t_texto_teatro" class="form-control" rows="8" required placeholder="Cole ou digite aqui o roteiro completo da peça..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold rounded-pill px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL OLIMPÍADA -->
    <div class="modal fade no-print" id="modalOlimpiada" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <form method="POST" class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <input type="hidden" name="acao" value="salvar_olimpiada">
                <input type="hidden" name="id" id="o_id" value="0">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold text-white m-0" id="modalOlimpiadaTitulo">Cadastrar Olimpíada</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-body-scroll p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tema da Olimpíada </label>
                        <input type="text" name="tema" id="o_tema" class="form-control" placeholder= "">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Personagens </label>
                        <textarea name="personagens" id="o_personagens" class="form-control" rows="3" placeholder="" required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold">Brincadeiras da Olimpíadas </label>
                        <textarea name="explicacao" id="o_explicacao" class="form-control" rows="4" placeholder="Lista das brincadeiras..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold rounded-pill px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CONFIRMAR EXCLUSÃO -->
    <div class="modal fade no-print" id="modalConfirmarExclusao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form method="POST" class="modal-content border-0 rounded-4 overflow-hidden shadow-lg text-center">
                <input type="hidden" name="acao" value="excluir_item">
                <input type="hidden" name="id" id="excluir_id" value="0">

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
        const dadosTeatros      = <?= json_encode($teatros, JSON_UNESCAPED_UNICODE) ?>;
        const dadosBrincadeiras = <?= json_encode($brincadeiras, JSON_UNESCAPED_UNICODE) ?>;
        const dadosOlimpiadas   = <?= json_encode($olimpiadas, JSON_UNESCAPED_UNICODE) ?>;
        const subtituloEdicao   = <?= json_encode($nome_completo_edicao, JSON_UNESCAPED_UNICODE) ?>;
        let teatroAtualAberto   = null;

        function abrirModalResgatar(tipoPadrao) {
            const selectTipo = document.getElementById('select_tipo_resgate');
            if (selectTipo && tipoPadrao) {
                selectTipo.value = tipoPadrao;
            }
            new bootstrap.Modal(document.getElementById('modalResgatarEdicao')).show();
        }

        function escaparHtml(texto) {
            const div = document.createElement('div');
            div.textContent = texto || '';
            return div.innerHTML;
        }

        function imprimirCategoria(categoria) {
            const area = document.getElementById('printAreaWord');
            let tituloDoc = '';
            let corpoHtml = '';

            if (categoria === 'teatros') {
                tituloDoc = 'Teatros e Roteiros';
                if (!dadosTeatros.length) {
                    corpoHtml = '<p class="word-item-body">Nenhum teatro cadastrado.</p>';
                } else {
                    dadosTeatros.forEach(t => {
                        corpoHtml += `
                            <div class="word-item">
                                <div class="word-item-title">${escaparHtml(t.nome)}</div>
                                <div class="word-item-meta">Momento: ${escaparHtml(t.momento)}</div>
                                <div class="word-item-body">${escaparHtml(t.texto_teatro)}</div>
                            </div>
                        `;
                    });
                }
            } else if (categoria === 'brincadeiras') {
                tituloDoc = 'Lista de Brincadeiras';
                if (!dadosBrincadeiras.length) {
                    corpoHtml = '<p class="word-item-body">Nenhuma brincadeira cadastrada.</p>';
                } else {
                    dadosBrincadeiras.forEach(b => {
                        corpoHtml += `
                            <div class="word-item">
                                <div class="word-item-title">${escaparHtml(b.nome)}</div>
                                <div class="word-item-body">${escaparHtml(b.explicacao)}</div>
                            </div>
                        `;
                    });
                }
            } else if (categoria === 'olimpiadas') {
                tituloDoc = 'Olimpíadas';
                if (!dadosOlimpiadas.length) {
                    corpoHtml = '<p class="word-item-body">Nenhuma olimpíada cadastrada.</p>';
                } else {
                    dadosOlimpiadas.forEach(o => {
                        corpoHtml += `
                            <div class="word-item">
                                <div class="word-item-title">Tema: ${escaparHtml(o.tema)}</div>
                                <div class="word-item-meta">Personagens:</div>
                                <div class="word-item-body" style="margin-bottom: 10pt;">${escaparHtml(o.personagens)}</div>
                                <div class="word-item-meta">Brincadeiras:</div>
                                <div class="word-item-body">${escaparHtml(o.explicacao)}</div>
                            </div>
                        `;
                    });
                }
            }

            area.innerHTML = `
                <div class="word-header">
                    <h1>${escaparHtml(tituloDoc)}</h1>
                    ${subtituloEdicao ? `<p>${escaparHtml(subtituloEdicao)}</p>` : ''}
                </div>
                ${corpoHtml}
            `;

            window.print();
        }

        function imprimirTeatroUnico() {
            if (!teatroAtualAberto) return;
            const area = document.getElementById('printAreaWord');
            area.innerHTML = `
                <div class="word-header">
                    <h1>${escaparHtml(teatroAtualAberto.nome)}</h1>
                    <p>Momento: ${escaparHtml(teatroAtualAberto.momento)}${subtituloEdicao ? ' • ' + escaparHtml(subtituloEdicao) : ''}</p>
                </div>
                <div class="word-item">
                    <div class="word-item-body">${escaparHtml(teatroAtualAberto.texto_teatro)}</div>
                </div>
            `;
            window.print();
        }

        function visualizarTeatroCompleto(t) {
            teatroAtualAberto = t;
            document.getElementById('v_t_nome').innerText    = t.nome;
            document.getElementById('v_t_momento').innerText = "Momento: " + t.momento;
            document.getElementById('v_t_texto').innerText   = t.texto_teatro;
            new bootstrap.Modal(document.getElementById('modalVerTeatro')).show();
        }

        function abrirModalBrincadeira() {
            document.getElementById('modalBrincadeiraTitulo').innerText = 'Cadastrar Brincadeira';
            document.getElementById('b_id').value         = '0';
            document.getElementById('b_nome').value       = '';
            document.getElementById('b_explicacao').value = '';
            new bootstrap.Modal(document.getElementById('modalBrincadeira')).show();
        }
        function editarBrincadeira(d) {
            document.getElementById('modalBrincadeiraTitulo').innerText = 'Editar Brincadeira';
            document.getElementById('b_id').value         = d.id;
            document.getElementById('b_nome').value       = d.nome || '';
            document.getElementById('b_explicacao').value = d.explicacao || '';
            new bootstrap.Modal(document.getElementById('modalBrincadeira')).show();
        }

        function abrirModalTeatro() {
            document.getElementById('modalTeatroTitulo').innerText = 'Cadastrar Teatro';
            document.getElementById('t_id').value           = '0';
            document.getElementById('t_nome').value         = '';
            document.getElementById('t_momento').value      = '';
            document.getElementById('t_texto_teatro').value = '';
            new bootstrap.Modal(document.getElementById('modalTeatro')).show();
        }
        function editarTeatro(d) {
            document.getElementById('modalTeatroTitulo').innerText = 'Editar Teatro';
            document.getElementById('t_id').value           = d.id;
            document.getElementById('t_nome').value         = d.nome || '';
            document.getElementById('t_momento').value      = d.momento || '';
            document.getElementById('t_texto_teatro').value = d.texto_teatro || '';
            new bootstrap.Modal(document.getElementById('modalTeatro')).show();
        }

        function abrirModalOlimpiada() {
            document.getElementById('modalOlimpiadaTitulo').innerText = 'Cadastrar Olimpíada';
            document.getElementById('o_id').value          = '0';
            document.getElementById('o_tema').value        = '';
            document.getElementById('o_personagens').value = '';
            document.getElementById('o_explicacao').value  = '';
            new bootstrap.Modal(document.getElementById('modalOlimpiada')).show();
        }
        function editarOlimpiada(d) {
            document.getElementById('modalOlimpiadaTitulo').innerText = 'Editar Olimpíada';
            document.getElementById('o_id').value          = d.id;
            document.getElementById('o_tema').value        = d.tema || '';
            document.getElementById('o_personagens').value = d.personagens || '';
            document.getElementById('o_explicacao').value  = d.explicacao || '';
            new bootstrap.Modal(document.getElementById('modalOlimpiada')).show();
        }

        function confirmarExclusao(id, titulo) {
            document.getElementById('excluir_id').value = id;
            document.getElementById('excluir_preview').innerText = '“' + titulo + '”';
            new bootstrap.Modal(document.getElementById('modalConfirmarExclusao')).show();
        }
    </script>
</body>
</html>