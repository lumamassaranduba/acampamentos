<?php
// equipes-trabalho.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redireciona se não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Dados do usuário para a Navbar
$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil       = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL'));

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// PROCESSAMENTOS FORMULÁRIOS / AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // RF08: Criar Setor/Equipe
    if ($acao === 'criar_equipe') {
        $nome = trim($_POST['nome'] ?? '');
        if (!empty($nome)) {
            $stmt = $conn->prepare("INSERT INTO equipes_trabalho (edicao_id, nome) VALUES (?, ?)");
            $stmt->bind_param("is", $edicao_id, $nome);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Equipe criada com sucesso!";
            } else {
                $mensagem_erro = "Erro ao criar equipe.";
            }
        } else {
            $mensagem_erro = "Informe o nome da equipe.";
        }
    }

    // RF09 / RN03: Alocar Voluntário da Equipe Geral (REGRA DE ALOCAÇÃO ÚNICA EM TODA A EDIÇÃO)
    if ($acao === 'alocar_membro') {
        $equipe_id = intval($_POST['equipe_id'] ?? 0);
        $voluntario_id = intval($_POST['voluntario_id'] ?? 0);
        $e_coordenador = isset($_POST['e_coordenador']) ? 1 : 0;

        if ($equipe_id > 0 && $voluntario_id > 0) {
            // Verifica se o voluntário já está alocado em QUALQUER equipe da mesma edição
            $stmt_check = $conn->prepare("
                SELECT eq.nome AS nome_equipe 
                FROM equipe_membros em 
                JOIN equipes_trabalho eq ON em.equipe_id = eq.id 
                WHERE em.voluntario_id = ? AND eq.edicao_id = ? 
                LIMIT 1
            ");
            $stmt_check->bind_param("ii", $voluntario_id, $edicao_id);
            $stmt_check->execute();
            $res_check = $stmt_check->get_result()->fetch_assoc();

            if ($res_check) {
                $mensagem_erro = "Este voluntário já está alocado na equipe '{$res_check['nome_equipe']}'! Cada pessoa só pode estar em uma equipe.";
            } else {
                $stmt = $conn->prepare("INSERT INTO equipe_membros (equipe_id, voluntario_id, e_coordenador) VALUES (?, ?, ?)");
                $stmt->bind_param("iii", $equipe_id, $voluntario_id, $e_coordenador);
                if ($stmt->execute()) {
                    $mensagem_sucesso = "Voluntário alocado com sucesso!";
                } else {
                    $mensagem_erro = "Erro ao alocar voluntário.";
                }
            }
        }
    }

    // Mover Voluntário para Outra Equipe (Drag and Drop / AJAX)
    if ($acao === 'mover_membro') {
        $membro_id = intval($_POST['membro_id'] ?? 0);
        $nova_equipe_id = intval($_POST['nova_equipe_id'] ?? 0);

        if ($membro_id > 0 && $nova_equipe_id > 0) {
            $stmt = $conn->prepare("UPDATE equipe_membros SET equipe_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $nova_equipe_id, $membro_id);
            if ($stmt->execute()) {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode(['status' => 'success']);
                    exit;
                }
                $mensagem_sucesso = "Voluntário movido com sucesso!";
            } else {
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    echo json_encode(['status' => 'error', 'message' => 'Erro ao mover voluntário.']);
                    exit;
                }
                $mensagem_erro = "Erro ao mover voluntário.";
            }
        }
    }

    // RF10 / RN04: Alternar Status de Coordenador
    if ($acao === 'toggle_coordenador') {
        $membro_id = intval($_POST['membro_id'] ?? 0);
        $status = intval($_POST['status'] ?? 0);
        $stmt = $conn->prepare("UPDATE equipe_membros SET e_coordenador = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $membro_id);
        $stmt->execute();
        $mensagem_sucesso = "Status de liderança atualizado!";
    }

    // Remover Voluntário da Equipe
    if ($acao === 'remover_membro') {
        $membro_id = intval($_POST['membro_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM equipe_membros WHERE id = ?");
        $stmt->bind_param("i", $membro_id);
        $stmt->execute();
        $mensagem_sucesso = "Voluntário removido da equipe.";
    }
}

// BUSCAS NO BANCO
$stmt = $conn->prepare("SELECT * FROM equipes_trabalho WHERE edicao_id = ? ORDER BY nome ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$equipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Busca APENAS os voluntários da Equipe Geral que AINDA NÃO FORAM ALOCADOS em nenhuma equipe nesta edição
$stmt_geral = $conn->prepare("
    SELECT eg.id, eg.nome, eg.telefone 
    FROM equipe_geral eg 
    WHERE eg.edicao_id = ? 
    AND eg.id NOT IN (
        SELECT em.voluntario_id 
        FROM equipe_membros em 
        JOIN equipes_trabalho eq ON em.equipe_id = eq.id 
        WHERE eq.edicao_id = ?
    )
    ORDER BY eg.nome ASC
");
$stmt_geral->bind_param("ii", $edicao_id, $edicao_id);
$stmt_geral->execute();
$voluntarios_geral = $stmt_geral->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Divisão de Equipes de Trabalho - Éfata</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --navy-dark: #0A192F;
            --navy-light: #1E293B;
            --gold-primary: #C5A059;
            --gold-hover: #B28D46;
            --bg-main: #F8FAFC;
            --border-color: #E2E8F0;
            --text-primary: #0F172A;
            --text-muted: #64748B;
        }

        body { 
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; 
            background-color: var(--bg-main); 
            color: var(--text-primary); 
            min-height: 100vh;
        }

        /* Top Navbar Padronizada */
        .top-navbar {
            background-color: var(--navy-dark);
            border-bottom: 2px solid var(--gold-primary);
            padding: 0.75rem 1.25rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.15);
        }

        .user-brand-block {
            min-width: 0;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            min-width: 42px;
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

        /* Botões Padronizados */
        .btn-navy {
            background-color: var(--navy-dark);
            color: #ffffff;
            font-weight: 600;
            border: 1px solid var(--navy-dark);
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-navy:hover {
            background-color: var(--gold-primary);
            border-color: var(--gold-primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
        }

        .btn-outline-dark-custom {
            border: 1px solid var(--border-color);
            background-color: #ffffff;
            color: var(--text-primary);
            font-weight: 600;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .btn-outline-dark-custom:hover {
            background-color: #F1F5F9;
            border-color: #CBD5E1;
            color: var(--navy-dark);
        }

        .card-custom { 
            background: #ffffff; 
            border-radius: 20px; 
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        /* CARD DA EQUIPE REFINADO */
        .card-equipe {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 18px rgba(0,0,0,0.03);
            transition: all 0.25s ease;
            overflow: hidden;
        }

        .card-equipe:hover {
            border-color: #CBD5E1;
            box-shadow: 0 8px 25px rgba(10, 25, 47, 0.08);
        }

        .card-equipe-header {
            background-color: #FAFCFF;
            padding: 1.15rem 1.35rem;
            border-bottom: 1px solid var(--border-color);
        }

        .card-equipe.drag-over {
            border: 2px dashed var(--gold-primary) !important;
            background-color: #FEFCE8 !important;
        }

        .highlight-card {
            animation: highlightPulse 2s ease-in-out;
        }

        @keyframes highlightPulse {
            0% { box-shadow: 0 0 0 0 rgba(197, 160, 89, 0.8); border-color: var(--gold-primary); }
            50% { box-shadow: 0 0 0 10px rgba(197, 160, 89, 0); border-color: var(--gold-primary); }
            100% { box-shadow: 0 0 0 0 rgba(197, 160, 89, 0); }
        }

        /* ITEM DA LISTA DE MEMBROS */
        .membro-item {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 12px 14px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
            cursor: grab;
        }

        .membro-item:hover {
            border-color: #CBD5E1;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .membro-item:active {
            cursor: grabbing;
        }

        .membro-item.dragging {
            opacity: 0.4;
        }

        /* DESIGN DESTACADO DO COORDENADOR */
        .coordenador-item {
            background-color: #FFFDF5 !important;
            border: 1px solid rgba(197, 160, 89, 0.45) !important;
            border-left: 5px solid var(--gold-primary) !important;
            box-shadow: 0 3px 10px rgba(197, 160, 89, 0.1) !important;
        }

        .badge-coordenador-premium {
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            border: 1px solid var(--gold-primary);
            font-size: 0.72rem;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 5px rgba(10, 25, 47, 0.15);
        }

        .badge-integrantes {
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            font-size: 0.82rem;
            padding: 6px 14px;
            border-radius: 12px;
            font-weight: 600;
        }

        /* ESTRUTURA E ESPAÇAMENTO DOS BOTÕES DE AÇÃO DOS MEMBROS */
        .membro-actions {
            display: flex;
            align-items: center;
            gap: 8px !important; /* Espaçamento bem definido para não colar a estrela no X */
        }

        .btn-star-coord {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--gold-primary);
            background-color: #FFFBEB;
            color: var(--gold-hover);
            transition: all 0.2s ease;
        }

        .btn-star-coord:hover {
            background-color: var(--gold-primary);
            color: #ffffff;
        }

        .btn-star-coord-active {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--navy-dark);
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            transition: all 0.2s ease;
        }

        .btn-star-coord-active:hover {
            background-color: #ef4444;
            border-color: #ef4444;
            color: #ffffff;
        }

        .btn-remove-membro {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #FEE2E2;
            background-color: #FEF2F2;
            color: #EF4444;
            transition: all 0.2s ease;
        }

        .btn-remove-membro:hover {
            background-color: #EF4444;
            border-color: #EF4444;
            color: #ffffff;
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        /* TOM SELECT PESQUISA */
        .search-select-wrapper {
            width: 220px;
        }

        .ts-control {
            border-radius: 10px !important;
            padding: 7px 12px !important;
            border: 1px solid var(--border-color) !important;
            font-size: 0.9rem !important;
            background-color: #ffffff !important;
            box-shadow: none !important;
        }

        .ts-control input {
            font-size: 0.9rem !important;
        }

        .ts-control:focus-within {
            border-color: var(--gold-primary) !important;
            box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.15) !important;
        }

        @media (max-width: 768px) {
            .search-select-wrapper {
                width: 100% !important;
            }
        }

        @media (max-width: 576px) {
            .top-navbar {
                padding: 0.65rem 0.85rem;
            }
            .btn-text-mobile {
                display: none !important;
            }
            .btn-mobile-icon-only {
                padding: 8px 12px !important;
            }
            .btn-mobile-block {
                width: 100%;
                justify-content: center;
            }
            .card-equipe-header {
                padding: 1rem;
            }
            .membro-item {
                padding: 10px 12px;
            }
        }

        /* IMPRESSÃO / PDF: ESTILO PURO */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body { 
                background-color: #ffffff !important; 
                color: #000000 !important; 
                font-family: Arial, sans-serif !important; 
                margin: 0 !important;
                padding: 0 !important;
            }

            .no-print, .top-navbar, .alert, .btn, .d-print-none { 
                display: none !important; 
            }

            .print-wrapper {
                display: block !important;
                width: 100% !important;
            }

            .print-title {
                text-align: center !important;
                font-size: 16pt !important;
                font-weight: bold !important;
                text-transform: uppercase !important;
                margin-bottom: 12px !important;
                padding: 6px !important;
                border: 1px solid #000000 !important;
                background-color: #ffffff !important;
            }

            table.print-table {
                width: 100% !important;
                border-collapse: collapse !important;
                border: 1px solid #000000 !important;
                margin-bottom: 20px !important;
                page-break-inside: avoid;
            }

            table.print-table th, 
            table.print-table td {
                border: 1px solid #000000 !important;
                padding: 6px 8px !important;
                font-size: 10pt !important;
                text-align: left !important;
            }

            table.print-table th {
                background-color: #0A192F !important;
                color: #ffffff !important;
                font-weight: bold !important;
            }

            table.print-table th.print-header-equipe {
                text-align: center !important;
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
                <small class="text-light opacity-75 d-block text-truncate" style="font-size: 0.72rem;"><?= htmlspecialchars($perfil) ?></small>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 nav-actions">
            <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout" title="Sair">
                <i class="bi bi-box-arrow-right"></i>
                <span class="d-none d-md-inline">Sair</span>
            </button>
        </div>
    </nav>

    <main class="container py-3" style="max-width: 1200px;">

        <!-- 1º CARD: TOPO DE NAVEGAÇÃO COM ATALHO DE IR PARA EQUIPE GERAL -->
        <div class="card-custom p-3 p-md-4 mt-2 mt-md-3 mb-4 no-print d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" class="btn text-dark border-0 p-0 fs-4 d-flex align-items-center justify-content-center me-1" title="Voltar ao Hub da Equipe">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h4 class="fw-bold m-0 text-dark fs-5 fs-md-4">Divisão das Equipes de Trabalho</h4>
                </div>
            </div>

            <!-- BOTÃO DE ATALHO PARA IR PARA A EQUIPE GERAL -->
            <a href="equipe-geral.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" class="btn btn-navy rounded-3 px-3 py-2 btn-sm d-flex align-items-center justify-content-center gap-2 btn-mobile-block">
                <i class="bi bi-people-fill text-warning"></i>
                <span>Ir para Equipe Geral</span>
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center no-print">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($mensagem_sucesso) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center no-print">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($mensagem_erro) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- 2º CARD: CONTROLE E PESQUISA PERFEITAMENTE ALINHADOS NA MESMA LINHA -->
        <div class="card-custom p-3 p-md-4 mb-4 d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3 no-print">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold text-dark fs-6 text-nowrap"><i class="bi bi-diagram-3-fill text-warning me-1.5"></i> Total: <?= count($equipes) ?> Equipes</span>
            </div>
            
            <div class="d-flex align-items-center gap-2 flex-wrap flex-md-nowrap justify-content-end w-100 w-md-auto">
                <!-- BUSCA RÁPIDA DE EQUIPE -->
                <div class="search-select-wrapper">
                    <select id="select-pesquisa-equipe" placeholder="Pesquisar equipe...">
                        <option value=""></option>
                        <?php foreach ($equipes as $eq_opt): ?>
                            <option value="card-equipe-<?= $eq_opt['id'] ?>"><?= htmlspecialchars($eq_opt['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button onclick="window.print()" class="btn btn-outline-dark-custom rounded-3 btn-mobile-icon-only px-3 py-2 d-flex align-items-center justify-content-center gap-2" title="Imprimir Equipes">
                    <i class="bi bi-printer-fill fs-5 fs-md-6"></i> 
                    <span class="btn-text-mobile">Imprimir Equipes</span>
                </button>
                <button class="btn btn-navy rounded-3 btn-mobile-icon-only px-3 py-2 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovaEquipe" title="+ Criar Nova Equipe">
                    <i class="bi bi-plus-lg text-warning fs-5 fs-md-6"></i> 
                    <span class="btn-text-mobile">+ Criar Nova Equipe</span>
                </button>
            </div>
        </div>

        <!-- CARDS DAS EQUIPES DE TRABALHO -->
        <div class="row g-4 no-print">
            <?php if (empty($equipes)): ?>
                <div class="col-12 text-center py-5 text-muted card-custom">
                    <i class="bi bi-diagram-3 fs-1 text-muted opacity-50 d-block mb-2"></i>
                    <h5 class="fw-bold">Nenhuma equipe cadastrada.</h5>
                    <p class="small mb-0">Clique em <strong>"+ Criar Nova Equipe"</strong> para cadastrar uma equipe (Ex: Cozinha, Secretaria, Apoio...).</p>
                </div>
            <?php else: ?>
                <?php foreach ($equipes as $eq): ?>
                    <?php
                    $stmt_m = $conn->prepare("SELECT em.id as membro_id, em.e_coordenador, eg.nome, eg.telefone, eg.tamanho_camiseta 
                                             FROM equipe_membros em 
                                             JOIN equipe_geral eg ON em.voluntario_id = eg.id 
                                             WHERE em.equipe_id = ? 
                                             ORDER BY em.e_coordenador DESC, eg.nome ASC");
                    $stmt_m->bind_param("i", $eq['id']);
                    $stmt_m->execute();
                    $membros = $stmt_m->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <div class="col-12 col-md-6" id="card-equipe-<?= $eq['id'] ?>">
                        <div class="card-equipe h-100 d-flex flex-column justify-content-between drop-zone" data-equipe-id="<?= $eq['id'] ?>">
                            <div>
                                <div class="card-equipe-header d-flex justify-content-between align-items-center">
                                    <h5 class="fw-bold text-dark m-0 fs-6 d-flex align-items-center gap-2">
                                        <i class="bi bi-diagram-3-fill text-warning fs-5"></i>
                                        <span><?= htmlspecialchars($eq['nome']) ?></span>
                                    </h5>
                                    <span class="badge-integrantes"><?= count($membros) ?> Integrantes</span>
                                </div>

                                <div class="p-3 p-md-4">
                                    <h6 class="fw-bold text-muted mb-3" style="font-size: 0.75rem; letter-spacing: 0.6px;">MEMBROS DA EQUIPE:</h6>
                                    <div class="target-list" style="min-height: 50px;">
                                        <?php if (empty($membros)): ?>
                                            <div class="text-center text-muted small py-4 border border-dashed rounded-3 bg-light">
                                                Nenhum voluntário alocado nesta equipe.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($membros as $m): ?>
                                                <div class="membro-item d-flex justify-content-between align-items-center draggable-item <?= $m['e_coordenador'] ? 'coordenador-item' : '' ?>" draggable="true" data-membro-id="<?= $m['membro_id'] ?>">
                                                    <div>
                                                        <div class="fw-bold text-dark d-flex align-items-center gap-2" style="font-size: 0.94rem;">
                                                            <span><?= htmlspecialchars($m['nome']) ?></span>
                                                            <?php if ($m['e_coordenador']): ?>
                                                                <span class="badge-coordenador-premium">
                                                                    <i class="bi bi-star-fill"></i> Coordenador
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted d-block mt-0.5" style="font-size: 0.8rem;">
                                                            <?= htmlspecialchars($m['telefone'] ?? 'Sem fone') ?> &#124; Camiseta: <strong><?= htmlspecialchars($m['tamanho_camiseta'] ?? 'N/A') ?></strong>
                                                        </small>
                                                    </div>

                                                    <!-- BOTÕES COM ESPAÇAMENTO ADEQUADO (GAP DOIS BOTÕES) -->
                                                    <div class="membro-actions ms-2">
                                                        <form action="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST" class="d-inline m-0">
                                                            <input type="hidden" name="acao" value="toggle_coordenador">
                                                            <input type="hidden" name="membro_id" value="<?= $m['membro_id'] ?>">
                                                            <input type="hidden" name="status" value="<?= $m['e_coordenador'] ? 0 : 1 ?>">
                                                            <button type="submit" class="<?= $m['e_coordenador'] ? 'btn-star-coord-active' : 'btn-star-coord' ?>" title="<?= $m['e_coordenador'] ? 'Remover Liderança' : 'Definir Coordenador' ?>">
                                                                <i class="bi bi-star<?= $m['e_coordenador'] ? '-fill' : '' ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form action="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST" class="d-inline m-0" onsubmit="return confirm('Remover este voluntário da equipe?');">
                                                            <input type="hidden" name="acao" value="remover_membro">
                                                            <input type="hidden" name="membro_id" value="<?= $m['membro_id'] ?>">
                                                            <button type="submit" class="btn-remove-membro" title="Remover da equipe">
                                                                <i class="bi bi-x-lg"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 p-md-4 pt-0">
                                <button class="btn btn-outline-dark-custom btn-sm w-100 rounded-3 py-2.5 fw-semibold d-flex align-items-center justify-content-center gap-2" onclick="abrirModalAlocar(<?= $eq['id'] ?>, '<?= htmlspecialchars(addslashes($eq['nome'])) ?>')">
                                    <i class="bi bi-person-plus-fill text-warning fs-6"></i>
                                    <span>Alocar servo da Equipe Geral</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ESTRUTURA PARA IMPRESSÃO / PDF (CABEÇALHO DA EQUIPE CENTRALIZADO) -->
        <div class="print-wrapper d-none d-print-block">
            <div class="print-title">Equipes de Trabalho</div>
            <?php foreach ($equipes as $eq): ?>
                <?php
                $stmt_m = $conn->prepare("SELECT em.id as membro_id, em.e_coordenador, eg.nome, eg.telefone, eg.tamanho_camiseta 
                                         FROM equipe_membros em 
                                         JOIN equipe_geral eg ON em.voluntario_id = eg.id 
                                         WHERE em.equipe_id = ? 
                                         ORDER BY em.e_coordenador DESC, eg.nome ASC");
                $stmt_m->bind_param("i", $eq['id']);
                $stmt_m->execute();
                $membros = $stmt_m->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                <table class="print-table">
                    <thead>
                        <tr>
                            <th colspan="3" class="print-header-equipe">EQUIPE: <?= mb_strtoupper(htmlspecialchars($eq['nome'])) ?> (<?= count($membros) ?> Integrantes)</th>
                        </tr>
                        <tr style="background-color: #f1f5f9; color: #000;">
                            <th style="width: 50%;">NOME</th>
                            <th style="width: 30%;">TELEFONE</th>
                            <th style="width: 20%; text-align: center;">CAMISETA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($membros)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #666; font-style: italic;">Nenhum servo alocado nesta equipe.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($membros as $m): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($m['nome']) ?></strong>
                                        <?php if ($m['e_coordenador']): ?>
                                            <span>(Coordenador)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($m['telefone'] ?? '') ?></td>
                                    <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($m['tamanho_camiseta'] ?? 'N/A') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>

    </main>

    <!-- MODAL NOVA EQUIPE -->
    <div class="modal fade no-print" id="modalNovaEquipe" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 overflow-hidden shadow-lg">
                <div class="modal-header modal-header-custom p-4">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-diagram-3-fill text-warning"></i> Nova Equipe / Setor
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST">
                    <input type="hidden" name="acao" value="criar_equipe">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Nome da Equipe *</label>
                            <input type="text" name="nome" class="form-control rounded-3" required placeholder="Ex: Cozinha, Secretaria, Apoio, Intercessão...">
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3 border-top">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-navy rounded-3 px-4">Criar Equipe</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL ALOCAR VOLUNTÁRIO -->
    <div class="modal fade no-print" id="modalAlocar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 overflow-hidden shadow-lg">
                <div class="modal-header modal-header-custom p-4">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-person-check-fill text-warning"></i> Alocar para: <span id="alocar_nome_equipe"></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST">
                    <input type="hidden" name="acao" value="alocar_membro">
                    <input type="hidden" name="equipe_id" id="alocar_equipe_id">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Selecione o servo da Equipe Geral *</label>
                            <select name="voluntario_id" id="select-voluntario" placeholder="Digite para pesquisar o nome..." required>
                                <option value=""></option>
                                <?php foreach ($voluntarios_geral as $vg): ?>
                                    <option value="<?= $vg['id'] ?>"><?= htmlspecialchars($vg['nome']) ?> (<?= htmlspecialchars($vg['telefone'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" style="font-size: 0.78rem;">Exibindo apenas voluntários que ainda não possuem equipe alocada.</small>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="e_coordenador" id="checkCoordenador" value="1">
                            <label class="form-check-label small fw-medium text-dark" for="checkCoordenador">
                                Definir como Coordenador / Liderança da Equipe
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3 border-top">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-navy rounded-3 px-4">Confirmar Alocação</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL LOGOUT -->
    <div class="modal fade no-print" id="modalConfirmarLogout" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg text-center">
                <div class="modal-header modal-header-custom p-3 justify-content-center">
                    <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-right text-warning fs-5"></i> Confirmar Saída
                    </h5>
                </div>
                <div class="modal-body p-4">
                    <p class="text-secondary mb-0">Deseja realmente encerrar sua sessão e sair do sistema?</p>
                </div>
                <div class="modal-footer bg-light p-3 border-top justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <a href="logout.php" class="btn btn-danger rounded-3 px-3 btn-sm fw-semibold">Sair do Sistema</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <script>
        let tomSelectInstance = null;

        document.addEventListener("DOMContentLoaded", function () {
            // Inicializar TomSelect no modal de alocar
            tomSelectInstance = new TomSelect("#select-voluntario", {
                create: false,
                sortField: { field: "text", direction: "asc" }
            });

            // Inicializar TomSelect na barra de Pesquisa de Equipes
            new TomSelect("#select-pesquisa-equipe", {
                create: false,
                sortField: { field: "text", direction: "asc" },
                onChange: function(cardId) {
                    if (!cardId) return;

                    const targetElement = document.getElementById(cardId);
                    if (targetElement) {
                        targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        // Efeito de destaque no card localizado
                        const cardBox = targetElement.querySelector('.card-equipe');
                        if (cardBox) {
                            cardBox.classList.add('highlight-card');
                            setTimeout(() => {
                                cardBox.classList.remove('highlight-card');
                            }, 2000);
                        }
                    }
                }
            });

            // Drag and Drop de voluntários entre equipes
            const draggables = document.querySelectorAll('.draggable-item');
            const dropZones = document.querySelectorAll('.drop-zone');

            draggables.forEach(draggable => {
                draggable.addEventListener('dragstart', (e) => {
                    draggable.classList.add('dragging');
                    e.dataTransfer.setData('text/plain', draggable.dataset.membroId);
                });

                draggable.addEventListener('dragend', () => {
                    draggable.classList.remove('dragging');
                });
            });

            dropZones.forEach(zone => {
                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    zone.classList.add('drag-over');
                });

                zone.addEventListener('dragleave', () => {
                    zone.classList.remove('drag-over');
                });

                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    zone.classList.remove('drag-over');

                    const membroId = e.dataTransfer.getData('text/plain');
                    const novaEquipeId = zone.dataset.equipeId;
                    const draggedElement = document.querySelector(`[data-membro-id="${membroId}"]`);

                    if (!draggedElement) return;

                    const currentZone = draggedElement.closest('.drop-zone');
                    if (currentZone && currentZone.dataset.equipeId === novaEquipeId) return;

                    const formData = new FormData();
                    formData.append('acao', 'mover_membro');
                    formData.append('membro_id', membroId);
                    formData.append('nova_equipe_id', novaEquipeId);

                    fetch(`equipes-trabalho.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>`, {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            window.location.reload();
                        } else {
                            alert(data.message || 'Erro ao mover voluntário.');
                        }
                    })
                    .catch(() => {
                        window.location.reload();
                    });
                });
            });
        });

        function abrirModalAlocar(id, nome) {
            document.getElementById('alocar_equipe_id').value = id;
            document.getElementById('alocar_nome_equipe').innerText = nome;
            
            if (tomSelectInstance) {
                tomSelectInstance.clear();
            }

            new bootstrap.Modal(document.getElementById('modalAlocar')).show();
        }
    </script>
</body>
</html>