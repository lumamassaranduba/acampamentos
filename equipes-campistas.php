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

    // AJAX para Mover Campista via Drag & Drop
    if ($acao === 'mover_campista_ajax') {
        header('Content-Type: application/json');
        $campista_id = intval($_POST['campista_id'] ?? 0);
        $nova_equipe_id = intval($_POST['nova_equipe_id'] ?? 0);

        if ($campista_id > 0 && $nova_equipe_id > 0) {
            $stmt = $conn->prepare("UPDATE equipes_membros SET equipe_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $nova_equipe_id, $campista_id);
            if ($stmt->execute()) {
                echo json_encode(['sucesso' => true]);
            } else {
                echo json_encode(['sucesso' => false, 'erro' => $conn->error]);
            }
        } else {
            echo json_encode(['sucesso' => false, 'erro' => 'IDs inválidos']);
        }
        exit;
    }

    // EQUIPE
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

    // LÍDERES
    if ($acao === 'adicionar_lider') {
        $equipe_id = intval($_POST['equipe_id'] ?? 0);
        $cargo     = trim($_POST['cargo'] ?? 'Confidente');
        $nome      = trim($_POST['nome'] ?? '');

        if ($equipe_id > 0 && !empty($nome)) {
            $stmt_p = $conn->prepare("INSERT INTO pessoas (nome) VALUES (?) ON DUPLICATE KEY UPDATE id=id");
            $stmt_p->bind_param("s", $nome);
            $stmt_p->execute();

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
        $cargo    = trim($_POST['cargo'] ?? '');
        $nome     = trim($_POST['nome'] ?? '');

        if ($lider_id > 0 && !empty($nome)) {
            $stmt_p = $conn->prepare("INSERT INTO pessoas (nome) VALUES (?) ON DUPLICATE KEY UPDATE id=id");
            $stmt_p->bind_param("s", $nome);
            $stmt_p->execute();

            $stmt_u = $conn->prepare("UPDATE equipes_lideres SET cargo = ?, nome = ? WHERE id = ?");
            $stmt_u->bind_param("ssi", $cargo, $nome, $lider_id);
            $stmt_u->execute();
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'excluir_lider') {
        $lider_id = intval($_POST['lider_id'] ?? 0);
        if ($lider_id > 0) {
            $stmt_d = $conn->prepare("DELETE FROM equipes_lideres WHERE id = ?");
            $stmt_d->bind_param("i", $lider_id);
            $stmt_d->execute();
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    // CAMPISTAS
    if ($acao === 'adicionar_campista') {
        $equipe_id = intval($_POST['equipe_id'] ?? 0);
        $nome      = trim($_POST['nome'] ?? '');

        if ($equipe_id > 0 && !empty($nome)) {
            $stmt_p = $conn->prepare("INSERT INTO pessoas (nome) VALUES (?) ON DUPLICATE KEY UPDATE id=id");
            $stmt_p->bind_param("s", $nome);
            $stmt_p->execute();

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
            $stmt_p = $conn->prepare("INSERT INTO pessoas (nome) VALUES (?) ON DUPLICATE KEY UPDATE id=id");
            $stmt_p->bind_param("s", $nome);
            $stmt_p->execute();

            $stmt_u = $conn->prepare("UPDATE equipes_membros SET nome = ? WHERE id = ?");
            $stmt_u->bind_param("si", $nome, $campista_id);
            $stmt_u->execute();
        }
        header("Location: equipes-campistas.php?edicao_id=" . $edicao_id);
        exit;
    }

    if ($acao === 'excluir_campista') {
        $campista_id = intval($_POST['campista_id'] ?? 0);
        if ($campista_id > 0) {
            $stmt_d = $conn->prepare("DELETE FROM equipes_membros WHERE id = ?");
            $stmt_d->bind_param("i", $campista_id);
            $stmt_d->execute();
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

// 5. Busca as equipas da edição
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

// 6. Lista todos os campistas para a modal de Trocar de Equipe
$todos_campistas = [];
$res_tc = $conn->query("
    SELECT em.id, em.nome, em.equipe_id, ec.nome AS equipe_nome 
    FROM equipes_membros em
    JOIN equipes_campistas ec ON em.equipe_id = ec.id
    WHERE ec.edicao_id = $edicao_id
    ORDER BY em.nome ASC
");
if ($res_tc) {
    $todos_campistas = $res_tc->fetch_all(MYSQLI_ASSOC);
}

// 7. Busca histórico unificado para Autocomplete
$res_hist = $conn->query("
    SELECT DISTINCT nome, telefone FROM (
        SELECT nome, telefone FROM pessoas
        UNION
        SELECT nome, telefone FROM equipe_geral
        UNION
        SELECT nome, NULL as telefone FROM equipes_membros
    ) AS h_todos
    WHERE nome IS NOT NULL AND nome != ''
    ORDER BY nome ASC
");
$pessoas_historico = $res_hist ? $res_hist->fetch_all(MYSQLI_ASSOC) : [];
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
    
    <!-- Tom Select -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root {
            --cor-modalidade: <?= $cor ?>;
            --nav-bg: #0d1b2a;
            --nav-yellow: #e5b338;
            --nav-red: #e63946;
        }

        body { font-family: 'Outfit', sans-serif; background-color: #fcfbf9; color: #1c1917; margin: 0; padding: 0; }
        .navbar-custom { background-color: var(--nav-bg); padding: 0.85rem 2rem; color: #ffffff; }
        .user-profile-container { display: flex; align-items: center; gap: 12px; }
        .logo-box { width: 48px; height: 48px; border: 1.5px solid var(--nav-yellow); border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; background-color: rgba(255, 255, 255, 0.05); flex-shrink: 0; }
        .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; padding: 4px; }
        .user-info-text .user-name { font-weight: 700; font-size: 1.05rem; color: #ffffff; line-height: 1.2; }
        .user-info-text .user-role { font-size: 0.78rem; color: #94a3b8; letter-spacing: 0.5px; font-weight: 500; }
        .btn-nav-outline-red { background-color: transparent; color: #f87171; border: 1px solid var(--nav-red); font-weight: 600; border-radius: 10px; padding: 0.45rem 1.1rem; font-size: 0.9rem; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }

        .main-content { padding: 32px 48px; width: 100%; max-width: 1400px; margin: 0 auto; box-sizing: border-box; }
        .equipe-card { background: #ffffff; border: 1.5px solid #000000; border-radius: 0px; padding: 0; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .equipe-header { padding: 8px 12px; font-weight: 800; font-size: 0.95rem; color: #000000; text-transform: uppercase; border-bottom: 1.5px solid #000000; display: flex; justify-content: space-between; align-items: center; gap: 10px; }
        .equipe-header-btns { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
        .equipe-header-btns .btn { font-size: 0.8rem; font-weight: 600; padding: 3px 8px !important; line-height: 1.2; }

        .table-lideres, .table-campistas { width: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .table-lideres td, .table-campistas td { border: 1px solid #000000; padding: 6px 12px; color: #000000; vertical-align: middle; }
        .table-lideres tr td:first-child { font-weight: 600; width: 35%; background-color: #fcfcfc; }
        .campistas-section-title { text-align: center; font-weight: 800; font-size: 0.85rem; letter-spacing: 1px; padding: 8px 0; color: #000000; text-transform: uppercase; border-top: 1.5px solid #000000; border-bottom: 1.5px solid #000000; }
        .table-campistas td.num-col { width: 45px; text-align: center; font-weight: 700; border-right: 1px solid #000000; }

        /* Estilo para arrastar campistas */
        .table-campistas tbody tr.campista-row { cursor: grab; }
        .table-campistas tbody tr.campista-row:active { cursor: grabbing; background-color: #f1f5f9; }

        .item-row-actions { display: none; gap: 4px; }
        tr:hover .item-row-actions { display: inline-flex; }

        /* Estilização Tom Select */
        .ts-control { border-radius: 12px !important; padding: 10px 14px !important; border: 1.5px solid #000000 !important; font-size: 0.95rem !important; }
        .ts-dropdown { border-radius: 12px !important; border: 1px solid #e2e8f0 !important; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; margin-top: 4px !important; }
        .ts-dropdown .option { padding: 10px 14px !important; font-size: 0.95rem !important; }

        @media print {
            .navbar-custom, .no-print, .btn, .equipe-header-btns, .item-row-actions { display: none !important; }
            body { background-color: #ffffff !important; }
            .main-content { padding: 0 !important; max-width: 100% !important; margin: 0 !important; }
            .equipe-col { width: 100% !important; page-break-after: always !important; }
            .equipe-card { border: 2px solid #000000 !important; }
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
    <div class="bg-white p-4 rounded-4 border mb-4 shadow-sm d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 no-print">
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
    <div class="row" id="gridEquipes">
        <?php foreach ($equipes as $equipe): ?>
            <div class="col-12 col-lg-6 equipe-col mb-4" data-id="<?= $equipe['id'] ?>">
                <div class="equipe-card">
                    
                    <div class="equipe-header" style="background-color: <?= htmlspecialchars($equipe['cor']) ?> !important;">
                        <span class="text-truncate"><?= htmlspecialchars($equipe['nome']) ?></span>
                        
                        <div class="equipe-header-btns no-print">
                            <button class="btn btn-sm btn-light text-dark rounded-1" title="Adicionar Líder" onclick="abrirModalLider(<?= $equipe['id'] ?>)">+ Líder</button>
                            <button class="btn btn-sm btn-light text-dark rounded-1 fw-bold" title="Adicionar Campista" onclick="abrirModalCampista(<?= $equipe['id'] ?>)">+ Campista</button>
                            <button class="btn btn-sm btn-light text-primary rounded-1" title="Editar Equipa" onclick="abrirModalEditarEquipe(<?= $equipe['id'] ?>, '<?= addslashes($equipe['nome']) ?>', '<?= $equipe['cor'] ?>')">
                                <i class="bi bi-pencil-square"></i>
                            </button>
                            <button class="btn btn-sm btn-light text-danger rounded-1" title="Excluir Equipa" onclick="abrirModalExcluirEquipe(<?= $equipe['id'] ?>, '<?= addslashes($equipe['nome']) ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- LÍDERES -->
                    <table class="table-lideres">
                        <tbody>
                            <?php if (!empty($equipe['lideres'])): ?>
                                <?php foreach ($equipe['lideres'] as $lider): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($lider['cargo'] ?? '') ?></td>
                                        <td class="d-flex align-items-center justify-content-between">
                                            <span><?= htmlspecialchars($lider['nome'] ?? '') ?></span>
                                            <div class="item-row-actions no-print">
                                                <button class="btn btn-link text-primary p-0 border-0" onclick="abrirModalEditarLider(<?= $lider['id'] ?>, '<?= addslashes($lider['cargo']) ?>', '<?= addslashes($lider['nome']) ?>')"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-link text-danger p-0 border-0 ms-1" onclick="abrirModalExcluirLider(<?= $lider['id'] ?>, '<?= addslashes($lider['nome']) ?>')"><i class="bi bi-x-circle"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-center text-muted py-2">Nenhum líder cadastrado</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="campistas-section-title">CAMPISTAS</div>

                    <!-- CAMPISTAS (SUPORTE A DRAG & DROP ENTRE EQUIPES) -->
                    <table class="table-campistas">
                        <tbody class="sortable-campistas" data-equipe-id="<?= $equipe['id'] ?>">
                            <?php 
                            $total_linhas = max(13, count($equipe['campistas'])); 
                            for ($i = 0; $i < $total_linhas; $i++): 
                                $num = $i + 1;
                                $campista_id = $equipe['campistas'][$i]['id'] ?? 0;
                                $campista_nome = $equipe['campistas'][$i]['nome'] ?? '';
                            ?>
                                <tr class="<?= !empty($campista_nome) ? 'campista-row' : 'empty-row' ?>" data-campista-id="<?= $campista_id ?>">
                                    <td class="num-col"><?= $num ?></td>
                                    <td class="d-flex align-items-center justify-content-between" style="min-height: 33px;">
                                        <span><?= htmlspecialchars($campista_nome) ?></span>
                                        <?php if (!empty($campista_nome)): ?>
                                            <div class="item-row-actions no-print">
                                                <button class="btn btn-link text-primary p-0 border-0" onclick="abrirModalEditarCampista(<?= $campista_id ?>, '<?= addslashes($campista_nome) ?>')"><i class="bi bi-pencil"></i></button>
                                                <button class="btn btn-link text-danger p-0 border-0 ms-1" onclick="abrirModalExcluirCampista(<?= $campista_id ?>, '<?= addslashes($campista_nome) ?>')"><i class="bi bi-trash"></i></button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>

                </div>
            </div>
        <?php endforeach; ?>
    </div>

</main>

<!-- MODAIS DE LÍDERES E CAMPISTAS -->
<div class="modal fade no-print" id="modalEditarLider" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="editar_lider">
            <input type="hidden" name="lider_id" id="edt_lider_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Editar Líder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Cargo / Nomeação</label>
                    <input type="text" name="cargo" id="edt_lider_cargo" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome do Líder</label>
                    <input type="text" name="nome" id="edt_lider_nome" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Atualizar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="modalExcluirLider" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="excluir_lider">
            <input type="hidden" name="lider_id" id="exc_lider_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">Remover Líder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="m-0 fs-6">Deseja remover <strong id="exc_lider_nome"></strong> do cargo de líder?</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger rounded-3 px-4">Sim, Remover</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="modalEditarCampista" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="editar_campista">
            <input type="hidden" name="campista_id" id="edt_campista_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Editar Campista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome Completo</label>
                    <input type="text" name="nome" id="edt_campista_nome" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Salvar Alterações</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="modalExcluirCampista" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="excluir_campista">
            <input type="hidden" name="campista_id" id="exc_campista_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">Remover Campista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="m-0 fs-6">Tem certeza que deseja remover o campista <strong id="exc_campista_nome"></strong> desta equipe?</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger rounded-3 px-4">Sim, Remover</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="modalAdicionarCampista" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="adicionar_campista">
            <input type="hidden" name="equipe_id" id="campista_equipe_id" value="">
            
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Adicionar Campista</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome do Campista</label>
                    <select name="nome" id="select-campista-historico" placeholder="Digite o nome..." required>
                        <option value=""></option>
                        <?php foreach ($pessoas_historico as $ph): ?>
                            <?php $tel = !empty($ph['telefone']) ? " ({$ph['telefone']})" : ""; ?>
                            <option value="<?= htmlspecialchars($ph['nome']) ?>">
                                <?= htmlspecialchars($ph['nome']) . htmlspecialchars($tel) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar Campista</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="modalEditarEquipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="editar_equipe">
            <input type="hidden" name="equipe_id" id="edt_equipe_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Editar Equipa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome da Equipa</label>
                    <input type="text" name="nome_equipe" id="edt_nome_equipe" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Cor do Cabeçalho</label>
                    <input type="color" name="cor_equipe" id="edt_cor_equipe" class="form-control form-control-color w-100">
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Atualizar</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="modalExcluirEquipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="excluir_equipe">
            <input type="hidden" name="equipe_id" id="exc_equipe_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold text-danger">Excluir Equipa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <p class="m-0 fs-6">Tem certeza que deseja apagar a equipe <strong id="exc_nome_equipe"></strong>?</p>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger rounded-3 px-4">Sim, Excluir</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade no-print" id="modalCriarEquipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="criar_equipe">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Nova Equipa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome da Equipa</label>
                    <input type="text" name="nome_equipe" class="form-control" placeholder="Ex: COMUNIDADE PIEDADE - COR BRANCA" required>
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

<div class="modal fade no-print" id="modalAdicionarLider" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="adicionar_lider">
            <input type="hidden" name="equipe_id" id="lider_equipe_id" value="">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Adicionar Líder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Cargo / Nomeação</label>
                    <input type="text" name="cargo" class="form-control" value="Confidente" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nome do Líder</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar Líder</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL SELEÇÃO DE TROCA DE EQUIPE -->
<div class="modal fade no-print" id="modalTrocarEquipe" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 rounded-4 shadow">
            <input type="hidden" name="acao" value="trocar_equipe_campista">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Mover Campista de Equipa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold small">Selecione o Campista</label>
                    <select name="campista_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($todos_campistas as $tc): ?>
                            <option value="<?= $tc['id'] ?>"><?= htmlspecialchars($tc['nome']) ?> (Atualmente em: <?= htmlspecialchars($tc['equipe_nome']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small">Nova Equipa de Destino</label>
                    <select name="nova_equipe_id" class="form-select" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($equipes as $eq_dest): ?>
                            <option value="<?= $eq_dest['id'] ?>"><?= htmlspecialchars($eq_dest['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary rounded-3 px-4">Mover Campista</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
    let tomSelectCampista = null;

    document.addEventListener("DOMContentLoaded", function () {
        tomSelectCampista = new TomSelect("#select-campista-historico", {
            create: true,
            createOnBlur: true,
            maxItems: 1,
            sortField: { field: "text", direction: "asc" }
        });

        // Configura Drag & Drop para mover campistas entre tabelas de equipes com RELOAD AUTOMÁTICO
        const listasCampistas = document.querySelectorAll('.sortable-campistas');
        listasCampistas.forEach(lista => {
            new Sortable(lista, {
                group: 'campistas_group',
                animation: 150,
                draggable: '.campista-row',
                onEnd: function (evt) {
                    const itemEl = evt.item;
                    const campistaId = itemEl.getAttribute('data-campista-id');
                    const novaEquipeId = evt.to.getAttribute('data-equipe-id');

                    if (campistaId && novaEquipeId && evt.from !== evt.to) {
                        const formData = new FormData();
                        formData.append('acao', 'mover_campista_ajax');
                        formData.append('campista_id', campistaId);
                        formData.append('nova_equipe_id', novaEquipeId);

                        fetch('equipes-campistas.php?edicao_id=<?= $edicao_id ?>', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Atualiza a página imediatamente para o nome reaparecer posicionado na nova lista
                            location.reload();
                        })
                        .catch(() => {
                            location.reload();
                        });
                    }
                }
            });
        });
    });

    function abrirModalLider(equipeId) {
        document.getElementById('lider_equipe_id').value = equipeId;
        new bootstrap.Modal(document.getElementById('modalAdicionarLider')).show();
    }

    function abrirModalEditarLider(id, cargo, nome) {
        document.getElementById('edt_lider_id').value = id;
        document.getElementById('edt_lider_cargo').value = cargo;
        document.getElementById('edt_lider_nome').value = nome;
        new bootstrap.Modal(document.getElementById('modalEditarLider')).show();
    }

    function abrirModalExcluirLider(id, nome) {
        document.getElementById('exc_lider_id').value = id;
        document.getElementById('exc_lider_nome').innerText = nome;
        new bootstrap.Modal(document.getElementById('modalExcluirLider')).show();
    }

    function abrirModalCampista(equipeId) {
        document.getElementById('campista_equipe_id').value = equipeId;
        if (tomSelectCampista) {
            tomSelectCampista.clear();
        }
        new bootstrap.Modal(document.getElementById('modalAdicionarCampista')).show();
    }

    function abrirModalEditarCampista(id, nome) {
        document.getElementById('edt_campista_id').value = id;
        document.getElementById('edt_campista_nome').value = nome;
        new bootstrap.Modal(document.getElementById('modalEditarCampista')).show();
    }

    function abrirModalExcluirCampista(id, nome) {
        document.getElementById('exc_campista_id').value = id;
        document.getElementById('exc_campista_nome').innerText = nome;
        new bootstrap.Modal(document.getElementById('modalExcluirCampista')).show();
    }

    function abrirModalEditarEquipe(equipeId, nomeEquipe, corEquipe) {
        document.getElementById('edt_equipe_id').value = equipeId;
        document.getElementById('edt_nome_equipe').value = nomeEquipe;
        document.getElementById('edt_cor_equipe').value = corEquipe;
        new bootstrap.Modal(document.getElementById('modalEditarEquipe')).show();
    }

    function abrirModalExcluirEquipe(equipeId, nomeEquipe) {
        document.getElementById('exc_equipe_id').value = equipeId;
        document.getElementById('exc_nome_equipe').innerText = nomeEquipe;
        new bootstrap.Modal(document.getElementById('modalExcluirEquipe')).show();
    }
</script>
</body>
</html>