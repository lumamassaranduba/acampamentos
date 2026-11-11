<?php
// equipe-hub.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redireciona se não estiver logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

$perfil       = strtoupper(trim($_SESSION['usuario_perfil'] ?? ''));
$nome_usuario = $_SESSION['usuario_nome'] ?? 'Usuário';

// Estatísticas rápidas para os cards
$total_geral = $conn->query("SELECT COUNT(*) as total FROM equipe_geral WHERE edicao_id = $edicao_id")->fetch_assoc()['total'] ?? 0;
$total_setores = $conn->query("SELECT COUNT(*) as total FROM equipes_trabalho WHERE edicao_id = $edicao_id")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão da Equipe de Trabalho - Éfata</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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

        /* Container Principal com Largura Harmoniosa */
        .main-container {
            padding: 2rem 1.25rem;
            max-width: 1000px;
            margin: 0 auto;
        }

        /* Card Cabeçalho */
        .card-header-page {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        /* Cards das Opções */
        .card-opcoes {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .card-opcoes:hover {
            border-color: var(--gold-primary);
            box-shadow: 0 10px 22px rgba(10, 25, 47, 0.08);
            transform: translateY(-4px);
            color: var(--navy-dark);
        }

        .icon-box-circle {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.25rem;
            transition: all 0.2s ease;
        }

        .card-opcoes:hover .icon-box-circle {
            background-color: var(--gold-primary);
            color: #ffffff;
        }

        .badge-counter {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--navy-dark);
            background-color: #F1F5F9;
            padding: 4px 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .btn-card-footer {
            color: var(--navy-dark);
            font-weight: 600;
            font-size: 0.88rem;
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        @media (max-width: 576px) {
            .top-navbar {
                padding: 0.65rem 0.85rem;
            }

            .main-container {
                padding: 1.25rem 0.85rem;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR PADRONIZADA -->
<nav class="top-navbar d-flex align-items-center justify-content-between gap-2">
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

<div class="main-container">

    <!-- CABEÇALHO DA TELA COM BOTAO VOLTAR -->
    <div class="card-header-page mb-4 d-flex align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="btn text-dark border-0 p-0 fs-4 d-flex align-items-center justify-content-center" title="Voltar ao Painel da Edição">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h4 class="fw-bold m-0 text-dark">Gestão da Equipe de Trabalho</h4>
                <p class="text-muted small m-0">Gerencie servos, divisão das equipes e funções.</p>
            </div>
        </div>
    </div>

    <!-- GRID DOS 3 CARDS COMPACTOS -->
    <div class="row g-3 justify-content-center">
        
        <!-- 1. EQUIPE GERAL DE TRABALHO -->
        <div class="col-12 col-md-4">
            <a href="equipe-geral.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-opcoes">
                <div>
                    <div class="icon-box-circle">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Equipe Geral</h5>
                    <p class="text-muted small mb-3" style="font-size: 0.85rem; line-height: 1.4;">
                        Cadastro inicial de servos.
                    </p>
                </div>
                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="badge-counter"><?= $total_geral ?> Servos</span>
                    <span class="btn-card-footer d-flex align-items-center gap-1">
                        Acessar <i class="bi bi-arrow-right"></i>
                    </span>
                </div>
            </a>
        </div>

        <!-- 2. DIVISÃO POR FUNÇÕES E SETORES -->
        <div class="col-12 col-md-4">
            <a href="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-opcoes">
                <div>
                    <div class="icon-box-circle">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Divisão por equipe</h5>
                    <p class="text-muted small mb-3" style="font-size: 0.85rem; line-height: 1.4;">
                        Equipes (Cozinha, Secretaria, etc.), alocação e coordenadores.
                    </p>
                </div>
                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="badge-counter"><?= $total_setores ?> Equipes</span>
                    <span class="btn-card-footer d-flex align-items-center gap-1">
                        Acessar <i class="bi bi-arrow-right"></i>
                    </span>
                </div>
            </a>
        </div>

        <!-- 3. TAREFAS -->
        <div class="col-12 col-md-4">
            <a href="equipe-atribuicoes.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="card-opcoes">
                <div>
                    <div class="icon-box-circle">
                        <i class="bi bi-check2-square"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Tarefas & Obrigações</h5>
                    <p class="text-muted small mb-3" style="font-size: 0.85rem; line-height: 1.4;">
                        Funções e atribuições das equipes durante o acampamento.
                    </p>
                </div>
                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="badge-counter">Funções</span>
                    <span class="btn-card-footer d-flex align-items-center gap-1">
                        Acessar <i class="bi bi-arrow-right"></i>
                    </span>
                </div>
            </a>
        </div>

    </div>
</div>

<!-- MODAL LOGOUT -->
<div class="modal fade" id="modalConfirmarLogout" tabindex="-1" aria-hidden="true">
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
</body>
</html>