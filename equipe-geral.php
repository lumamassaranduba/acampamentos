<?php
// equipe-geral.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redireciona se não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Recupera dados do usuário para a Navbar
$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil       = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL'));

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// Função auxiliar para limpar caracteres especiais de telefones para comparação precisa
function limparTelefone($tel) {
    return preg_replace('/[^0-9]/', '', $tel);
}

// Processar formulários de Ação (Cadastrar, Editar, Excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    // 1. Cadastrar Voluntário com Integração ao Histórico e Validação de Duplicidade (Nome e Telefone)
    if ($_POST['acao'] === 'cadastrar_voluntario') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $tamanho_camiseta = trim($_POST['tamanho_camiseta'] ?? '');

        if (!empty($nome)) {
            
            $telefone_limpo = limparTelefone($telefone);
            $duplicado_nome = false;
            $duplicado_telefone = false;

            // Busca todos os voluntários da edição atual para validar duplicidade
            $stmt_check = $conn->prepare("SELECT nome, telefone FROM equipe_geral WHERE edicao_id = ?");
            $stmt_check->bind_param("i", $edicao_id);
            $stmt_check->execute();
            $voluntarios_existentes = $stmt_check->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($voluntarios_existentes as $ve) {
                if (mb_strtolower(trim($ve['nome']), 'UTF-8') === mb_strtolower($nome, 'UTF-8')) {
                    $duplicado_nome = true;
                }
                if (!empty($telefone_limpo) && !empty($ve['telefone'])) {
                    if (limparTelefone($ve['telefone']) === $telefone_limpo) {
                        $duplicado_telefone = true;
                    }
                }
            }

            if ($duplicado_nome) {
                $mensagem_erro = "O servo '{$nome}' já está cadastrado nesta edição!";
            } elseif ($duplicado_telefone) {
                $mensagem_erro = "O telefone '{$telefone}' já está cadastrado para outro servo nesta edição!";
            } else {
                
                // Verifica se a pessoa já existe na tabela de Histórico (pessoas)
                $stmt_p = $conn->prepare("SELECT id FROM pessoas WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?)) LIMIT 1");
                $stmt_p->bind_param("s", $nome);
                $stmt_p->execute();
                $res_p = $stmt_p->get_result()->fetch_assoc();

                if ($res_p) {
                    $pessoa_id = $res_p['id'];
                    if (!empty($telefone)) {
                        $stmt_u = $conn->prepare("UPDATE pessoas SET telefone = ? WHERE id = ?");
                        $stmt_u->bind_param("si", $telefone, $pessoa_id);
                        $stmt_u->execute();
                    }
                } else {
                    $stmt_i = $conn->prepare("INSERT INTO pessoas (nome, telefone) VALUES (?, ?)");
                    $stmt_i->bind_param("ss", $nome, $telefone);
                    $stmt_i->execute();
                }

                // Insere na Equipe Geral da Edição
                $stmt = $conn->prepare("INSERT INTO equipe_geral (edicao_id, nome, telefone, tamanho_camiseta) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $edicao_id, $nome, $telefone, $tamanho_camiseta);
                if ($stmt->execute()) {
                    $mensagem_sucesso = "Servo cadastrado e vinculado ao histórico com sucesso!";
                } else {
                    $mensagem_erro = "Erro ao cadastrar servo.";
                }
            }
        } else {
            $mensagem_erro = "Preencha o campo de nome obrigatório.";
        }
    }

    // 2. Editar Voluntário com Validação de Duplicidade (Nome e Telefone)
    if ($_POST['acao'] === 'editar_voluntario') {
        $id = intval($_POST['voluntario_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $tamanho_camiseta = trim($_POST['tamanho_camiseta'] ?? '');

        if ($id > 0 && !empty($nome)) {
            
            $telefone_limpo = limparTelefone($telefone);
            $duplicado_nome = false;
            $duplicado_telefone = false;

            // Busca outros voluntários da edição (excluindo o atual)
            $stmt_check = $conn->prepare("SELECT nome, telefone FROM equipe_geral WHERE edicao_id = ? AND id != ?");
            $stmt_check->bind_param("ii", $edicao_id, $id);
            $stmt_check->execute();
            $outros_voluntarios = $stmt_check->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($outros_voluntarios as $ve) {
                if (mb_strtolower(trim($ve['nome']), 'UTF-8') === mb_strtolower($nome, 'UTF-8')) {
                    $duplicado_nome = true;
                }
                if (!empty($telefone_limpo) && !empty($ve['telefone'])) {
                    if (limparTelefone($ve['telefone']) === $telefone_limpo) {
                        $duplicado_telefone = true;
                    }
                }
            }

            if ($duplicado_nome) {
                $mensagem_erro = "Já existe outro servo cadastrado com o nome '{$nome}' nesta edição!";
            } elseif ($duplicado_telefone) {
                $mensagem_erro = "Já existe outro servo cadastrado com o telefone '{$telefone}' nesta edição!";
            } else {
                $stmt = $conn->prepare("UPDATE equipe_geral SET nome = ?, telefone = ?, tamanho_camiseta = ? WHERE id = ? AND edicao_id = ?");
                $stmt->bind_param("sssii", $nome, $telefone, $tamanho_camiseta, $id, $edicao_id);
                if ($stmt->execute()) {
                    
                    // Atualiza também na tabela 'pessoas'
                    $stmt_p = $conn->prepare("UPDATE pessoas SET telefone = ? WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?))");
                    $stmt_p->bind_param("ss", $telefone, $nome);
                    $stmt_p->execute();

                    $mensagem_sucesso = "Servo atualizado com sucesso!";
                } else {
                    $mensagem_erro = "Erro ao atualizar servo.";
                }
            }
        } else {
            $mensagem_erro = "Preencha o campo de nome obrigatório para editar.";
        }
    }

    // 3. Excluir Voluntário
    if ($_POST['acao'] === 'excluir_voluntario') {
        $id = intval($_POST['voluntario_id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM equipe_geral WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("ii", $id, $edicao_id);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Servo removido com sucesso!";
            } else {
                $mensagem_erro = "Erro ao remover servo.";
            }
        }
    }
}

// Ordenação Automática por Ordem Alfabética
$stmt = $conn->prepare("SELECT * FROM equipe_geral WHERE edicao_id = ? ORDER BY nome ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$voluntarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_voluntarios = count($voluntarios);

// Buscar nomes e telefones cadastrados no histórico para Autocomplete no formulário
$res_historico = $conn->query("
    SELECT DISTINCT nome, telefone FROM (
        SELECT nome, telefone FROM pessoas
        UNION
        SELECT nome, telefone FROM equipe_geral
    ) AS historico_todos
    WHERE nome IS NOT NULL AND nome != ''
    ORDER BY nome ASC
");
$pessoas_historico = $res_historico ? $res_historico->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipe Geral - Éfata</title>
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
        }

        .btn-outline-dark-custom:hover {
            background-color: #F1F5F9;
            border-color: #CBD5E1;
            color: var(--navy-dark);
        }

        /* Botões de Ação na Tabela */
        .btn-action-icon {
            width: 30px;
            height: 30px;
            min-width: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border-color);
            background-color: #ffffff;
            color: var(--text-muted);
            transition: all 0.2s ease;
            padding: 0;
            font-size: 0.82rem;
        }

        .btn-action-icon:hover {
            background-color: var(--navy-dark);
            border-color: var(--navy-dark);
            color: var(--gold-primary);
        }

        .btn-action-icon-danger {
            width: 30px;
            height: 30px;
            min-width: 30px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #FEE2E2;
            background-color: #FEF2F2;
            color: #EF4444;
            transition: all 0.2s ease;
            padding: 0;
            font-size: 0.82rem;
        }

        .btn-action-icon-danger:hover {
            background-color: #EF4444;
            border-color: #EF4444;
            color: #ffffff;
        }

        .card-custom { 
            background: #ffffff; 
            border-radius: 20px; 
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        .badge-navy-count {
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            font-size: 0.9rem;
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* BADGES AZUL MARINHO DA CAMISETA */
        .tam-camiseta-navy {
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            font-weight: 600;
            font-size: 0.78rem;
            padding: 3px 6px;
            border-radius: 6px;
            display: inline-block;
        }

        .tam-camiseta-na {
            background-color: #F1F5F9;
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.75rem;
            padding: 3px 6px;
            border-radius: 6px;
            display: inline-block;
            border: 1px dashed var(--border-color);
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        /* Estilização Tom Select */
        .ts-control { 
            border-radius: 10px !important; 
            padding: 10px 14px !important; 
            border: 1px solid var(--border-color) !important; 
            font-size: 0.95rem !important; 
        }
        .ts-control:focus-within {
            border-color: var(--gold-primary) !important;
            box-shadow: 0 0 0 3px rgba(197, 160, 89, 0.2) !important;
        }
        .ts-dropdown { 
            border-radius: 12px !important; 
            border: 1px solid var(--border-color) !important; 
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; 
            margin-top: 4px !important; 
        }
        .ts-dropdown .option { 
            padding: 10px 14px !important; 
            font-size: 0.95rem !important; 
        }
        .ts-dropdown .active { 
            background-color: #f1f5f9 !important; 
            color: #0f172a !important; 
        }

        /* REGRAS EXCLUSIVAS PARA DISPOSITIVOS MÓVEIS */
        @media (max-width: 576px) {
            .top-navbar {
                padding: 0.65rem 0.85rem;
            }
            .card-custom {
                padding: 0.75rem 0.5rem !important;
                border-radius: 16px;
            }

            /* Esconde os textos dos botões no topo para ficarem compactos com apenas o ícone */
            .btn-text-mobile {
                display: none !important;
            }

            .btn-mobile-icon-only {
                padding: 8px 12px !important;
                min-width: 44px;
            }

            /* Tabela Ajustada ao Ecrã sem Estourar as Ações */
            .table-responsive {
                border-radius: 10px;
                overflow-x: hidden !important;
            }

            .table-mobile-fit {
                width: 100% !important;
                table-layout: fixed !important;
            }

            .table-mobile-fit th, 
            .table-mobile-fit td {
                font-size: 0.78rem !important;
                padding: 8px 2px !important;
                word-wrap: break-word;
                white-space: normal !important;
            }

            /* Definição de Larguras no Celular para Garantir os Botões */
            .col-m-num { width: 8% !important; text-align: center; }
            .col-m-nome { width: 34% !important; }
            .col-m-tel { width: 28% !important; font-size: 0.72rem !important; }
            .col-m-cam { width: 12% !important; text-align: center; }
            .col-m-acoes { width: 18% !important; text-align: right; }

            .table-title-mobile {
                font-size: 1.05rem !important;
                text-align: center;
            }
        }

        /* IMPRESSÃO / PDF: ESTILO PURO SEM DEPENDÊNCIAS EXTERNAS */
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
                table-layout: fixed !important;
            }

            table.print-table th, 
            table.print-table td {
                border: 1px solid #000000 !important;
                padding: 6px 8px !important;
                font-size: 10pt !important;
                text-align: left !important;
                word-wrap: break-word !important;
            }

            table.print-table th {
                background-color: #0A192F !important;
                color: #ffffff !important;
                font-weight: bold !important;
            }

            table.print-table th.col-1, table.print-table td.col-1 { width: 8% !important; text-align: center !important; }
            table.print-table th.col-2, table.print-table td.col-2 { width: 47% !important; }
            table.print-table th.col-3, table.print-table td.col-3 { width: 25% !important; }
            table.print-table th.col-4, table.print-table td.col-4 { width: 20% !important; border-right: 1px solid #000000 !important; }
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

    <main class="container py-2" style="max-width: 1200px;">

        <!-- TOPO DE NAVEGAÇÃO -->
        <div class="card-custom p-3 p-md-4 mt-3 mt-md-4 mb-4 no-print d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" class="btn text-dark border-0 p-0 fs-4 d-flex align-items-center justify-content-center me-1" title="Voltar ao Hub da Equipe">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h4 class="fw-bold m-0 text-dark fs-5 fs-md-4">Equipe Geral </h4>
                    <p class="text-muted small m-0">Cadastro e listagem completa dos servos desta edição.</p>
                </div>
            </div>
            <a href="equipes-trabalho.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" class="btn btn-navy rounded-3 px-3 py-2 btn-sm d-flex align-items-center justify-content-center gap-2 btn-mobile-block">
                <i class="bi bi-diagram-3-fill text-warning"></i>
                <span>Ir para Equipes de Trabalho</span>
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

        <!-- TOPO DE CONTROLE -->
        <div class="card-custom p-3 p-md-4 mb-4 d-flex flex-row align-items-center justify-content-between flex-wrap gap-2 no-print">
            <div class="d-flex align-items-center gap-3">
                <div class="badge-navy-count">
                    <i class="bi bi-people-fill me-1 text-warning"></i> Total: <?= $total_voluntarios ?> servos
                </div>
                <small class="text-muted d-none d-sm-inline">(A-Z)</small>
            </div>
            
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark-custom rounded-3 btn-mobile-icon-only px-3 py-2 d-flex align-items-center justify-content-center gap-2" title="Imprimir Lista">
                    <i class="bi bi-printer-fill fs-5 fs-md-6"></i> 
                    <span class="btn-text-mobile">Imprimir Lista</span>
                </button>
                <button class="btn btn-navy rounded-3 btn-mobile-icon-only px-3 py-2 d-flex align-items-center justify-content-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovoVoluntario" title="+ Novo Voluntário">
                    <i class="bi bi-person-plus-fill text-warning fs-5 fs-md-6"></i> 
                    <span class="btn-text-mobile"> Novo servo</span>
                </button>
            </div>
        </div>

        <!-- TABELA EXCLUSIVA PARA A TELA NAVEGADOR SITE -->
        <div class="card-custom p-2 p-md-4 d-print-none">
            <h5 class="fw-bold text-dark mb-3 table-title-mobile d-block d-md-none">EQUIPE GERAL DE SERVOS</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-mobile-fit">
                    <thead>
                        <tr class="d-none d-md-table-row">
                            <th colspan="5" class="text-start py-2 border-0 bg-transparent fw-bold text-dark fs-5">
                                EQUIPE GERAL DE SERVOS
                            </th>
                        </tr>
                        <tr class="table-dark" style="background-color: var(--navy-dark) !important; color: #ffffff;">
                            <th class="col-m-num" style="background-color: var(--navy-dark) !important; color: #ffffff;">ID</th>
                            <th class="col-m-nome" style="background-color: var(--navy-dark) !important; color: #ffffff;">Nome</th>
                            <th class="col-m-tel" style="background-color: var(--navy-dark) !important; color: #ffffff;">Telefone</th>
                            <th class="col-m-cam" style="background-color: var(--navy-dark) !important; color: #ffffff;">Cam.</th>
                            <th class="text-end col-m-acoes" style="background-color: var(--navy-dark) !important; color: #ffffff;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($voluntarios)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum servo cadastrado na Equipe Geral.</td></tr>
                        <?php else: ?>
                            <?php $num = 1; foreach ($voluntarios as $v): ?>
                                <tr>
                                    <td class="col-m-num text-muted font-monospace"><?= $num++ ?></td>
                                    <td class="col-m-nome fw-bold text-dark"><?= htmlspecialchars($v['nome']) ?></td>
                                    <td class="col-m-tel text-muted"><?= htmlspecialchars($v['telefone'] ?? '') ?></td>
                                    <td class="col-m-cam">
                                        <?php if (!empty($v['tamanho_camiseta'])): ?>
                                            <span class="tam-camiseta-navy"><?= htmlspecialchars($v['tamanho_camiseta']) ?></span>
                                        <?php else: ?>
                                            <span class="tam-camiseta-na"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end col-m-acoes">
                                        <div class="d-flex align-items-center justify-content-end gap-1">
                                            <button class="btn-action-icon" data-bs-toggle="modal" data-bs-target="#modalEditarVoluntario<?= $v['id'] ?>" title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <button class="btn-action-icon-danger" data-bs-toggle="modal" data-bs-target="#modalExcluirVoluntario<?= $v['id'] ?>" title="Excluir">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Modal Editar Voluntário -->
                                <div class="modal fade no-print" id="modalEditarVoluntario<?= $v['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 overflow-hidden shadow-lg">
                                            <div class="modal-header modal-header-custom p-4">
                                                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                                                    <i class="bi bi-pencil-square text-warning"></i> Editar servo
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="equipe-geral.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST">
                                                <input type="hidden" name="acao" value="editar_voluntario">
                                                <input type="hidden" name="voluntario_id" value="<?= $v['id'] ?>">
                                                <div class="modal-body p-4">
                                                    <div class="mb-3 text-start">
                                                        <label class="form-label small fw-medium text-dark">Nome Completo </label>
                                                        <input type="text" name="nome" class="form-control rounded-3" value="<?= htmlspecialchars($v['nome']) ?>" required>
                                                    </div>
                                                    <div class="mb-3 text-start">
                                                        <label class="form-label small fw-medium text-dark">Telefone / WhatsApp</label>
                                                        <input type="text" name="telefone" class="form-control rounded-3" value="<?= htmlspecialchars($v['telefone'] ?? '') ?>">
                                                    </div>
                                                    <div class="mb-3 text-start">
                                                        <label class="form-label small fw-medium text-dark">Tamanho da Camiseta</label>
                                                        <select name="tamanho_camiseta" class="form-select rounded-3">
                                                            <option value="">Não informado</option>
                                                            <?php
                                                            $tamanhos = ['PP', 'P', 'M', 'G', 'GG', 'EXG', 'outro'];
                                                            foreach ($tamanhos as $tam) {
                                                                $selected = ($v['tamanho_camiseta'] === $tam) ? 'selected' : '';
                                                                echo "<option value='{$tam}' {$selected}>{$tam}</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light p-3 border-top">
                                                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-navy rounded-3 px-4">Salvar Alterações</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Excluir Voluntário -->
                                <div class="modal fade no-print" id="modalExcluirVoluntario<?= $v['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0 overflow-hidden shadow-lg">
                                            <div class="modal-header bg-danger text-white p-4">
                                                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                                                    <i class="bi bi-exclamation-triangle-fill"></i> Confirmar Exclusão
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="equipe-geral.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST">
                                                <input type="hidden" name="acao" value="excluir_voluntario">
                                                <input type="hidden" name="voluntario_id" value="<?= $v['id'] ?>">
                                                <div class="modal-body p-4 text-center">
                                                    <p class="m-0 fs-5 text-dark">Tem certeza de que deseja remover <strong><?= htmlspecialchars($v['nome']) ?></strong>?</p>
                                                    <small class="text-muted d-block mt-2">Esta ação não poderá ser desfeita.</small>
                                                </div>
                                                <div class="modal-footer bg-light p-3 border-top justify-content-center">
                                                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-danger rounded-3 px-4">Sim, Remover</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ESTRUTURA BLINDADA EXCLUSIVA PARA IMPRESSÃO/PDF -->
        <div class="print-wrapper d-none d-print-block">
            <div class="print-title">EQUIPE GERAL</div>
            <table class="print-table">
                <thead>
                    <tr>
                        <th class="col-1">ID</th>
                        <th class="col-2">Nome</th>
                        <th class="col-3">Telefone</th>
                        <th class="col-4">Tamanho Camiseta</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($voluntarios)): ?>
                        <tr><td colspan="4" style="text-align: center;">Nenhum servo cadastrado na Equipe Geral.</td></tr>
                    <?php else: ?>
                        <?php $num_p = 1; foreach ($voluntarios as $vp): ?>
                            <tr>
                                <td class="col-1"><?= $num_p++ ?></td>
                                <td class="col-2" style="font-weight: bold;"><?= htmlspecialchars($vp['nome']) ?></td>
                                <td class="col-3"><?= htmlspecialchars($vp['telefone'] ?? '') ?></td>
                                <td class="col-4" style="font-weight: bold;"><?= htmlspecialchars($vp['tamanho_camiseta'] ?? 'N/A') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </main>

    <!-- MODAL DE CADASTRO COM TOM SELECT DO HISTÓRICO -->
    <div class="modal fade no-print" id="modalNovoVoluntario" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 overflow-hidden shadow-lg">
                <div class="modal-header modal-header-custom p-4">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                        <i class="bi bi-person-plus-fill text-warning"></i> Cadastrar servo (Equipe Geral)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipe-geral.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST">
                    <input type="hidden" name="acao" value="cadastrar_voluntario">
                    <div class="modal-body p-4">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Nome Completo </label>
                            <select name="nome" id="select_nome_voluntario" placeholder="Digite para pesquisar no histórico..." required>
                                <option value=""></option>
                                <?php foreach ($pessoas_historico as $ph): ?>
                                    <option value="<?= htmlspecialchars($ph['nome']) ?>" data-telefone="<?= htmlspecialchars($ph['telefone'] ?? '') ?>">
                                        <?= htmlspecialchars($ph['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" style="font-size: 0.78rem;">Se a pessoa já serviu antes, selecione o nome para reutilizar os dados.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Telefone / WhatsApp</label>
                            <input type="text" name="telefone" id="input_telefone" class="form-control rounded-3" placeholder="Ex: (18) 99999-9999">
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-medium text-dark">Tamanho da Camiseta</label>
                            <select name="tamanho_camiseta" class="form-select rounded-3">
                                <option value="">Selecione...</option>
                                <option value="PP">PP</option>
                                <option value="P">P</option>
                                <option value="M">M</option>
                                <option value="G">G</option>
                                <option value="GG">GG</option>
                                <option value="EXG">EXG</option>
                                <option value="outro">outro</option>
                            </select>
                        </div>

                    </div>
                    <div class="modal-footer bg-light p-3 border-top">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-navy rounded-3 px-4">Salvar Cadastro</button>
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
        document.addEventListener("DOMContentLoaded", function () {
            const tsVoluntario = new TomSelect("#select_nome_voluntario", {
                create: true,
                createOnBlur: true,
                maxItems: 1,
                sortField: { field: "text", direction: "asc" },
                render: {
                    option: function(data, escape) {
                        const tel = data.telefone ? ' (' + escape(data.telefone) + ')' : '';
                        return '<div>' + escape(data.text) + tel + '</div>';
                    },
                    item: function(data, escape) {
                        return '<div>' + escape(data.text) + '</div>';
                    }
                },
                onChange: function(nomeSelecionado) {
                    if (!nomeSelecionado) return;

                    const optionSelecionada = this.options[nomeSelecionado];
                    const campoTel = document.getElementById('input_telefone');

                    if (optionSelecionada && optionSelecionada.telefone && campoTel) {
                        campoTel.value = optionSelecionada.telefone;
                    }
                }
            });
        });
    </script>
</body>
</html>