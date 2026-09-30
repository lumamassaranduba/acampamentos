<?php
// equipes-campistas.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// 1. Valida autenticação do utilizador
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$caminho_logo  = 'assets/img/logo.png';
$nome_usuario  = $_SESSION['usuario_nome'] ?? 'Valéria Guimarães';
$perfil        = $_SESSION['usuario_perfil'] ?? '';

// 2. Captura o ID da edição
$edicao_id = 0;
if (isset($_GET['edicao_id']) && intval($_GET['edicao_id']) > 0) {
    $edicao_id = intval($_GET['edicao_id']);
} elseif (isset($_GET['id']) && intval($_GET['id']) > 0) {
    $edicao_id = intval($_GET['id']);
}

if ($edicao_id <= 0) {
    header("Location: dashboard-geral.php?erro=edicao_invalida");
    exit;
}

// 3. Busca dados da edição e da modalidade
$stmt_ed = $conn->prepare("
    SELECT e.*, m.nome AS modalidade_nome, m.cor
    FROM edicoes e
    INNER JOIN modalidades m ON e.modalidade_id = m.id
    WHERE e.id = ?
");
$stmt_ed->bind_param("i", $edicao_id);
$stmt_ed->execute();
$edicao = $stmt_ed->get_result()->fetch_assoc();

if (!$edicao) {
    header("Location: dashboard-geral.php?erro=edicao_nao_encontrada");
    exit;
}

$modalidade_id  = intval($edicao['modalidade_id']);
$nomeModalidade = htmlspecialchars($edicao['modalidade_nome']);
$nomeEdicao     = !empty($edicao['nome']) ? htmlspecialchars($edicao['nome']) : "Edição";
$anoEdicao      = intval($edicao['ano'] ?? date('Y'));
$cor            = !empty($edicao['cor']) ? $edicao['cor'] : '#292524';

// 4. Processamento de formulários (Ações POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'criar_equipe') {
        $nome_equipe = trim($_POST['nome_equipe'] ?? '');
        $cor_equipe  = trim($_POST['cor_equipe'] ?? '#00bcd4');

        if (!empty($nome_equipe)) {
            $stmt_add = $conn->prepare("INSERT INTO equipes_campistas (edicao_id, nome, cor) VALUES (?, ?, ?)");
            if ($stmt_add) {
                $stmt_add->bind_param("iss", $edicao_id, $nome_equipe, $cor_equipe);
                $stmt_add->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'editar_equipe') {
        $equipe_id   = intval($_POST['equipe_id'] ?? 0);
        $nome_equipe = trim($_POST['nome_equipe'] ?? '');
        $cor_equipe  = trim($_POST['cor_equipe'] ?? '#00bcd4');

        if ($equipe_id > 0 && !empty($nome_equipe)) {
            $stmt_edit = $conn->prepare("UPDATE equipes_campistas SET nome = ?, cor = ? WHERE id = ?");
            if ($stmt_edit) {
                $stmt_edit->bind_param("ssi", $nome_equipe, $cor_equipe, $equipe_id);
                $stmt_edit->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'excluir_equipe') {
        $equipe_id = intval($_POST['equipe_id'] ?? 0);

        if ($equipe_id > 0) {
            $conn->query("DELETE FROM equipes_lideres WHERE equipe_id = $equipe_id");
            $conn->query("DELETE FROM equipes_membros WHERE equipe_id = $equipe_id");
            $stmt_del = $conn->prepare("DELETE FROM equipes_campistas WHERE id = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("i", $equipe_id);
                $stmt_del->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'adicionar_lider') {
        $equipe_id = intval($_POST['equipe_id'] ?? 0);
        $cargo     = trim($_POST['cargo'] ?? 'Confidente');
        $nome      = trim($_POST['nome'] ?? '');

        if ($equipe_id > 0 && !empty($nome)) {
            $stmt_lid = $conn->prepare("INSERT INTO equipes_lideres (equipe_id, cargo, nome) VALUES (?, ?, ?)");
            if ($stmt_lid) {
                $stmt_lid->bind_param("iss", $equipe_id, $cargo, $nome);
                $stmt_lid->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'editar_lider') {
        $lider_id = intval($_POST['lider_id'] ?? 0);
        $cargo    = trim($_POST['cargo'] ?? 'Confidente');
        $nome     = trim($_POST['nome'] ?? '');

        if ($lider_id > 0 && !empty($nome)) {
            $stmt_edit = $conn->prepare("UPDATE equipes_lideres SET cargo = ?, nome = ? WHERE id = ?");
            if ($stmt_edit) {
                $stmt_edit->bind_param("ssi", $cargo, $nome, $lider_id);
                $stmt_edit->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'excluir_lider') {
        $lider_id = intval($_POST['lider_id'] ?? 0);

        if ($lider_id > 0) {
            $stmt_del = $conn->prepare("DELETE FROM equipes_lideres WHERE id = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("i", $lider_id);
                $stmt_del->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'adicionar_campista') {
        $equipe_id = intval($_POST['equipe_id'] ?? 0);
        $nome      = trim($_POST['nome'] ?? '');

        if ($equipe_id > 0 && !empty($nome)) {
            $stmt_camp = $conn->prepare("INSERT INTO equipes_membros (equipe_id, nome) VALUES (?, ?)");
            if ($stmt_camp) {
                $stmt_camp->bind_param("is", $equipe_id, $nome);
                $stmt_camp->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'editar_campista') {
        $campista_id = intval($_POST['campista_id'] ?? 0);
        $nome        = trim($_POST['nome'] ?? '');

        if ($campista_id > 0 && !empty($nome)) {
            $stmt_edit = $conn->prepare("UPDATE equipes_membros SET nome = ? WHERE id = ?");
            if ($stmt_edit) {
                $stmt_edit->bind_param("si", $nome, $campista_id);
                $stmt_edit->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'excluir_campista') {
        $campista_id = intval($_POST['campista_id'] ?? 0);

        if ($campista_id > 0) {
            $stmt_del = $conn->prepare("DELETE FROM equipes_membros WHERE id = ?");
            if ($stmt_del) {
                $stmt_del->bind_param("i", $campista_id);
                $stmt_del->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'trocar_equipe_campista') {
        $campista_id    = intval($_POST['campista_id'] ?? 0);
        $nova_equipe_id = intval($_POST['nova_equipe_id'] ?? 0);

        if ($campista_id > 0 && $nova_equipe_id > 0) {
            $stmt_tr = $conn->prepare("UPDATE equipes_membros SET equipe_id = ? WHERE id = ?");
            if ($stmt_tr) {
                $stmt_tr->bind_param("ii", $nova_equipe_id, $campista_id);
                $stmt_tr->execute();
            }
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }
}

// 5. Busca as equipas
$equipes = [];
$res_eq = $conn->query("SELECT * FROM equipes_campistas WHERE edicao_id = $edicao_id ORDER BY id ASC");

if ($res_eq) {
    while ($eq = $res_eq->fetch_assoc()) {
        $eq_id = $eq['id'];

        $lideres = [];
        $res_lid = $conn->query("SELECT * FROM equipes_lideres WHERE equipe_id = $eq_id ORDER BY id ASC");
        if ($res_lid) {
            while ($l = $res_lid->fetch_assoc()) {
                $lideres[] = $l;
            }
        }

        $campistas = [];
        $res_c = $conn->query("SELECT * FROM equipes_membros WHERE equipe_id = $eq_id ORDER BY id ASC");
        if ($res_c) {
            while ($c = $res_c->fetch_assoc()) {
                $campistas[] = $c;
            }
        }

        $eq['lideres']   = $lideres;
        $eq['campistas'] = $campistas;
        $equipes[]       = $eq;
    }
}

// Mock demonstrativo caso o banco de dados esteja vazio
if (empty($equipes)) {
    $equipes = [
        [
            'id' => 1,
            'nome' => 'COMUNIDADE TEMOR DE DEUS - COR AZUL',
            'cor' => '#00e5ff',
            'lideres' => [
                ['id' => 1, 'cargo' => 'Confidente', 'nome' => 'Camila'],
                ['id' => 2, 'cargo' => 'Confidente', 'nome' => 'Bárbara'],
                ['id' => 3, 'cargo' => 'Confidente', 'nome' => 'Miguel'],
                ['id' => 4, 'cargo' => 'Confidente', 'nome' => 'João Guilherme']
            ],
            'campistas' => [
                ['id' => 101, 'nome' => 'Alex Santos Barroca'],
                ['id' => 102, 'nome' => 'Ana Luiza Barcello Nunes'],
                ['id' => 103, 'nome' => 'Carlos Eduardo O. Velasque'],
                ['id' => 104, 'nome' => 'Lucas Eduardo Ventura França'],
                ['id' => 105, 'nome' => 'Maitê Carbonari'],
                ['id' => 106, 'nome' => 'Marina Joca Araujo'],
                ['id' => 107, 'nome' => 'Pedro Henrique Cian de Oliveira'],
                ['id' => 108, 'nome' => 'Ysla Valentine Silva Brasi'],
                ['id' => 109, 'nome' => 'Luigi Ventura Malacrida'],
                ['id' => 110, 'nome' => 'Maria Jullia da Silva Tineu'],
                ['id' => 111, 'nome' => 'Leonardo Vicentini Gervazoni']
            ]
        ],
        [
            'id' => 2,
            'nome' => 'COMUNIDADE FORTALEZA - COR ROSA',
            'cor' => '#e91e63',
            'lideres' => [
                ['id' => 5, 'cargo' => 'Confidente', 'nome' => 'DAMIANA'],
                ['id' => 6, 'cargo' => 'Confidente', 'nome' => 'LAURA'],
                ['id' => 7, 'cargo' => 'Confidente', 'nome' => 'ROGÉRIO'],
                ['id' => 8, 'cargo' => 'Confidente', 'nome' => 'LÉO']
            ],
            'campistas' => [
                ['id' => 201, 'nome' => 'Anna Luísa Benedito Sales'],
                ['id' => 202, 'nome' => 'Flávio Chiarelli dos Anjos'],
                ['id' => 203, 'nome' => 'Gabriel Seolin Benedetti'],
                ['id' => 204, 'nome' => 'Jean Carlos Jesus'],
                ['id' => 205, 'nome' => 'Jhonatan A. Alves de Almeida'],
                ['id' => 206, 'nome' => 'João Gabriel Pereira'],
                ['id' => 207, 'nome' => 'Lara Aguiar de Freitas'],
                ['id' => 208, 'nome' => 'Lívia de Freitas Rodrigues Cano'],
                ['id' => 209, 'nome' => 'Lucas Paiva Carbonari'],
                ['id' => 210, 'nome' => 'Maria Clara Lima de Moraes'],
                ['id' => 211, 'nome' => 'Maria Elisa Oliveira'],
                ['id' => 212, 'nome' => 'Matheus Lorençoni Maldonado']
            ]
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $nomeModalidade ?> - Divisão de Equipas e Campistas</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --cor-modalidade: <?= $cor ?>;
            --nav-bg: #0d1b2a;
            --nav-yellow: #e5b338;
            --nav-red: #e63946;
        }

        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: #fcfbf9; 
            color: #1c1917; 
            margin: 0; 
            padding: 0; 
        }

        .navbar-custom {
            background-color: var(--nav-bg);
            padding: 0.85rem 2rem;
            color: #ffffff;
        }

        .user-profile-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-box {
            width: 48px;
            height: 48px;
            border: 1.5px solid var(--nav-yellow);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background-color: rgba(255, 255, 255, 0.05);
            flex-shrink: 0;
        }

        .logo-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            padding: 4px;
        }

        .user-info-text .user-name {
            font-weight: 700;
            font-size: 1.05rem;
            color: #ffffff;
            line-height: 1.2;
        }

        .user-info-text .user-role {
            font-size: 0.78rem;
            color: #94a3b8;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .btn-nav-outline-red {
            background-color: transparent;
            color: #f87171;
            border: 1px solid var(--nav-red);
            font-weight: 600;
            border-radius: 10px;
            padding: 0.45rem 1.1rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-nav-outline-red:hover {
            background-color: rgba(230, 57, 70, 0.1);
            color: #f87171;
        }

        .main-content { 
            padding: 32px 48px; 
            width: 100%; 
            max-width: 1400px; 
            margin: 0 auto; 
            box-sizing: border-box;
        }

        .equipe-card {
            background: #ffffff;
            border: 1.5px solid #000000;
            border-radius: 0px;
            padding: 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .equipe-header {
            padding: 10px 14px;
            font-weight: 800;
            font-size: 0.95rem;
            color: #000000;
            text-transform: uppercase;
            border-bottom: 1.5px solid #000000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .equipe-header-btns {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }

        .equipe-header-btns .btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            flex-direction: row !important;
            white-space: nowrap !important;
            font-size: 0.82rem;
            font-weight: 600;
            padding: 4px 8px !important;
            line-height: 1.2;
        }

        .table-lideres, .table-campistas {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        .table-lideres td, .table-campistas td {
            border: 1px solid #000000;
            padding: 6px 12px;
            color: #000000;
            vertical-align: middle;
        }

        .table-lideres tr td:first-child {
            font-weight: 600;
            width: 35%;
            background-color: #fcfcfc;
        }

        .campistas-section-title {
            text-align: center;
            font-weight: 800;
            font-size: 0.85rem;
            letter-spacing: 1px;
            padding: 8px 0;
            color: #000000;
            text-transform: uppercase;
            border-top: 1.5px solid #000000;
            border-bottom: 1.5px solid #000000;
        }

        .table-campistas td.num-col {
            width: 45px;
            text-align: center;
            font-weight: 700;
            border-right: 1px solid #000000;
        }

        @media (max-width: 768px) {
            .main-content { padding: 16px; }
            .navbar-custom { padding: 0.85rem 1rem; }
        }

        /* REGRAS ESPECÍFICAS DE IMPRESSÃO (PDF / IMPRESSORA) */
        @media print {
            /* Força a impressão das cores de fundo (background-color) */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }

            @page {
                size: A4 portrait;
                margin: 10mm;
            }

            .navbar-custom, 
            .no-print, 
            .btn, 
            .equipe-header-btns {
                display: none !important;
            }

            body {
                background-color: #ffffff !important;
            }

            .main-content {
                padding: 0 !important;
                max-width: 100% !important;
                margin: 0 !important;
            }

            /* Força cada coluna/equipe a ocupar 100% da folha e quebrar página */
            .equipe-col {
                width: 100% !important;
                float: none !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .equipe-card {
                box-shadow: none !important;
                border: 2px solid #000000 !important;
                margin-bottom: 0 !important;
                width: 100% !important;
            }

            .equipe-header {
                font-size: 1.1rem !important;
                padding: 12px 16px !important;
            }

            .table-lideres td, .table-campistas td {
                padding: 8px 12px !important;
                font-size: 0.95rem !important;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-custom sticky-top no-print">
    <div class="container-fluid p-0">
        <div class="user-profile-container">
            <div class="logo-box">
                <img src="<?= $caminho_logo ?>" alt="Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/48?text=LOGO';">
            </div>
            <div class="user-info-text">
                <div class="user-name"><?= htmlspecialchars($nome_usuario) ?></div>
                <div class="user-role"><?= htmlspecialchars($perfil) ?></div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <a href="logout.php" class="btn-nav-outline-red">
                <i class="bi bi-box-arrow-right"></i> Sair
            </a>
        </div>
    </div>
</nav>

<main class="main-content">

    <!-- CABEÇALHO DA PÁGINA -->
    <div class="bg-white p-4 rounded-4 border mb-4 shadow-sm d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 no-print" style="border-color: #e7e5e4 !important;">
        <div class="d-flex align-items-center gap-3">
            <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id ?>" class="btn btn-light btn-sm rounded-3 d-flex align-items-center gap-2 border text-secondary">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>

            <div>
                <h3 class="fw-bold m-0 text-dark">Equipas e Campistas</h3>
                <span class="text-muted small"><?= $nomeModalidade ?> - <?= $nomeEdicao ?> (<?= $anoEdicao ?>)</span>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-primary btn-sm rounded-3 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalTrocarEquipe">
                <i class="bi bi-arrow-left-right"></i> Mover Campista
            </button>

            <button onclick="window.print()" class="btn btn-outline-dark btn-sm rounded-3 px-3 d-flex align-items-center gap-2">
                <i class="bi bi-printer"></i> Imprimir
            </button>

            <button class="btn btn-dark btn-sm rounded-3 px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalCriarEquipe">
                <i class="bi bi-plus-lg"></i> Nova Equipa
            </button>
        </div>
    </div>

    <!-- GRID DE EQUIPAS -->
    <div class="row">
        <?php foreach ($equipes as$equipe): ?>
            <div class="col-12 col-lg-6 equipe-col">
                <div class="equipe-card">
                    
                    <!-- CABEÇALHO DA EQUIPA COM BOTÕES DE AÇÃO -->
                    <div class="equipe-header" style="background-color: <?= htmlspecialchars($equipe['cor']) ?> !important;">
                        <span class="text-truncate"><?= htmlspecialchars($equipe['nome']) ?></span>
                        <div class="equipe-header-btns">
                            <button class="btn btn-sm btn-light text-dark rounded-1 d-inline-flex align-items-center gap-1 text-nowrap" title="Adicionar Líder" onclick="abrirModalLider(<?= $equipe['id'] ?>)">
                                <i class="bi bi-person-plus-fill"></i> <span>+ Líder</span>
                            </button>
                            <button class="btn btn-sm btn-light text-dark rounded-1 d-inline-flex align-items-center gap-1 text-nowrap" title="Adicionar Campista" onclick="abrirModalCampista(<?= $equipe['id'] ?>)">
                                <i class="bi bi-plus-circle-fill"></i> <span>+ Campista</span>
                            </button>
                            <button class="btn btn-sm btn-light text-primary rounded-1 d-inline-flex align-items-center gap-1 text-nowrap" title="Gerenciar Membros/Equipe" onclick="abrirModalGerenciarEquipe(<?= htmlspecialchars(json_encode($equipe)) ?>)">
                                <i class="bi bi-pencil-square"></i> <span>Editar</span>
                            </button>
                            <button class="btn btn-sm btn-light text-danger rounded-1 d-inline-flex align-items-center justify-content-center text-nowrap" title="Excluir Equipe" onclick="abrirModalExcluirEquipe(<?= $equipe['id'] ?>, '<?= addslashes($equipe['nome']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- TABELA DE LÍDERES -->
                    <table class="table-lideres">
                        <tbody>
                            <?php if (!empty($equipe['lideres'])): ?>
                                <?php foreach ($equipe['lideres'] as$lider): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($lider['cargo'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($lider['nome'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-2">Nenhum líder cadastrado</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <!-- TÍTULO CAMPISTAS -->
                    <div class="campistas-section-title">
                        CAMPISTAS
                    </div>

                    <!-- LISTA DE CAMPISTAS -->
                    <table class="table-campistas">
                        <tbody>
                            <?php 
                            $total_linhas = max(13, count($equipe['campistas'])); 
                            for ($i = 0; $i < $total_linhas; $i++): 
                                $num =$i + 1;
                                $campista_nome = $equipe['campistas'][$i]['nome'] ?? '';
                            ?>
                                <tr>
                                    <td class="num-col"><?= $num ?></td>
                                    <td><?= htmlspecialchars($campista_nome) ?></td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                </div>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- MODAL: GERENCIAR E EDITAR EQUIPA / LÍDERES / CAMPISTAS -->
<div class="modal fade no-print" id="modalGerenciarEquipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Gerenciar Equipe e Integrantes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                
                <!-- EDICÃO DO NOME E COR DA EQUIPE -->
                <form method="POST" class="mb-4 pb-3 border-bottom">
                    <input type="hidden" name="acao" value="editar_equipe">
                    <input type="hidden" name="equipe_id" id="gerenciar_equipe_id" value="">
                    
                    <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-card-heading me-1"></i> Dados da Comunidade</h6>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Nome da Equipe</label>
                            <input type="text" name="nome_equipe" id="gerenciar_nome_equipe" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Cor da Equipe</label>
                            <input type="color" name="cor_equipe" id="gerenciar_cor_equipe" class="form-control form-control-color w-100">
                        </div>
                    </div>
                    <div class="text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-dark px-3">Atualizar Nome/Cor</button>
                    </div>
                </form>

                <!-- LISTA DE LÍDERES DA EQUIPE -->
                <div class="mb-4 pb-3 border-bottom">
                    <h6 class="fw-bold mb-2 text-primary"><i class="bi bi-person-badge me-1"></i> Líderes</h6>
                    <div id="container_lideres_gerenciar"></div>
                </div>

                <!-- LISTA DE CAMPISTAS DA EQUIPE -->
                <div>
                    <h6 class="fw-bold mb-2 text-primary"><i class="bi bi-people me-1"></i> Campistas</h6>
                    <div id="container_campistas_gerenciar"></div>
                </div>

            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary rounded-3 px-4" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: EXCLUIR EQUIPA -->
<div class="modal fade no-print" id="modalExcluirEquipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="excluir_equipe">
            <input type="hidden" name="equipe_id" id="delete_equipe_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">Excluir Equipe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="m-0">Tem certeza que deseja remover a comunidade <strong id="delete_equipe_nome"></strong>? Todos os líderes e campistas associados a ela também serão desvinculados.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger rounded-3 px-4">Sim, Remover Equipe</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: CRIAR EQUIPA -->
<div class="modal fade no-print" id="modalCriarEquipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="criar_equipe">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Nova Equipa / Comunidade</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome da Equipa</label>
                    <input type="text" name="nome_equipe" class="form-control" placeholder="Ex: COMUNIDADE TEMOR DE DEUS" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Cor do Cabeçalho</label>
                    <input type="color" name="cor_equipe" class="form-control form-control-color w-100" value="#00bcd4">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADICIONAR LÍDER -->
<div class="modal fade no-print" id="modalAdicionarLider" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="adicionar_lider">
            <input type="hidden" name="equipe_id" id="lider_equipe_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Adicionar Líder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Cargo / Nomeação do Líder</label>
                    <input type="text" name="cargo" class="form-control" placeholder="Ex: Confidente, Padrinho, Monitor..." value="Confidente" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome do Líder</label>
                    <input type="text" name="nome" class="form-control" placeholder="Digite o nome completo" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar Líder</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: ADICIONAR CAMPISTA -->
<div class="modal fade no-print" id="modalAdicionarCampista" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="adicionar_campista">
            <input type="hidden" name="equipe_id" id="campista_equipe_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Adicionar Campista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome do Campista</label>
                    <input type="text" name="nome" class="form-control" placeholder="Digite o nome do campista" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar Campista</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: TROCAR / MOVER DE EQUIPA -->
<div class="modal fade no-print" id="modalTrocarEquipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="trocar_equipe_campista">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right text-primary me-2"></i>Mover Campista de Equipe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Selecione o Campista</label>
                    <select name="campista_id" class="form-select" required>
                        <option value="">-- Selecione o campista --</option>
                        <?php foreach ($equipes as$eq): ?>
                            <optgroup label="<?= htmlspecialchars($eq['nome']) ?>">
                                <?php foreach ($eq['campistas'] as$c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small">Nova Equipe / Comunidade Destino</label>
                    <select name="nova_equipe_id" class="form-select" required>
                        <option value="">-- Selecione a equipe destino --</option>
                        <?php foreach ($equipes as$eq): ?>
                            <option value="<?= $eq['id'] ?>"><?= htmlspecialchars($eq['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Confirmar Troca</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function abrirModalLider(equipeId) {
        document.getElementById('lider_equipe_id').value = equipeId;
        var modal = new bootstrap.Modal(document.getElementById('modalAdicionarLider'));
        modal.show();
    }

    function abrirModalCampista(equipeId) {
        document.getElementById('campista_equipe_id').value = equipeId;
        var modal = new bootstrap.Modal(document.getElementById('modalAdicionarCampista'));
        modal.show();
    }

    function abrirModalExcluirEquipe(equipeId, nomeEquipe) {
        document.getElementById('delete_equipe_id').value = equipeId;
        document.getElementById('delete_equipe_nome').innerText = nomeEquipe;
        var modal = new bootstrap.Modal(document.getElementById('modalExcluirEquipe'));
        modal.show();
    }

    function abrirModalGerenciarEquipe(equipe) {
        document.getElementById('gerenciar_equipe_id').value = equipe.id;
        document.getElementById('gerenciar_nome_equipe').value = equipe.nome;
        document.getElementById('gerenciar_cor_equipe').value = equipe.cor;

        // Renderiza lista de Líderes
        let containerLideres = document.getElementById('container_lideres_gerenciar');
        containerLideres.innerHTML = '';

        if (!equipe.lideres || equipe.lideres.length === 0) {
            containerLideres.innerHTML = '<p class="text-muted small">Nenhum líder cadastrado nesta comunidade.</p>';
        } else {
            equipe.lideres.forEach(function(lider) {
                let div = document.createElement('div');
                div.className = "mb-2 p-2 border rounded d-flex gap-2 align-items-center bg-light";
                div.innerHTML = `
                    <form method="POST" class="d-flex gap-2 flex-grow-1 m-0">
                        <input type="hidden" name="acao" value="editar_lider">
                        <input type="hidden" name="lider_id" value="${lider.id}">
                        <input type="text" name="cargo" class="form-control form-control-sm w-25" value="${lider.cargo || ''}" required placeholder="Cargo">
                        <input type="text" name="nome" class="form-control form-control-sm w-50" value="${lider.nome || ''}" required placeholder="Nome">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                    </form>
                    <form method="POST" class="m-0" onsubmit="return confirm('Excluir este líder?')">
                        <input type="hidden" name="acao" value="excluir_lider">
                        <input type="hidden" name="lider_id" value="${lider.id}">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                `;
                containerLideres.appendChild(div);
            });
        }

        // Renderiza lista de Campistas
        let containerCampistas = document.getElementById('container_campistas_gerenciar');
        containerCampistas.innerHTML = '';

        if (!equipe.campistas || equipe.campistas.length === 0) {
            containerCampistas.innerHTML = '<p class="text-muted small">Nenhum campista cadastrado nesta comunidade.</p>';
        } else {
            equipe.campistas.forEach(function(campista) {
                let div = document.createElement('div');
                div.className = "mb-2 p-2 border rounded d-flex gap-2 align-items-center bg-light";
                div.innerHTML = `
                    <form method="POST" class="d-flex gap-2 flex-grow-1 m-0">
                        <input type="hidden" name="acao" value="editar_campista">
                        <input type="hidden" name="campista_id" value="${campista.id}">
                        <input type="text" name="nome" class="form-control form-control-sm" value="${campista.nome || ''}" required placeholder="Nome do campista">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i> Salvar</button>
                    </form>
                    <form method="POST" class="m-0" onsubmit="return confirm('Excluir este campista?')">
                        <input type="hidden" name="acao" value="excluir_campista">
                        <input type="hidden" name="campista_id" value="${campista.id}">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                `;
                containerCampistas.appendChild(div);
            });
        }

        var modal = new bootstrap.Modal(document.getElementById('modalGerenciarEquipe'));
        modal.show();
    }
</script>
</body>
</html>