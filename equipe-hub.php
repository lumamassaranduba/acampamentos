<?php
// equipe-hub.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

// Estatísticas rápidas para os cards
$total_geral = $conn->query("SELECT COUNT(*) as total FROM equipe_geral WHERE edicao_id = $edicao_id")->fetch_assoc()['total'] ?? 0;
$total_setores = $conn->query("SELECT COUNT(*) as total FROM equipes_trabalho WHERE edicao_id = $edicao_id")->fetch_assoc()['total'] ?? 0;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Equipe - ÉFATA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        .topbar-efata { background-color: var(--efata-dark); border-bottom: 2px solid var(--efata-gold); color: #fff; }
        .card-opcoes {
            background: #ffffff;
            border: 1px solid #E2E8F0;
            border-radius: 20px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        .card-opcoes:hover {
            transform: translateY(-5px);
            border-color: var(--efata-gold);
            box-shadow: 0 12px 24px rgba(197, 160, 89, 0.15);
            color: inherit;
        }
        .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            background-color: #FEF3C7;
            color: var(--efata-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
    </style>
</head>
<body>

    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top">
        <div class="d-flex align-items-center gap-3">
            <a href="edicao-detalhes.php?id=<?= $edicao_id ?>" class="btn btn-sm btn-outline-light rounded-circle">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h5 class="fw-bold m-0 text-white">Gestão da Equipe de Trabalho</h5>
        </div>
    </header>

    <main class="container py-5">

        <div class="row g-4 justify-content-center">
            
            <!-- PARTE 1: EQUIPE GERAL -->
            <div class="col-12 col-md-4">
                <a href="equipe-geral.php?edicao_id=<?= $edicao_id ?>" class="card-opcoes p-4">
                    <div>
                        <div class="icon-circle mb-3">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">1. Equipe Geral de Trabalho</h4>
                        <p class="text-muted small">Cadastro inicial obrigatório de todos os voluntários (Nome, Telefone, Tamanho de Camiseta) e contagem geral.</p>
                    </div>
                    <div class="pt-3 border-top mt-3 d-flex justify-content-between align-items-center">
                        <small class="fw-semibold text-gold"><?= $total_geral ?> Voluntários Cadastrados</small>
                        <i class="bi bi-arrow-right-circle-fill fs-5 text-gold"></i>
                    </div>
                </a>
            </div>

            <!-- PARTE 2: EQUIPES POR FUNÇÕES / SETORES -->
            <div class="col-12 col-md-4">
                <a href="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>" class="card-opcoes p-4">
                    <div>
                        <div class="icon-circle mb-3">
                            <i class="bi bi-diagram-3-fill"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">2. Divisão por Funções e Setores</h4>
                        <p class="text-muted small">Criação dos setores (Cozinha, Secretaria, Apoio, etc.), alocação dos voluntários e destaque para os Coordenadores.</p>
                    </div>
                    <div class="pt-3 border-top mt-3 d-flex justify-content-between align-items-center">
                        <small class="fw-semibold text-gold"><?= $total_setores ?> Setores Criados</small>
                        <i class="bi bi-arrow-right-circle-fill fs-5 text-gold"></i>
                    </div>
                </a>
            </div>

            <!-- PARTE 3: ATRIBUIÇÕES E OBRIGAÇÕES DAS EQUIPES -->
            <div class="col-12 col-md-4">
                <a href="equipe-atribuicoes.php?edicao_id=<?= $edicao_id ?>" class="card-opcoes p-4">
                    <div>
                        <div class="icon-circle mb-3">
                            <i class="bi bi-check2-square"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">3. Tarefas</h4>
                        <p class="text-muted small"> O que equipe deve fazer durante acampamento.</p>
                    </div>
                    <div class="pt-3 border-top mt-3 d-flex justify-content-between align-items-center">
                        <small class="fw-semibold text-gold">Plano de Ação</small>
                        <i class="bi bi-arrow-right-circle-fill fs-5 text-gold"></i>
                    </div>
                </a>
            </div>

        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>