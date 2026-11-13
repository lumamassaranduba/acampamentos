<?php
// missa.php
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

// Lista de opções do ENUM permitidas
$dias_enum_permitidos = ['1º Dia', '2º Dia', '3º Dia', '4º Dia', '5º Dia', '6º Dia', '7º Dia'];

// --- 1. NOME DA EDIÇÃO E MODALIDADE ---
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

// --- 2. SALVAR / EDITAR MISSA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_salvar_missa'])) {
    $missa_id  = intval($_POST['missa_id'] ?? 0);
    $titulo    = trim($_POST['titulo'] ?? '');
    $dia_missa = trim($_POST['dia_missa'] ?? '');
    $horario   = trim($_POST['horario'] ?? '');
    $leituras  = trim($_POST['leituras'] ?? '');
    $preces    = trim($_POST['preces'] ?? '');

    if (!in_array($dia_missa, $dias_enum_permitidos)) {
        $dia_missa = $dias_enum_permitidos[0];
    }

    if (!empty($titulo) && $edicao_id > 0) {
        if ($missa_id > 0) {
            $stmt = $conn->prepare("UPDATE missas SET titulo = ?, dia_missa = ?, horario = ?, leituras = ?, preces = ? WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("sssssii", $titulo, $dia_missa, $horario, $leituras, $preces, $missa_id, $edicao_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO missas (edicao_id, titulo, dia_missa, horario, leituras, preces) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $edicao_id, $titulo, $dia_missa, $horario, $leituras, $preces);
        }

        if ($stmt->execute()) {
            $mensagem_sucesso = "Missa salva com sucesso!";
        } else {
            $mensagem_erro = "Erro ao salvar as informações da missa.";
        }
    } else {
        $mensagem_erro = "Preencha o título da missa.";
    }
}

// --- 3. EXCLUIR MISSA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_missa'])) {
    $missa_id_del = intval($_POST['missa_id'] ?? 0);
    $stmt_del = $conn->prepare("DELETE FROM missas WHERE id = ? AND edicao_id = ?");
    $stmt_del->bind_param("ii", $missa_id_del, $edicao_id);
    if ($stmt_del->execute()) {
        $mensagem_sucesso = "Missa removida com sucesso!";
    } else {
        $mensagem_erro = "Erro ao excluir a missa.";
    }
}

// --- 4. RESGATAR MISSAS DE OUTRA EDIÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_resgatar_missas'])) {
    $edicao_origem_id = intval($_POST['edicao_origem_id'] ?? 0);
    if ($edicao_origem_id > 0 && $edicao_id > 0) {
        $stmt_copy = $conn->prepare("
            INSERT INTO missas (edicao_id, titulo, dia_missa, horario, leituras, preces)
            SELECT ?, titulo, dia_missa, horario, leituras, preces
            FROM missas
            WHERE edicao_id = ?
            ORDER BY dia_missa ASC, horario ASC, id ASC
        ");
        $stmt_copy->bind_param("ii", $edicao_id, $edicao_origem_id);
        if ($stmt_copy->execute()) {
            $qtd = $stmt_copy->affected_rows;
            if ($qtd > 0) {
                $mensagem_sucesso = "{$qtd} missa(s) resgatada(s) com sucesso!";
            } else {
                $mensagem_erro = "A edição selecionada não possui missas cadastradas.";
            }
        } else {
            $mensagem_erro = "Erro ao resgatar as missas: " . $conn->error;
        }
    }
}

// --- 5. BUSCA EDIÇÕES ANTERIORES DA MESMA MODALIDADE PARA RESGATE ---
$edicoes_anteriores = [];
if ($edicao_id > 0 && $modalidade_id > 0) {
    $stmt_ed_ant = $conn->prepare("
        SELECT DISTINCT e.id, e.ano, e.nome 
        FROM edicoes e
        INNER JOIN missas m ON m.edicao_id = e.id
        WHERE e.modalidade_id = ? AND e.id != ?
        ORDER BY e.ano DESC, e.id DESC
    ");
    $stmt_ed_ant->bind_param("ii", $modalidade_id, $edicao_id);
    $stmt_ed_ant->execute();
    $edicoes_anteriores = $stmt_ed_ant->get_result()->fetch_all(MYSQLI_ASSOC);
}

// --- 6. BUSCA AS MISSAS DA EDIÇÃO ATUAL ---
$stmt_missas = $conn->prepare("SELECT * FROM missas WHERE edicao_id = ? ORDER BY dia_missa ASC, horario ASC");
$stmt_missas->bind_param("i", $edicao_id);
$stmt_missas->execute();
$res_missas = $stmt_missas->get_result();

$missas = [];
while ($row = $res_missas->fetch_assoc()) {
    $missas[] = $row;
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
    <title>Missas, Leituras e Preces - ÉFATA</title>
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
        .btn-gold:hover, .btn-gold:disabled {
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

        /* CARDS MINIMALISTAS */
        .card-missa {
            background: #ffffff;
            border: 1px solid #EAECEF;
            border-top: 3px solid var(--gold-primary);
            border-radius: 16px;
            padding: 1.35rem;
            transition: all 0.2s ease;
        }
        .card-missa:hover {
            border-color: #CBD5E1;
            box-shadow: 0 8px 20px -6px rgba(10, 25, 47, 0.08);
        }

        .badge-dia {
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            font-weight: 700;
            font-size: 0.74rem;
            padding: 4px 11px;
            border-radius: 50px;
            letter-spacing: 0.3px;
        }

        .texto-leitura {
            white-space: pre-wrap;
            font-size: 1rem;
            line-height: 1.68;
            background: #F8FAFC;
            padding: 1.15rem;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            color: #1E293B;
            word-break: break-word;
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

        .header-actions-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        @media (max-width: 767.98px) {
            .header-actions-group {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
            .header-actions-group .btn {
                justify-content: center;
                padding-top: 10px;
                padding-bottom: 10px;
            }
        }
    </style>
</head>
<body>

    <!-- IFRAME OCULTO PARA IMPRESSÃO DIRETA SEM ABRIR NOVA GUIA -->
    <iframe id="iframeImpressaoMissa" style="position: fixed; width: 0; height: 0; border: 0; visibility: hidden;"></iframe>

    <!-- NAVBAR PADRONIZADA -->
    <nav class="top-navbar d-flex align-items-center justify-content-between gap-2">
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
    <div class="bg-white border-bottom py-3">
        <div class="container d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center justify-content-between gap-3" style="max-width: 1200px;">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($url_voltar) ?>" class="btn-only-arrow" title="Voltar">
                    <i class="bi bi-arrow-left fs-5"></i>
                </a>
                <div>
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-journal-bookmark-fill" style="color: var(--gold-primary);"></i>Missas, Leituras e Preces
                    </h5>
                    <small class="text-muted fw-medium">
                        <?= !empty($nome_completo_edicao) ? htmlspecialchars($nome_completo_edicao) : 'Liturgia e celebrações da edição' ?>
                    </small>
                </div>
            </div>

            <div class="header-actions-group">
                <?php if ($edicao_id > 0): ?>
                    <button type="button" class="btn btn-resgatar-discreto rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalResgatarEdicao" title="Importar missas de outra edição">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Resgatar edição</span>
                    </button>
                <?php endif; ?>

                <button type="button" class="btn btn-gold rounded-pill px-4 py-2 btn-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalMissa" onclick="limparModalMissa()">
                    <i class="bi bi-plus-lg"></i>
                    <span>Nova Missa</span>
                </button>
            </div>
        </div>
    </div>

    <main class="container py-4" style="max-width: 1200px;">

        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success rounded-4 border-0 shadow-sm alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($mensagem_sucesso) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-danger rounded-4 border-0 shadow-sm alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($mensagem_erro) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- LISTA DE MISSAS -->
        <div class="row g-3 g-md-4">
            <?php foreach ($missas as $m): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card-missa h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div class="min-w-0">
                                    <span class="badge-dia mb-2 d-inline-block"><?= htmlspecialchars($m['dia_missa']) ?></span>
                                    <h5 class="fw-bold text-dark m-0" style="word-break: break-word;"><?= htmlspecialchars($m['titulo']) ?></h5>
                                </div>
                                <div class="dropdown flex-shrink-0">
                                    <button class="btn btn-link text-muted p-1 border-0" data-bs-toggle="dropdown" aria-label="Opções">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end border-0 shadow-sm rounded-3">
                                        <li>
                                            <button type="button" class="dropdown-item small py-2" onclick='editarMissa(<?= htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8') ?>)'>
                                                <i class="bi bi-pencil me-2 text-primary"></i>Editar
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button type="button" class="dropdown-item small py-2 text-danger" onclick='confirmarExclusaoMissa(<?= intval($m['id']) ?>, <?= htmlspecialchars(json_encode($m['titulo']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($m['dia_missa']), ENT_QUOTES, 'UTF-8') ?>)'>
                                                <i class="bi bi-trash me-2"></i>Excluir
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <div class="text-muted small mb-3">
                                <?php if (!empty($m['horario'])): ?>
                                    <i class="bi bi-clock me-1" style="color: var(--gold-primary);"></i> Horário: <?= date('H:i', strtotime($m['horario'])) ?>
                                <?php else: ?>
                                    <i class="bi bi-clock me-1"></i> Horário não definido
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- BOTÕES NA PALETA ÉFATA -->
                        <div class="d-flex flex-column gap-2 mt-3 pt-3 border-top">
                            <div class="d-grid gap-2" style="grid-template-columns: 1fr 1fr;">
                                <button type="button" class="btn btn-outline-navy btn-sm rounded-pill py-2 d-flex align-items-center justify-content-center gap-1"
                                        onclick='abrirModalLeitura(<?= htmlspecialchars(json_encode("Leituras - " . $m['titulo'] . " (" . $m['dia_missa'] . ")"), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($m['leituras'] ?? "Nenhuma leitura cadastrada."), ENT_QUOTES, 'UTF-8') ?>)'>
                                    <i class="bi bi-book"></i> Leituras
                                </button>

                                <button type="button" class="btn btn-outline-gold btn-sm rounded-pill py-2 d-flex align-items-center justify-content-center gap-1"
                                        onclick='abrirModalLeitura(<?= htmlspecialchars(json_encode("Preces - " . $m['titulo'] . " (" . $m['dia_missa'] . ")"), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($m['preces'] ?? "Nenhuma prece cadastrada."), ENT_QUOTES, 'UTF-8') ?>)'>
                                    <i class="bi bi-chat-quote"></i> Preces
                                </button>
                            </div>

                            <!-- IMPRIMIR DIRETO APENAS LEITURAS E PRECES DESTE DIA -->
                            <button type="button" id="btnPrintMissa_<?= $m['id'] ?>" onclick="imprimirMissaDireto(<?= intval($m['id']) ?>, this)" class="btn btn-navy btn-sm rounded-pill py-2 w-100 d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-printer-fill" style="color: var(--gold-primary);"></i>
                                <span class="btn-text">Imprimir / PDF (<?= htmlspecialchars($m['dia_missa']) ?>)</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($missas)): ?>
                <div class="col-12">
                    <div class="text-center py-5 bg-white rounded-4 border p-4">
                        <i class="bi bi-journal-x fs-1 d-block mb-2" style="color: var(--gold-primary);"></i>
                        <h6 class="fw-bold text-dark mb-1">Nenhuma missa cadastrada</h6>
                        <p class="text-muted small mb-3">Cadastre as celebrações, leituras e preces desta edição ou importe de um ano anterior.</p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <?php if ($edicao_id > 0 && !empty($edicoes_anteriores)): ?>
                                <button type="button" class="btn btn-resgatar-discreto rounded-pill px-3 py-2 btn-sm" data-bs-toggle="modal" data-bs-target="#modalResgatarEdicao">
                                    <i class="bi bi-arrow-repeat me-1"></i> Resgatar edição
                                </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-gold rounded-pill px-4 py-2 btn-sm" data-bs-toggle="modal" data-bs-target="#modalMissa" onclick="limparModalMissa()">
                                <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeira Missa
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MODAL PARA CADASTRAR / EDITAR MISSA -->
    <div class="modal fade" id="modalMissa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form action="" method="POST" class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <input type="hidden" name="acao_salvar_missa" value="1">
                <input type="hidden" name="missa_id" id="missa_id" value="0">

                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold text-white m-0" id="modalMissaTitulo">Cadastrar Missa</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body modal-body-scroll p-3 p-md-4">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Tema</label>
                        <input type="text" name="titulo" id="missa_titulo" class="form-control rounded-3" placeholder="Ex: Missa de Abertura" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Dia do Acampamento *</label>
                            <select name="dia_missa" id="missa_dia" class="form-select rounded-3" required>
                                <?php foreach ($dias_enum_permitidos as $opcao_dia): ?>
                                    <option value="<?= $opcao_dia ?>"><?= $opcao_dia ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label small fw-semibold">Horário</label>
                            <input type="time" name="horario" id="missa_horario" class="form-control rounded-3">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold"><i class="bi bi-book me-1" style="color: var(--gold-primary);"></i> Leituras (Primeira Leitura, Salmo, Evangelho, etc.)</label>
                        <textarea name="leituras" id="missa_leituras" class="form-control rounded-3" rows="5" placeholder="Digite ou cole aqui as leituras da missa..."></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-semibold"><i class="bi bi-chat-quote me-1" style="color: var(--gold-primary);"></i> Preces dos Fiéis</label>
                        <textarea name="preces" id="missa_preces" class="form-control rounded-3" rows="4" placeholder="Digite ou cole aqui as preces..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold rounded-pill px-4">Salvar Missa</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL RESGATAR MISSAS DE OUTRA EDIÇÃO -->
    <div class="modal fade" id="modalResgatarEdicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="" method="POST" class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <input type="hidden" name="acao_resgatar_missas" value="1">

                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat text-warning fs-5"></i>
                        <span>Resgatar Missas de Outra Edição</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body p-4">
                    <?php if (empty($edicoes_anteriores)): ?>
                        <div class="text-center py-3 text-muted">
                            <i class="bi bi-info-circle fs-2 d-block mb-2" style="color: var(--gold-primary);"></i>
                            <p class="mb-0 small">Nenhuma outra edição desta modalidade possui missas cadastradas ainda.</p>
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
                                Todas as missas, leituras e preces do ano escolhido serão duplicadas para esta edição. Alterações feitas aqui <strong>não</strong> afetarão a edição anterior.
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

    <!-- MODAL EXIBIÇÃO DE LEITURA / PRECES -->
    <div class="modal fade" id="modalExibirTexto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 overflow-hidden shadow">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold text-white m-0" id="exibirTitulo">Leitura</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body modal-body-scroll p-3 p-md-4">
                    <div class="texto-leitura" id="exibirConteudo"></div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-navy rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL CARD DE CONFIRMAÇÃO DE EXCLUSÃO -->
    <div class="modal fade" id="modalConfirmarExclusaoMissa" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <form action="" method="POST" class="modal-content border-0 rounded-4 overflow-hidden shadow-lg text-center">
                <input type="hidden" name="acao_excluir_missa" value="1">
                <input type="hidden" name="missa_id" id="excluir_missa_id" value="0">

                <div class="modal-header modal-header-custom p-3 justify-content-center">
                    <h5 class="modal-title fw-bold fs-6 m-0 text-white d-flex align-items-center gap-2">
                        <i class="bi bi-trash3 text-warning fs-5"></i> Confirmar Exclusão
                    </h5>
                </div>

                <div class="modal-body p-4">
                    <div class="delete-icon-circle">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h6 class="fw-bold text-dark mb-1">Excluir esta missa?</h6>
                    <p class="fw-semibold text-dark small mb-2" id="excluir_missa_preview" style="word-break: break-word;"></p>
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
    <div class="modal fade" id="modalConfirmarLogout" tabindex="-1" aria-hidden="true">
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
        function limparModalMissa() {
            document.getElementById('modalMissaTitulo').textContent = 'Cadastrar Missa';
            document.getElementById('missa_id').value = '0';
            document.getElementById('missa_titulo').value = '';
            document.getElementById('missa_dia').value = '1º Dia';
            document.getElementById('missa_horario').value = '';
            document.getElementById('missa_leituras').value = '';
            document.getElementById('missa_preces').value = '';
        }

        function editarMissa(missa) {
            document.getElementById('modalMissaTitulo').textContent = 'Editar Missa';
            document.getElementById('missa_id').value = missa.id;
            document.getElementById('missa_titulo').value = missa.titulo || '';
            document.getElementById('missa_dia').value = missa.dia_missa || '1º Dia';
            document.getElementById('missa_horario').value = missa.horario || '';
            document.getElementById('missa_leituras').value = missa.leituras || '';
            document.getElementById('missa_preces').value = missa.preces || '';

            var modal = new bootstrap.Modal(document.getElementById('modalMissa'));
            modal.show();
        }

        function abrirModalLeitura(titulo, conteudo) {
            document.getElementById('exibirTitulo').textContent = titulo;
            document.getElementById('exibirConteudo').textContent = conteudo || 'Nenhum texto informado.';
            var modal = new bootstrap.Modal(document.getElementById('modalExibirTexto'));
            modal.show();
        }

        function confirmarExclusaoMissa(id, titulo, dia) {
            document.getElementById('excluir_missa_id').value = id;
            document.getElementById('excluir_missa_preview').textContent = '“' + titulo + ' (' + dia + ')”';
            var modal = new bootstrap.Modal(document.getElementById('modalConfirmarExclusaoMissa'));
            modal.show();
        }

        function imprimirMissaDireto(missaId, btn) {
            const iframe = document.getElementById('iframeImpressaoMissa');
            const spanTexto = btn.querySelector('.btn-text');
            const textoOriginal = spanTexto.textContent;

            btn.disabled = true;
            spanTexto.textContent = 'Preparando...';

            iframe.onload = function () {
                btn.disabled = false;
                spanTexto.textContent = textoOriginal;
                try {
                    iframe.contentWindow.focus();
                    iframe.contentWindow.print();
                } catch (e) {
                    window.location.href = 'gerar-pdf-missa.php?id=' + missaId;
                }
            };

            iframe.src = 'gerar-pdf-missa.php?id=' + missaId + '&t=' + Date.now();
        }
    </script>
</body>
</html>