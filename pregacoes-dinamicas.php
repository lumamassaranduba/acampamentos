<?php
// pregacoes-dinamicas.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nome_usuario   = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Coordenador';
$perfil_usuario = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENAÇÃO'));

$edicao_id     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

$mensagem = '';
$erro = '';

// --- NOME DA EDIÇÃO PARA CABEÇALHO, IMPRESSÃO E MODALIDADE ---
$nome_completo_edicao = "";
if ($edicao_id > 0) {
    $stmt_info = $conn->prepare("SELECT e.nome AS edicao_nome, m.nome AS modalidade_nome, e.modalidade_id 
                                 FROM edicoes e 
                                 LEFT JOIN modalidades m ON e.modalidade_id = m.id 
                                 WHERE e.id = ?");
    if ($stmt_info) {
        $stmt_info->bind_param("i", $edicao_id);
        if ($stmt_info->execute()) {
            $res = $stmt_info->get_result()->fetch_assoc();
            if ($res) {
                $mod = $res['modalidade_nome'] ?? '';
                $ed  = $res['edicao_nome'] ?? '';
                if ($modalidade_id === 0 && !empty($res['modalidade_id'])) {
                    $modalidade_id = intval($res['modalidade_id']);
                }
                $nome_completo_edicao = trim("$mod - $ed", " -");
            }
        }
    }
}

// --- PROCESSAMENTO DO FORMULÁRIO (SALVAR / EDITAR / EXCLUIR / RESGATAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'salvar') {
        $id              = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $tema            = trim($_POST['tema'] ?? '');
        $pregacao        = trim($_POST['pregacao'] ?? '');
        $dinamica_titulo = trim($_POST['dinamica_titulo'] ?? '');
        $dinamica_desc   = trim($_POST['dinamica_desc'] ?? '');
        $ordem           = intval($_POST['ordem'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE pregacoes_dinamicas SET tema=?, pregacao=?, dinamica_titulo=?, dinamica_desc=?, ordem=? WHERE id=?");
            $stmt->bind_param("ssssii", $tema, $pregacao, $dinamica_titulo, $dinamica_desc, $ordem, $id);
            if ($stmt->execute()) {
                $mensagem = "Registro atualizado com sucesso!";
            } else {
                $erro = "Erro ao atualizar: " . $conn->error;
            }
        } else {
            $orientacoes = '';
            $stmt = $conn->prepare("INSERT INTO pregacoes_dinamicas (edicao_id, ordem, tema, pregacao, orientacoes, dinamica_titulo, dinamica_desc) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssss", $edicao_id, $ordem, $tema, $pregacao, $orientacoes, $dinamica_titulo, $dinamica_desc);
            if ($stmt->execute()) {
                $mensagem = "Novo conteúdo cadastrado com sucesso!";
            } else {
                $erro = "Erro ao cadastrar: " . $conn->error;
            }
        }
    } elseif ($action === 'excluir') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM pregacoes_dinamicas WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $mensagem = "Registro excluído com sucesso!";
            } else {
                $erro = "Erro ao excluir: " . $conn->error;
            }
        }
    } elseif ($action === 'resgatar') {
        $edicao_origem_id = intval($_POST['edicao_origem_id'] ?? 0);
        if ($edicao_origem_id > 0 && $edicao_id > 0) {
            $stmt_copy = $conn->prepare("
                INSERT INTO pregacoes_dinamicas (edicao_id, ordem, tema, pregacao, orientacoes, dinamica_titulo, dinamica_desc)
                SELECT ?, ordem, tema, pregacao, orientacoes, dinamica_titulo, dinamica_desc
                FROM pregacoes_dinamicas
                WHERE edicao_id = ?
                ORDER BY ordem ASC, id ASC
            ");
            $stmt_copy->bind_param("ii", $edicao_id, $edicao_origem_id);
            if ($stmt_copy->execute()) {
                $qtd = $stmt_copy->affected_rows;
                if ($qtd > 0) {
                    $mensagem = "{$qtd} pregação(ões)/dinâmica(s) resgatada(s) com sucesso!";
                } else {
                    $erro = "A edição selecionada não possui pregações cadastradas.";
                }
            } else {
                $erro = "Erro ao resgatar dados: " . $conn->error;
            }
        }
    }
}

// --- BUSCA DE EDIÇÕES ANTERIORES DA MESMA MODALIDADE PARA RESGATE ---
$edicoes_anteriores = [];
if ($edicao_id > 0 && $modalidade_id > 0) {
    $stmt_ed_ant = $conn->prepare("
        SELECT DISTINCT e.id, e.ano, e.nome 
        FROM edicoes e
        INNER JOIN pregacoes_dinamicas pd ON pd.edicao_id = e.id
        WHERE e.modalidade_id = ? AND e.id != ?
        ORDER BY e.ano DESC, e.id DESC
    ");
    $stmt_ed_ant->bind_param("ii", $modalidade_id, $edicao_id);
    $stmt_ed_ant->execute();
    $edicoes_anteriores = $stmt_ed_ant->get_result()->fetch_all(MYSQLI_ASSOC);
}

// --- CONSULTA DOS REGISTROS ---
$conteudos = [];
if ($edicao_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM pregacoes_dinamicas WHERE edicao_id = ? OR edicao_id IS NULL OR edicao_id = 0 ORDER BY ordem ASC, id ASC");
    $stmt->bind_param("i", $edicao_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $conteudos = $result->fetch_all(MYSQLI_ASSOC);
    }
} else {
    $result = $conn->query("SELECT * FROM pregacoes_dinamicas ORDER BY ordem ASC, id ASC");
    if ($result) {
        $conteudos = $result->fetch_all(MYSQLI_ASSOC);
    }
}

$url_voltar = "dashboard-geral.php";
if ($edicao_id > 0) {
    $url_voltar = "edicao-detalhes.php?edicao_id={$edicao_id}" . ($modalidade_id > 0 ? "&modalidade_id={$modalidade_id}" : "");
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>Pregações e Dinâmicas - ÉFATA</title>
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

        /* TRAVA O FUNDO QUANDO O MODAL ESTIVER ABERTO */
        body.modal-open {
            overflow: hidden !important;
        }

        #modalConteudo .modal-body {
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

        /* BOTÕES DA PALETA */
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

        /* CARDS MINIMALISTAS */
        .card-minimal {
            background: #ffffff;
            border: 1px solid #EAECEF;
            border-radius: 14px;
            transition: border-color 0.2s ease;
        }

        .card-minimal:hover {
            border-color: #CBD5E1;
        }

        .tag-minimal {
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            padding: 4px 10px;
            border-radius: 50px;
            background-color: #F1F5F9;
            color: #475569;
        }

        .tag-minimal-gold {
            background-color: #FDF8EE;
            color: #9A742E;
            border: 1px solid #F2E3C2;
        }

        .section-label-minimal {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748B;
            margin-bottom: 6px;
        }

        .box-minimal-note {
            background-color: #F8FAFC;
            border-left: 3px solid #94A3B8;
            border-radius: 0 10px 10px 0;
            padding: 12px 16px;
        }

        .box-minimal-dinamica {
            background-color: #FFFDF8;
            border-left: 3px solid var(--gold-primary);
            border-radius: 0 10px 10px 0;
            padding: 12px 16px;
        }

        .texto-formatado {
            white-space: pre-line;
            line-height: 1.68;
            font-size: 0.96rem;
            color: #1E293B;
            word-break: break-word;
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

        /* ÍCONE DE ALERTA EXCLUSÃO */
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

        /* RESPONSIVIDADE MOBILE E TABLET */
        .header-actions-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 767.98px) {
            .header-actions-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
                padding-top: 12px;
                border-top: 1px solid #F1F5F9;
            }
            .header-actions-group .btn-resgatar-discreto {
                grid-column: span 2;
            }
            .header-actions-group .btn {
                justify-content: center;
                padding: 10px 10px;
                font-size: 0.84rem;
            }
            .card-minimal {
                padding: 1.15rem !important;
            }
        }

        /* IMPRESSÃO / PDF ESTILO DOCUMENTO WORD (SEM BORDAS) */
        @media print {
            @page {
                margin: 1.8cm 2cm;
                size: A4 portrait;
            }

            .no-print,
            .top-navbar,
            .page-header-bar,
            .alert,
            .tag-minimal,
            .btn-icon-minimal {
                display: none !important;
            }

            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Outfit', Georgia, serif;
                font-size: 11.5pt;
                overflow: visible !important;
            }

            main.container {
                max-width: 100% !important;
                width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            .word-doc-header {
                display: block !important;
                text-align: center;
                margin-bottom: 24pt;
                padding-bottom: 10pt;
                border-bottom: 1.5pt solid #000000;
            }

            .word-doc-header h2 {
                font-size: 16pt;
                font-weight: 700;
                margin: 0 0 4pt 0;
                color: #000000;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .word-doc-header p {
                font-size: 11pt;
                margin: 0;
                color: #333333;
            }

            .card-minimal {
                border: none !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                background: transparent !important;
                padding: 0 !important;
                margin-bottom: 22pt !important;
                page-break-inside: auto;
            }

            .card-minimal + .card-minimal {
                padding-top: 14pt !important;
                border-top: 0.75pt solid #CCCCCC !important;
            }

            .card-header-minimal {
                border: none !important;
                padding: 0 0 6pt 0 !important;
                margin-bottom: 8pt !important;
                page-break-after: avoid;
            }

            .card-header-minimal h4 {
                font-size: 13.5pt !important;
                font-weight: 700 !important;
                color: #000000 !important;
            }

            .section-label-minimal {
                color: #000000 !important;
                font-size: 10pt !important;
                margin-top: 8pt;
                margin-bottom: 4pt;
                page-break-after: avoid;
            }

            .box-minimal-note,
            .box-minimal-dinamica {
                background: transparent !important;
                border: none !important;
                border-radius: 0 !important;
                padding: 0 !important;
                margin-top: 10pt !important;
            }

            .box-minimal-dinamica h5 {
                font-size: 11.5pt !important;
                font-weight: 700 !important;
                color: #000000 !important;
                margin-bottom: 4pt !important;
                page-break-after: avoid;
            }

            .texto-formatado {
                font-size: 11pt !important;
                line-height: 1.55 !important;
                color: #000000 !important;
                text-align: justify;
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
    <div class="bg-white border-bottom py-3 page-header-bar no-print">
        <div class="container d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3" style="max-width: 960px;">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($url_voltar) ?>" class="btn-only-arrow" title="Voltar">
                    <i class="bi bi-arrow-left fs-5"></i>
                </a>
                <div>
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-book" style="color: var(--gold-primary);"></i>Pregações e Dinâmicas
                    </h5>
                    <small class="text-muted fw-medium">
                        <?= !empty($nome_completo_edicao) ? htmlspecialchars($nome_completo_edicao) : 'Manual do Encontro / Acampamento' ?>
                    </small>
                </div>
            </div>

            <div class="header-actions-group">
                <?php if ($edicao_id > 0): ?>
                    <button type="button" class="btn btn-resgatar-discreto rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalResgatarEdicao" title="Importar pregações de outra edição">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Resgatar edição</span>
                    </button>
                <?php endif; ?>

                <button type="button" onclick="window.print()" class="btn btn-outline-navy rounded-pill px-3 px-sm-4 py-2 btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-printer-fill" style="color: var(--gold-primary);"></i>
                    <span>Imprimir / PDF</span>
                </button>

                <button type="button" class="btn btn-gold rounded-pill px-3 px-sm-4 py-2 btn-sm d-flex align-items-center gap-2" onclick="abrirModalCadastrar()">
                    <i class="bi bi-plus-lg"></i>
                    <span>Nova Pregação</span>
                </button>
            </div>
        </div>
    </div>

    <main class="container py-4" style="max-width: 960px;">

        <!-- CABEÇALHO DE DOCUMENTO WORD (APARECE APENAS NA IMPRESSÃO / PDF) -->
        <div class="word-doc-header d-none">
            <h2>Pregações, Dinâmicas e Momentos</h2>
            <?php if (!empty($nome_completo_edicao)): ?>
                <p><?= htmlspecialchars($nome_completo_edicao) ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm no-print" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensagem) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm no-print" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($erro) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($conteudos)): ?>
            <div class="text-center py-5 bg-white rounded-4 border p-4 my-3 no-print">
                <i class="bi bi-journal-plus fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                <h6 class="fw-bold text-dark mb-1">Nenhuma pregação ou dinâmica cadastrada</h6>
                <p class="text-muted small mb-3">Adicione uma nova pregação ou importe de uma edição anterior.</p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <?php if ($edicao_id > 0 && !empty($edicoes_anteriores)): ?>
                        <button type="button" class="btn btn-resgatar-discreto px-3 py-2 rounded-pill btn-sm" data-bs-toggle="modal" data-bs-target="#modalResgatarEdicao">
                            <i class="bi bi-arrow-repeat me-1"></i> Resgatar edição
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-gold px-4 py-2 rounded-pill btn-sm" onclick="abrirModalCadastrar()">
                        <i class="bi bi-plus-lg me-1"></i> Adicionar
                    </button>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($conteudos as $item): ?>
                <?php
                    $hasPregacao    = !empty($item['pregacao']);
                    $hasDinamica    = !empty($item['dinamica_titulo']) || !empty($item['dinamica_desc']);
                    $hasOrientacoes = !empty($item['orientacoes']);
                    $tituloExibicao = !empty($item['tema']) ? $item['tema'] : (!empty($item['dinamica_titulo']) ? $item['dinamica_titulo'] : 'Registro #' . $item['id']);
                ?>

                <article class="card-minimal p-4 mb-3">

                    <!-- Cabeçalho Minimalista do Card -->
                    <div class="d-flex align-items-start justify-content-between gap-2 pb-3 mb-3 border-bottom card-header-minimal">
                        <div class="min-w-0">
                            <?php if (!empty($item['tema'])): ?>
                                <span class="text-muted small d-block mb-1 no-print">Tema</span>
                                <h4 class="fw-bold m-0 text-dark" style="font-size: 1.2rem; word-break: break-word;">
                                    <?= htmlspecialchars($item['tema']) ?>
                                </h4>
                            <?php else: ?>
                                <h4 class="fw-bold m-0 text-dark" style="font-size: 1.15rem;">
                                    <?= htmlspecialchars($item['dinamica_titulo'] ?: 'Dinâmica Especial') ?>
                                </h4>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2 flex-shrink-0 no-print">
                            <div class="d-none d-sm-flex align-items-center gap-1">
                                <?php if ($hasPregacao): ?>
                                    <span class="tag-minimal">Pregação</span>
                                <?php endif; ?>
                                <?php if ($hasDinamica): ?>
                                    <span class="tag-minimal tag-minimal-gold">Dinâmica</span>
                                <?php endif; ?>
                            </div>

                            <button type="button" class="btn-icon-minimal" title="Editar" onclick='editarItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)'>
                                <i class="bi bi-pencil"></i>
                            </button>

                            <button type="button" class="btn-icon-minimal delete" title="Excluir" onclick='confirmarExclusao(<?= intval($item['id']) ?>, <?= htmlspecialchars(json_encode($tituloExibicao), ENT_QUOTES, 'UTF-8') ?>)'>
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Conteúdo da Pregação -->
                    <?php if ($hasPregacao): ?>
                        <div class="mb-3">
                            <div class="section-label-minimal">Pregação / Conteúdo</div>
                            <div class="texto-formatado"><?= htmlspecialchars($item['pregacao']) ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Orientações (caso existam registros antigos com orientação) -->
                    <?php if ($hasOrientacoes): ?>
                        <div class="box-minimal-note mb-3">
                            <div class="section-label-minimal">Momento da Oração / Orientações</div>
                            <div class="texto-formatado" style="font-size: 0.92rem;"><?= htmlspecialchars($item['orientacoes']) ?></div>
                        </div>
                    <?php endif; ?>

                    <!-- Dinâmica -->
                    <?php if ($hasDinamica): ?>
                        <div class="box-minimal-dinamica">
                            <div class="section-label-minimal no-print" style="color: #9A742E;">Dinâmica</div>
                            <h5 class="fw-bold text-dark mb-1" style="font-size: 1rem;">
                                <span class="d-none d-print-inline">Dinâmica: </span><?= htmlspecialchars($item['dinamica_titulo'] ?? 'Atividade') ?>
                            </h5>
                            <?php if (!empty($item['dinamica_desc'])): ?>
                                <div class="texto-formatado" style="font-size: 0.92rem;"><?= htmlspecialchars($item['dinamica_desc']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </article>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>

    <!-- MODAL DE CADASTRO / EDIÇÃO (SEM CAMPO DE ORIENTAÇÕES) -->
    <div class="modal fade no-print" id="modalConteudo" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form method="POST" class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <input type="hidden" name="action" value="salvar">
                <input type="hidden" name="id" id="form_id" value="0">

                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold m-0 text-white" id="modalTitulo">Nova Pregação / Dinâmica</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-3 p-md-4">
                    <div class="row g-3">
                        <div class="col-12 col-sm-9">
                            <label class="form-label fw-semibold">Tema da Pregação</label>
                            <input type="text" name="tema" id="form_tema" class="form-control" placeholder="Ex: O Amor de Deus">
                        </div>
                        <div class="col-12 col-sm-3">
                            <label class="form-label fw-semibold">Ordem</label>
                            <input type="number" name="ordem" id="form_ordem" class="form-control" value="1">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Conteúdo da Pregação</label>
                            <textarea name="pregacao" id="form_pregacao" class="form-control" rows="5" placeholder="Escreva aqui o conteúdo central da pregação..."></textarea>
                        </div>

                        <hr class="my-2">

                        <div class="col-12">
                            <label class="form-label fw-semibold" style="color: #8C6A28;">Título da Dinâmica</label>
                            <input type="text" name="dinamica_titulo" id="form_dinamica_titulo" class="form-control" placeholder="Ex: Dinâmica do Pirulito">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold" style="color: #8C6A28;">Descrição da Dinâmica</label>
                            <textarea name="dinamica_desc" id="form_dinamica_desc" class="form-control" rows="3" placeholder="Como executar a dinâmica, materiais necessários..."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold rounded-pill px-4">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL RESGATAR PREGAÇÕES DE OUTRA EDIÇÃO -->
    <div class="modal fade no-print" id="modalResgatarEdicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <input type="hidden" name="action" value="resgatar">

                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat text-warning fs-5"></i>
                        <span>Resgatar Pregações e Dinâmicas</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <?php if (empty($edicoes_anteriores)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-info-circle fs-2 d-block mb-2" style="color: var(--gold-primary);"></i>
                            <p class="mb-0 small">Nenhuma outra edição desta modalidade possui pregações cadastradas ainda.</p>
                        </div>
                    <?php else: ?>
                        <label class="form-label fw-semibold small mb-2">Selecione o ano / edição de origem:</label>
                        <select name="edicao_origem_id" class="form-select rounded-3 p-2 mb-3" required>
                            <option value="">Escolha uma edição...</option>
                            <?php foreach ($edicoes_anteriores as $ed_ant): ?>
                                <option value="<?= $ed_ant['id'] ?>">
                                    <?= htmlspecialchars($ed_ant['ano'] . ' - ' . $ed_ant['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="alert alert-warning border-0 rounded-3 small mb-0 d-flex align-items-start gap-2">
                            <i class="bi bi-exclamation-circle-fill mt-1"></i>
                            <div>
                                Todas as pregações e dinâmicas do ano escolhido serão duplicadas para esta edição. Alterações feitas aqui <strong>não</strong> afetarão a edição anterior.
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

    <!-- MODAL CARD DE CONFIRMAÇÃO DE EXCLUSÃO -->
    <div class="modal fade no-print" id="modalConfirmarExclusao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form method="POST" class="modal-content border-0 rounded-4 overflow-hidden shadow-lg text-center">
                <input type="hidden" name="action" value="excluir">
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
                    <h6 class="fw-bold text-dark mb-1">Excluir este registro?</h6>
                    <p class="fw-semibold text-dark small mb-2" id="excluir_titulo_preview" style="word-break: break-word;"></p>
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
        const modalElement = new bootstrap.Modal(document.getElementById('modalConteudo'));
        const modalExcluirElement = new bootstrap.Modal(document.getElementById('modalConfirmarExclusao'));

        function abrirModalCadastrar() {
            document.getElementById('form_id').value = '0';
            document.getElementById('form_tema').value = '';
            document.getElementById('form_ordem').value = '1';
            document.getElementById('form_pregacao').value = '';
            document.getElementById('form_dinamica_titulo').value = '';
            document.getElementById('form_dinamica_desc').value = '';
            document.getElementById('modalTitulo').innerText = 'Nova Pregação / Dinâmica';
            modalElement.show();
        }

        function editarItem(item) {
            document.getElementById('form_id').value = item.id;
            document.getElementById('form_tema').value = item.tema || '';
            document.getElementById('form_ordem').value = item.ordem || '1';
            document.getElementById('form_pregacao').value = item.pregacao || '';
            document.getElementById('form_dinamica_titulo').value = item.dinamica_titulo || '';
            document.getElementById('form_dinamica_desc').value = item.dinamica_desc || '';
            document.getElementById('modalTitulo').innerText = 'Editar Pregação / Dinâmica';
            modalElement.show();
        }

        function confirmarExclusao(id, titulo) {
            document.getElementById('excluir_id').value = id;
            document.getElementById('excluir_titulo_preview').innerText = '“' + titulo + '”';
            modalExcluirElement.show();
        }
    </script>
</body>
</html>