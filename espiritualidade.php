<?php
// espiritualidade.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil       = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL'));

$edicao_id_param     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id_param = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

$mensagem_sucesso = '';
$mensagem_erro = '';

// 1. PROCESSAR SALVAMENTO DA CHAMADA (MARCA APENAS QUEM FALTOU)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_presencas') {
    $esp_id = intval($_POST['espiritualidade_id'] ?? 0);
    $ausentes_ids = $_POST['ausentes'] ?? [];
    $justificativas = $_POST['justificativa'] ?? [];

    if ($esp_id > 0) {
        $sql_all = "SELECT id FROM `equipe_geral` WHERE 1=1";
        if ($edicao_id_param > 0) {
            $sql_all .= " AND (edicao_id = {$edicao_id_param} OR edicao_id IS NULL OR edicao_id = 0)";
        }
        $res_all = $conn->query($sql_all);

        if (!$res_all || $res_all->num_rows == 0) {
            $res_all = $conn->query("SELECT id FROM `pessoas`");
        }

        if ($res_all) {
            while ($m = $res_all->fetch_assoc()) {
                $m_id = intval($m['id']);
                $is_ausente = in_array($m_id, $ausentes_ids) ? 'ausente' : 'presente';
                $just = ($is_ausente === 'ausente') ? trim($justificativas[$m_id] ?? '') : '';

                $stmt_p = $conn->prepare("
                    INSERT INTO espiritualidade_presencas (espiritualidade_id, membro_id, status, justificativa)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), justificativa = VALUES(justificativa)
                ");
                $stmt_p->bind_param("iiss", $esp_id, $m_id, $is_ausente, $just);
                $stmt_p->execute();
            }
        }
        $mensagem_sucesso = "Chamada salva com sucesso!";
    }
}

// 2. EXCLUSÃO DE ESPIRITUALIDADE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_espiritualidade') {
    $esp_id = intval($_POST['espiritualidade_id'] ?? 0);
    if ($esp_id > 0) {
        $conn->query("DELETE FROM espiritualidade_presencas WHERE espiritualidade_id = $esp_id");
        $stmt_del = $conn->prepare("DELETE FROM espiritualidades WHERE id = ?");
        $stmt_del->bind_param("i", $esp_id);
        $stmt_del->execute();
        $mensagem_sucesso = "Momento excluído com sucesso!";
    }
}

// 3. CADASTRAR / EDITAR ESPIRITUALIDADE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_espiritualidade') {
    $esp_id = intval($_POST['espiritualidade_id'] ?? 0);
    $tipo = trim($_POST['tipo'] ?? 'Espiritualidade');
    $data_momento = !empty($_POST['data_momento']) ? $_POST['data_momento'] : NULL;
    $horario_momento = !empty($_POST['horario_momento']) ? $_POST['horario_momento'] : NULL;
    $animacao_responsavel = trim($_POST['animacao_responsavel'] ?? '');
    $oracao_inicial_responsavel = trim($_POST['oracao_inicial_responsavel'] ?? '');
    $pregacao_tema = trim($_POST['pregacao_tema'] ?? '');
    $pregacao_pregador = trim($_POST['pregacao_pregador'] ?? '');
    $detalhes = trim($_POST['detalhes'] ?? '');

    if ($esp_id > 0) {
        $stmt_u = $conn->prepare("UPDATE espiritualidades SET tipo=?, data_momento=?, horario_momento=?, animacao_responsavel=?, oracao_inicial_responsavel=?, pregacao_tema=?, pregacao_pregador=?, detalhes=? WHERE id=?");
        $stmt_u->bind_param("ssssssssi", $tipo, $data_momento, $horario_momento, $animacao_responsavel, $oracao_inicial_responsavel, $pregacao_tema, $pregacao_pregador, $detalhes, $esp_id);
        $stmt_u->execute();
        $mensagem_sucesso = "Momento atualizado com sucesso!";
    } else {
        $stmt_i = $conn->prepare("INSERT INTO espiritualidades (edicao_id, tipo, data_momento, horario_momento, animacao_responsavel, oracao_inicial_responsavel, pregacao_tema, pregacao_pregador, detalhes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_i->bind_param("issssssss", $edicao_id_param, $tipo, $data_momento, $horario_momento, $animacao_responsavel, $oracao_inicial_responsavel, $pregacao_tema, $pregacao_pregador, $detalhes);
        $stmt_i->execute();
        $mensagem_sucesso = "Momento criado com sucesso!";
    }
}

// 4. BUSCAR ESPIRITUALIDADES
$sql = "SELECT * FROM espiritualidades WHERE 1=1";
if ($edicao_id_param > 0) {
    $sql .= " AND (edicao_id = {$edicao_id_param} OR edicao_id IS NULL OR edicao_id = 0)";
}
$sql .= " ORDER BY data_momento ASC, horario_momento ASC, id DESC";
$res = $conn->query($sql);
$lista_espiritualidades = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 5. BUSCAR INTEGRANTES DA EQUIPE GERAL
$sql_membros = "SELECT id, nome FROM `equipe_geral` WHERE 1=1";
if ($edicao_id_param > 0) {
    $sql_membros .= " AND (edicao_id = {$edicao_id_param} OR edicao_id IS NULL OR edicao_id = 0)";
}
$sql_membros .= " ORDER BY nome ASC";
$res_membros = $conn->query($sql_membros);

if (!$res_membros || $res_membros->num_rows == 0) {
    $sql_membros = "SELECT id, nome FROM `pessoas` ORDER BY nome ASC";
    $res_membros = $conn->query($sql_membros);
}

$lista_membros = $res_membros ? $res_membros->fetch_all(MYSQLI_ASSOC) : [];

// 6. MATRIZ DE PRESENÇAS
$res_presencas_todas = $conn->query("SELECT * FROM espiritualidade_presencas");
$matriz_presencas = [];
if ($res_presencas_todas) {
    while ($row = $res_presencas_todas->fetch_assoc()) {
        $matriz_presencas[$row['espiritualidade_id']][$row['membro_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espiritualidade e Vivência</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --navy-dark: #0A192F;
            --navy-hover: #172A46;
            --gold-primary: #C5A059;
            --gold-hover: #B28D46;
            --bg-main: #F8FAFC;
            --border-color: #E2E8F0;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--bg-main);
            color: #0F172A;
            margin: 0;
            padding: 0;
        }

        /* NAVBAR PADRONIZADA */
        .top-navbar {
            background-color: var(--navy-dark);
            border-bottom: 2px solid var(--gold-primary);
            padding: 0.75rem 1.25rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.15);
        }

        .user-brand-block { min-width: 0; }

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

        /* BOTÕES DA PALETA */
        .btn-gold {
            background-color: var(--gold-primary);
            color: #ffffff;
            border: 1.5px solid var(--gold-primary);
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-gold:hover {
            background-color: var(--gold-hover);
            color: #ffffff;
            border-color: var(--gold-hover);
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
        }

        .btn-outline-gold {
            border: 1.5px solid var(--gold-primary);
            color: #8C6A28;
            background: transparent;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-outline-gold:hover {
            background-color: var(--gold-primary);
            color: #ffffff;
            border-color: var(--gold-primary);
        }

        .btn-navy {
            background-color: var(--navy-dark);
            color: #ffffff;
            border: 1.5px solid var(--navy-dark);
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-navy:hover {
            background-color: var(--navy-hover);
            color: #ffffff;
            border-color: var(--navy-hover);
        }

        .btn-outline-navy {
            border: 1.5px solid var(--navy-dark);
            color: var(--navy-dark);
            background: transparent;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-outline-navy:hover {
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

        /* CARDS DE ESPIRITUALIDADE E VIVÊNCIA */
        .card-momento {
            background: #ffffff;
            border-radius: 20px;
            border: 1.5px solid var(--border-color);
            overflow: hidden;
            transition: all 0.25s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 16px rgba(10, 25, 47, 0.04);
        }

        .card-momento:hover {
            border-color: var(--gold-primary);
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(10, 25, 47, 0.08);
        }

        .card-momento.tipo-espiritualidade { border-top: 5px solid var(--navy-dark); }
        .card-momento.tipo-vivencia { border-top: 5px solid var(--gold-primary); }

        .card-momento-header {
            padding: 16px 18px 12px 18px;
            background: linear-gradient(180deg, #F8FAFC 0%, #FFFFFF 100%);
            border-bottom: 1px solid #F1F5F9;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 10px;
        }

        .pill-tipo {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 12px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .pill-espiritualidade { background-color: rgba(10, 25, 47, 0.08); color: var(--navy-dark); }
        .pill-vivencia { background-color: rgba(197, 160, 89, 0.16); color: #8C6A28; }

        .datetime-chips {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .chip-info {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 8px;
            background-color: #F1F5F9;
            color: #334155;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .card-momento-body {
            padding: 16px 18px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .info-row-card {
            background-color: #F8FAFC;
            border: 1px solid #EDF2F7;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.88rem;
        }

        .info-row-destaque {
            background: linear-gradient(135deg, #F8FAFC 0%, #F1F5F9 100%);
            border-left: 3.5px solid var(--gold-primary);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 0.88rem;
        }

        .detalhes-box {
            background-color: #FFFDF9;
            border: 1px dashed #E2D5B7;
            border-radius: 12px;
            padding: 10px 14px;
            font-size: 0.84rem;
            color: #334155;
        }

        .card-momento-footer {
            padding: 12px 18px 16px 18px;
            background-color: #ffffff;
            border-top: 1px solid #F1F5F9;
        }

        /* CHAMADA RÁPIDA (MARCAR FALTAS) */
        .membro-check-item {
            padding: 12px 16px;
            border-bottom: 1px solid #F1F5F9;
            transition: background 0.15s;
            cursor: pointer;
        }

        .membro-check-item:hover { background-color: #F8FAFC; }
        .membro-check-item.is-ausente { background-color: #FEF2F2; }
        .membro-check-item .form-check-input:checked {
            background-color: #DC2626;
            border-color: #DC2626;
        }

        .filter-pill-btn {
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 5px 14px;
        }

        /* ABAS E LISTA DE AUSENTES NA PALETA */
        .nav-pills-paleta .nav-link {
            color: var(--navy-dark);
            border: 1.5px solid var(--border-color);
            background-color: #ffffff;
            font-weight: 600;
            border-radius: 50px;
            padding: 7px 18px;
            font-size: 0.86rem;
            transition: all 0.2s ease;
        }

        .nav-pills-paleta .nav-link:hover { border-color: var(--gold-primary); }

        .nav-pills-paleta .nav-link.active {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-color: var(--navy-dark);
        }

        .nav-pills-paleta .nav-link.active i { color: var(--gold-primary); }

        .ausente-item-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-left: 4px solid var(--gold-primary);
            border-radius: 10px;
            padding: 9px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            font-size: 0.9rem;
        }

        /* PLANILHA COMPACTA */
        .tabela-compacta th {
            background-color: var(--navy-dark) !important;
            color: #ffffff !important;
            font-size: 0.76rem;
            font-weight: 600;
            padding: 6px 6px !important;
            white-space: nowrap;
            border-color: #1E293B;
        }

        .tabela-compacta td {
            font-size: 0.8rem;
            padding: 5px 6px !important;
            vertical-align: middle;
        }

        .dot-presente {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #16A34A;
        }

        .badge-falta-compacta {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 6px;
            background-color: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }

        /* RESPONSIVIDADE MOBILE E TABLET */
        .header-actions-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        @media (max-width: 767.98px) {
            .header-actions-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
                padding-top: 12px;
                border-top: 1px solid #F1F5F9;
            }

            .header-actions-group .btn {
                justify-content: center;
                padding-top: 10px;
                padding-bottom: 10px;
            }

            .membro-check-item {
                flex-direction: column;
                align-items: stretch !important;
                gap: 8px !important;
            }

            .membro-check-item input[type="text"] {
                max-width: 100% !important;
            }
        }

        /* ÁREA DE IMPRESSÃO LIMPA */
        #area_impressao_dedicada {
            display: none;
        }

        @media print {
            body * {
                visibility: hidden !important;
            }
            #area_impressao_dedicada,
            #area_impressao_dedicada * {
                visibility: visible !important;
            }
            #area_impressao_dedicada {
                display: block !important;
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                background: #ffffff !important;
                padding: 10mm !important;
                color: #000000 !important;
            }
            #area_impressao_dedicada table {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 12px !important;
            }
            #area_impressao_dedicada th,
            #area_impressao_dedicada td {
                border: 1px solid #334155 !important;
                padding: 6px 10px !important;
                font-size: 0.88rem !important;
            }
            #area_impressao_dedicada th {
                background-color: #0A192F !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <!-- CONTAINER OCULTO EXCLUSIVO PARA IMPRESSÃO -->
    <div id="area_impressao_dedicada"></div>

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

    <!-- CABEÇALHO DA PÁGINA -->
    <div class="bg-white border-bottom py-3 no-print">
        <div class="container d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3" style="max-width: 1140px;">
            <div class="d-flex align-items-center gap-3">
                <?php if ($edicao_id_param > 0): ?>
                    <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="btn-only-arrow" title="Voltar">
                        <i class="bi bi-arrow-left fs-5"></i>
                    </a>
                <?php endif; ?>
                <div>
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-heart-pulse-fill" style="color: var(--gold-primary);"></i>Espiritualidade & Vivência
                    </h5>
                </div>
            </div>

            <div class="header-actions-group">
                <button class="btn btn-outline-navy rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalRelatorioGeral">
                    <i class="bi bi-clipboard2-data-fill" style="color: var(--gold-primary);"></i>
                    <span>Relatório & Ausentes</span>
                </button>

                <button class="btn btn-gold rounded-pill px-4 py-2 btn-sm d-flex align-items-center gap-2" onclick="abrirModalForm()">
                    <i class="bi bi-plus-circle-fill"></i>
                    <span>Novo Momento</span>
                </button>
            </div>
        </div>
    </div>

    <!-- LISTA DE CARDS -->
    <main class="container py-4 no-print" style="max-width: 1140px;">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 rounded-4 border-0 shadow-sm">
                <i class="bi bi-check-circle-fill me-2"></i><?= $mensagem_sucesso ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($lista_espiritualidades)): ?>
            <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm">
                <i class="bi bi-journal-x display-3 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-dark">Nenhum momento cadastrado</h5>
                <p class="text-muted small mb-4">Clique em "Novo Momento" para criar uma Espiritualidade ou Vivência.</p>
                <button class="btn btn-gold rounded-pill px-4 py-2" onclick="abrirModalForm()">
                    <i class="bi bi-plus-lg me-1"></i> Criar Primeiro Momento
                </button>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 g-4">
                <?php foreach ($lista_espiritualidades as $item): ?>
                    <?php
                    $tipo_lc = mb_strtolower($item['tipo'], 'UTF-8');
                    $is_vivencia = ($tipo_lc === 'vivência' || $tipo_lc === 'vivencia');
                    $card_class = $is_vivencia ? 'tipo-vivencia' : 'tipo-espiritualidade';
                    $pill_class = $is_vivencia ? 'pill-vivencia' : 'pill-espiritualidade';
                    $icone_tipo = $is_vivencia ? 'bi-stars' : 'bi-flame';

                    $data_formatada = !empty($item['data_momento']) ? date('d/m/Y', strtotime($item['data_momento'])) : 'Sem data';
                    $hora_formatada = !empty($item['horario_momento']) ? date('H:i', strtotime($item['horario_momento'])) : 'Sem horário';

                    $pres_esp = $matriz_presencas[$item['id']] ?? [];
                    $qtd_presentes = 0;
                    $qtd_ausentes = 0;
                    foreach ($pres_esp as $reg_p) {
                        if ($reg_p['status'] === 'presente') $qtd_presentes++;
                        elseif ($reg_p['status'] === 'ausente') $qtd_ausentes++;
                    }
                    $chamada_realizada = ($qtd_presentes + $qtd_ausentes) > 0;

                    $json_item = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
                    ?>

                    <div class="col">
                        <div class="card-momento <?= $card_class ?>">
                            <div class="card-momento-header">
                                <div>
                                    <span class="pill-tipo <?= $pill_class ?>">
                                        <i class="bi <?= $icone_tipo ?>"></i>
                                        <?= htmlspecialchars($item['tipo']) ?>
                                    </span>

                                    <div class="datetime-chips">
                                        <span class="chip-info">
                                            <i class="bi bi-calendar-event text-primary"></i> <?= $data_formatada ?>
                                        </span>
                                        <span class="chip-info">
                                            <i class="bi bi-clock text-secondary"></i> <?= $hora_formatada ?>
                                        </span>
                                        <?php if ($chamada_realizada): ?>
                                            <span class="chip-info <?= $qtd_ausentes > 0 ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>">
                                                <i class="bi <?= $qtd_ausentes > 0 ? 'bi-person-x-fill' : 'bi-person-check-fill' ?>"></i>
                                                <?= $qtd_ausentes === 0 ? 'Sem faltas' : ($qtd_ausentes . ($qtd_ausentes === 1 ? ' falta' : ' faltas')) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                                    <button type="button" class="btn btn-sm btn-light border rounded-circle" style="width: 34px; height: 34px;" title="Editar" onclick='abrirModalForm(<?= $json_item ?>)'>
                                        <i class="bi bi-pencil text-dark"></i>
                                    </button>

                                    <form method="POST" action="espiritualidade.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" onsubmit="return confirm('Tem certeza que deseja excluir este momento?');" class="d-inline">
                                        <input type="hidden" name="acao" value="excluir_espiritualidade">
                                        <input type="hidden" name="espiritualidade_id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-light border rounded-circle text-danger" style="width: 34px; height: 34px;" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <div class="card-momento-body">
                                <?php if (!empty($item['animacao_responsavel'])): ?>
                                    <div class="info-row-card d-flex justify-content-between align-items-center gap-2">
                                        <span class="text-muted"><i class="bi bi-music-note-beaming text-primary me-2"></i>Animação</span>
                                        <span class="text-dark fw-semibold text-end"><?= htmlspecialchars($item['animacao_responsavel']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($item['oracao_inicial_responsavel'])): ?>
                                    <div class="info-row-card d-flex justify-content-between align-items-center gap-2">
                                        <span class="text-muted"><i class="bi bi-hand-thumbs-up text-success me-2"></i>Oração Inicial</span>
                                        <span class="text-dark fw-semibold text-end"><?= htmlspecialchars($item['oracao_inicial_responsavel']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if (!$is_vivencia): ?>
                                    <?php if (!empty($item['pregacao_tema']) || !empty($item['pregacao_pregador'])): ?>
                                        <div class="info-row-destaque">
                                            <div class="fw-bold text-dark mb-1 d-flex align-items-center gap-1" style="font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.4px;">
                                                <i class="bi bi-mic-fill" style="color: var(--gold-primary);"></i> Pregação
                                            </div>
                                            <?php if (!empty($item['pregacao_tema'])): ?>
                                                <div class="text-dark"><strong>Tema:</strong> <?= htmlspecialchars($item['pregacao_tema']) ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($item['pregacao_pregador'])): ?>
                                                <div class="text-secondary mt-1"><strong>Pregador(a):</strong> <?= htmlspecialchars($item['pregacao_pregador']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (!empty($item['pregacao_tema'])): ?>
                                        <div class="info-row-destaque">
                                            <div class="fw-bold text-dark mb-1" style="font-size: 0.8rem;"><i class="bi bi-1-circle-fill me-1" style="color: var(--gold-primary);"></i> MOMENTO 1</div>
                                            <div class="text-dark"><?= htmlspecialchars($item['pregacao_tema']) ?></div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['pregacao_pregador'])): ?>
                                        <div class="info-row-destaque">
                                            <div class="fw-bold text-dark mb-1" style="font-size: 0.8rem;"><i class="bi bi-2-circle-fill me-1" style="color: var(--gold-primary);"></i> MOMENTO 2</div>
                                            <div class="text-dark"><?= htmlspecialchars($item['pregacao_pregador']) ?></div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($item['detalhes'])): ?>
                                    <div class="detalhes-box mt-auto">
                                        <div class="fw-bold text-muted mb-1" style="font-size: 0.76rem; text-transform: uppercase;">
                                            <i class="bi bi-card-text me-1"></i>Detalhes / Observações
                                        </div>
                                        <p class="m-0" style="white-space: pre-line; line-height: 1.45;"><?= htmlspecialchars($item['detalhes']) ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="card-momento-footer">
                                <button class="btn btn-outline-navy btn-sm w-100 rounded-pill py-2 d-flex align-items-center justify-content-center gap-2" onclick="abrirModalChamada(<?= $item['id'] ?>, '<?= htmlspecialchars($item['tipo']) ?> - <?= $data_formatada ?>')">
                                    <i class="bi bi-person-x"></i>
                                    <span>Registrar Faltas da Lista</span>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- MODAL DE CHAMADA RÁPIDA (MARCA APENAS QUEM FALTOU) -->
    <div class="modal fade no-print" id="modalChamada" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <form method="POST" action="espiritualidade.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="modal-content rounded-4 border-0 shadow-lg" onkeydown="impedirSubmitEnter(event)">
                <input type="hidden" name="acao" value="salvar_presencas">
                <input type="hidden" name="espiritualidade_id" id="chamada_esp_id" value="0">

                <div class="modal-header modal-header-custom p-3">
                    <div>
                        <h5 class="modal-title fw-bold m-0 text-white" id="chamada_titulo">Registro de Faltas</h5>
                        <small class="text-white-50">Marque apenas quem <strong>não compareceu</strong> (os demais serão salvos como presentes)</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="p-3 bg-light border-bottom">
                    <div class="d-flex flex-column flex-sm-row gap-2 align-items-stretch align-items-sm-center mb-2">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="filtro_nome" class="form-control border-start-0" placeholder="Buscar integrante pelo nome..." onkeyup="filtrarChamada()" onkeydown="impedirEnterInput(event)">
                            <button type="button" class="btn btn-outline-secondary bg-white border-start-0" onclick="limparBuscaChamada()" title="Limpar"><i class="bi bi-x-lg"></i></button>
                        </div>
                        <button type="button" class="btn btn-outline-navy btn-sm text-nowrap rounded-3 px-3 py-2" onclick="limparTodasFaltas()">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Zerar Faltas
                        </button>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-1">
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-navy filter-pill-btn" id="btn_filtro_todos" onclick="setFiltroStatus('todos')">Todos (<?= count($lista_membros) ?>)</button>
                            <button type="button" class="btn btn-outline-danger filter-pill-btn" id="btn_filtro_ausentes" onclick="setFiltroStatus('ausentes')">Faltantes (<span id="count_ausentes">0</span>)</button>
                            <button type="button" class="btn btn-outline-success filter-pill-btn" id="btn_filtro_presentes" onclick="setFiltroStatus('presentes')">Presentes (<span id="count_presentes"><?= count($lista_membros) ?></span>)</button>
                        </div>
                    </div>
                </div>

                <div class="modal-body p-0" style="max-height: 55vh; overflow-y: auto;">
                    <?php if (empty($lista_membros)): ?>
                        <div class="text-center py-5 text-muted">Nenhum servo cadastrado na equipe.</div>
                    <?php else: ?>
                        <div class="d-flex flex-column" id="container_membros">
                            <?php foreach ($lista_membros as $m): ?>
                                <div class="membro-check-item d-flex align-items-center justify-content-between gap-3" id="row_membro_<?= $m['id'] ?>" onclick="clicarLinhaMembro(event, <?= $m['id'] ?>)">
                                    <div class="form-check d-flex align-items-center gap-2 m-0 flex-grow-1">
                                        <input class="form-check-input fs-5 mt-0" type="checkbox" name="ausentes[]" value="<?= $m['id'] ?>" id="check_membro_<?= $m['id'] ?>" onchange="atualizarEstadoLinha(<?= $m['id'] ?>)">
                                        <label class="form-check-label fw-semibold text-dark m-0" for="check_membro_<?= $m['id'] ?>" style="cursor: pointer;">
                                            <?= htmlspecialchars($m['nome']) ?>
                                        </label>
                                    </div>

                                    <input type="text" name="justificativa[<?= $m['id'] ?>]" id="just_membro_<?= $m['id'] ?>" class="form-control form-control-sm" placeholder="Motivo da falta (opcional)..." style="max-width: 260px; display: none;" onclick="event.stopPropagation()">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer bg-light p-3 justify-content-between">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-gold rounded-pill px-4">
                        <i class="bi bi-check2-circle me-1"></i> Salvar Chamada
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL CENTRALIZADO: RELATÓRIO & LISTA DE AUSENTES -->
    <div class="modal fade no-print" id="modalRelatorioGeral" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header modal-header-custom">
                    <div>
                        <h5 class="modal-title fw-bold text-white"><i class="bi bi-clipboard2-data-fill me-2" style="color: var(--gold-primary);"></i>Relatório & Lista de Ausentes</h5>
                        <small class="text-white-50">Consulte quem faltou ou visualize a planilha </small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" onclick="imprimirRelatorioAtivo()" class="btn btn-gold btn-sm rounded-pill px-3">
                            <i class="bi bi-printer me-1"></i> Imprimir
                        </button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <div class="modal-body p-3 p-md-4 bg-white">
                    <?php if (empty($lista_membros) || empty($lista_espiritualidades)): ?>
                        <div class="alert alert-info text-center rounded-4 mb-0">É necessário ter momentos cadastrados e integrantes na equipe para gerar o relatório.</div>
                    <?php else: ?>
                        <!-- ABAS NA PALETA (NAVY & GOLD) -->
                        <ul class="nav nav-pills nav-pills-paleta mb-3 gap-2" id="pills-tab-relatorio" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="tab-ausentes-btn" data-bs-toggle="pill" data-bs-target="#tab-ausentes" type="button" role="tab">
                                    <i class="bi bi-person-x-fill me-1"></i> Lista de Quem Não Foi
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="tab-planilha-btn" data-bs-toggle="pill" data-bs-target="#tab-planilha" type="button" role="tab">
                                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> Planilha Geral 
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- ABA 1: LISTA SÓ COM O NOME DE QUEM NÃO FOI -->
                            <div class="tab-pane fade show active" id="tab-ausentes" role="tabpanel">
                                <div class="border rounded-4 p-3 bg-light bg-opacity-50">
                                    <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-end justify-content-between gap-2 mb-3 pb-3 border-bottom">
                                        <div class="flex-grow-1">
                                            <label class="form-label fw-bold small text-muted mb-1">Selecione o Momento:</label>
                                            <select id="select_momento_ausentes" class="form-select rounded-3 fw-semibold" onchange="renderizarListaAusentes()">
                                                <?php foreach ($lista_espiritualidades as $idx => $esp): ?>
                                                    <?php $dt = !empty($esp['data_momento']) ? date('d/m/Y', strtotime($esp['data_momento'])) : 'Sem data'; ?>
                                                    <option value="<?= $esp['id'] ?>" <?= $idx === count($lista_espiritualidades) - 1 ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($esp['tipo']) ?> - <?= $dt ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div>
                                            <button type="button" class="btn btn-navy rounded-pill px-4 py-2 btn-sm w-100 d-flex align-items-center justify-content-center gap-2" onclick="copiarListaAusentes()">
                                                <i class="bi bi-clipboard-check" style="color: var(--gold-primary);"></i>
                                                <span id="texto_btn_copiar">Copiar Lista</span>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="fw-bold m-0" style="color: var(--navy-dark);">
                                            <i class="bi bi-person-dash-fill me-1" style="color: var(--gold-primary);"></i> Ausentes (<span id="total_ausentes_badge">0</span>)
                                        </h6>
                                    </div>

                                    <div id="lista_ausentes_container" class="d-flex flex-column gap-2"></div>
                                </div>
                            </div>

                            <!-- ABA 2: PLANILHA COMPACTA -->
                            <div class="tab-pane fade" id="tab-planilha" role="tabpanel">
                                <div class="d-flex align-items-center justify-content-end gap-3 mb-2 small text-muted">
                                    <span><span class="dot-presente me-1"></span> Presente</span>
                                    <span><span class="badge-falta-compacta me-1">F</span> Falta (passe o mouse para ver motivo)</span>
                                </div>

                                <div id="wrapper_tabela_planilha_geral" class="table-responsive rounded-3 border">
                                    <table class="table table-sm table-bordered table-hover align-middle text-center mb-0 tabela-compacta">
                                        <thead>
                                            <tr>
                                                <th class="text-start ps-2" style="min-width: 180px;">Integrante</th>
                                                <?php foreach ($lista_espiritualidades as $esp): ?>
                                                    <?php
                                                        $dt = !empty($esp['data_momento']) ? date('d/m', strtotime($esp['data_momento'])) : 'S/D';
                                                        $sigla = (mb_strtolower($esp['tipo'], 'UTF-8') === 'vivência' || mb_strtolower($esp['tipo'], 'UTF-8') === 'vivencia') ? 'VIV' : 'ESP';
                                                    ?>
                                                    <th title="<?= htmlspecialchars($esp['tipo']) ?> - <?= $dt ?>">
                                                        <span style="color: var(--gold-primary);"><?= $sigla ?></span> <?= $dt ?>
                                                    </th>
                                                <?php endforeach; ?>
                                                <th style="width: 60px;">Pres.</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lista_membros as $m): ?>
                                                <?php $total_p = 0; ?>
                                                <tr>
                                                    <td class="text-start ps-2 fw-semibold text-dark text-nowrap"><?= htmlspecialchars($m['nome']) ?></td>
                                                    <?php foreach ($lista_espiritualidades as $esp): ?>
                                                        <?php
                                                            $reg = $matriz_presencas[$esp['id']][$m['id']] ?? null;
                                                            $st  = $reg['status'] ?? null;
                                                            $j   = $reg['justificativa'] ?? '';
                                                        ?>
                                                        <td>
                                                            <?php if ($st === 'presente'): ?>
                                                                <?php $total_p++; ?>
                                                                <span class="dot-presente" title="Presente"></span>
                                                            <?php elseif ($st === 'ausente'): ?>
                                                                <span class="badge-falta-compacta" title="<?= !empty($j) ? 'Motivo: ' . htmlspecialchars($j) : 'Faltou' ?>">F</span>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                    <td class="fw-bold bg-light" style="color: var(--navy-dark);"><?= $total_p ?>/<?= count($lista_espiritualidades) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL FORM DE CADASTRO/EDIÇÃO -->
    <div class="modal fade no-print" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
                <div class="modal-header modal-header-custom">
                    <h5 class="modal-title fw-bold text-white" id="modalTitulo">Novo Momento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <form action="espiritualidade.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" method="POST">
                    <input type="hidden" name="acao" value="salvar_espiritualidade">
                    <input type="hidden" name="espiritualidade_id" id="form_id" value="0">

                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tipo de Momento</label>
                            <select name="tipo" id="form_tipo" class="form-select" onchange="atualizarRotulosForm()" required>
                                <option value="Espiritualidade">Espiritualidade</option>
                                <option value="Vivência">Vivência</option>
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Data</label>
                                <input type="date" name="data_momento" id="form_data" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Horário</label>
                                <input type="time" name="horario_momento" id="form_horario" class="form-control" required>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Animação <small class="text-muted fw-normal">(Quem conduz)</small></label>
                            <input type="text" name="animacao_responsavel" id="form_animacao" class="form-control" placeholder="Nome da pessoa ou equipe">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Oração Inicial <small class="text-muted fw-normal">(Quem conduz)</small></label>
                            <input type="text" name="oracao_inicial_responsavel" id="form_oracao" class="form-control" placeholder="Nome do responsável">
                        </div>

                        <hr class="my-3">

                        <div class="mb-3">
                            <label class="form-label fw-semibold" id="label_campo_1">Pregação - Tema</label>
                            <input type="text" name="pregacao_tema" id="form_tema" class="form-control" placeholder="Informe o tema ou atração">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" id="label_campo_2">Pregação - Pregador(a)</label>
                            <input type="text" name="pregacao_pregador" id="form_pregador" class="form-control" placeholder="Informe o responsável">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Detalhes / Observações</label>
                            <textarea name="detalhes" id="form_detalhes" class="form-control" rows="3" placeholder="Informações adicionais, materiais necessários..."></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold rounded-pill px-4">Salvar Momento</button>
                    </div>
                </form>
            </div>
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
        const presencasSalvas = <?= json_encode($matriz_presencas) ?>;
        const todosMembros = <?= json_encode($lista_membros) ?>;
        let filtroStatusAtual = 'todos';

        document.addEventListener("DOMContentLoaded", function () {
            renderizarListaAusentes();
        });

        function impedirEnterInput(event) {
            if (event.key === 'Enter') event.preventDefault();
        }

        function impedirSubmitEnter(event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT') event.preventDefault();
        }

        function normalizarTexto(texto) {
            return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
        }

        function escaparHtml(str) {
            if (!str) return '';
            return str.replace(/&/g, "&amp;")
                      .replace(/</g, "&lt;")
                      .replace(/>/g, "&gt;")
                      .replace(/"/g, "&quot;")
                      .replace(/'/g, "&#039;");
        }

        function clicarLinhaMembro(event, membroId) {
            if (event.target.tagName === 'INPUT' || event.target.tagName === 'LABEL') return;
            const chk = document.getElementById('check_membro_' + membroId);
            if (chk) {
                chk.checked = !chk.checked;
                atualizarEstadoLinha(membroId);
            }
        }

        function atualizarEstadoLinha(membroId) {
            const chk = document.getElementById('check_membro_' + membroId);
            const just = document.getElementById('just_membro_' + membroId);
            const row = document.getElementById('row_membro_' + membroId);

            if (chk && just && row) {
                if (chk.checked) {
                    just.style.display = 'block';
                    row.classList.add('is-ausente');
                } else {
                    just.style.display = 'none';
                    just.value = '';
                    row.classList.remove('is-ausente');
                }
            }
            atualizarContadoresChamada();
            filtrarChamada();
        }

        function atualizarContadoresChamada() {
            let ausentes = 0;
            let presentes = 0;
            document.querySelectorAll('[id^="check_membro_"]').forEach(chk => {
                if (chk.checked) ausentes++;
                else presentes++;
            });
            const elP = document.getElementById('count_presentes');
            const elA = document.getElementById('count_ausentes');
            if (elP) elP.innerText = presentes;
            if (elA) elA.innerText = ausentes;
        }

        function setFiltroStatus(status) {
            filtroStatusAtual = status;
            document.getElementById('btn_filtro_todos').className = status === 'todos' ? 'btn btn-navy filter-pill-btn' : 'btn btn-outline-navy filter-pill-btn';
            document.getElementById('btn_filtro_ausentes').className = status === 'ausentes' ? 'btn btn-danger filter-pill-btn' : 'btn btn-outline-danger filter-pill-btn';
            document.getElementById('btn_filtro_presentes').className = status === 'presentes' ? 'btn btn-success filter-pill-btn' : 'btn btn-outline-success filter-pill-btn';
            filtrarChamada();
        }

        function limparBuscaChamada() {
            document.getElementById('filtro_nome').value = '';
            filtrarChamada();
        }

        function filtrarChamada() {
            const termo = normalizarTexto(document.getElementById('filtro_nome').value);

            document.querySelectorAll('.membro-check-item').forEach(row => {
                const labelElement = row.querySelector('.form-check-label');
                const chk = row.querySelector('input[type="checkbox"]');
                if (!labelElement || !chk) return;

                const nomeMembro = normalizarTexto(labelElement.innerText);
                const bateTexto = (termo === '' || nomeMembro.includes(termo));
                const bateStatus = (filtroStatusAtual === 'todos')
                    || (filtroStatusAtual === 'ausentes' && chk.checked)
                    || (filtroStatusAtual === 'presentes' && !chk.checked);

                if (bateTexto && bateStatus) {
                    row.style.setProperty('display', 'flex', 'important');
                } else {
                    row.style.setProperty('display', 'none', 'important');
                }
            });
        }

        function limparTodasFaltas() {
            document.querySelectorAll('[id^="check_membro_"]').forEach(chk => {
                chk.checked = false;
                const mId = chk.id.replace('check_membro_', '');
                atualizarEstadoLinha(mId);
            });
        }

        function abrirModalChamada(espId, titulo) {
            document.getElementById('chamada_esp_id').value = espId;
            document.getElementById('chamada_titulo').innerText = 'Chamada: ' + titulo;
            document.getElementById('filtro_nome').value = '';
            setFiltroStatus('todos');

            const dadosEsp = presencasSalvas[espId] || {};

            document.querySelectorAll('[id^="check_membro_"]').forEach(chk => {
                const mId = chk.id.replace('check_membro_', '');
                const reg = dadosEsp[mId];
                const just = document.getElementById('just_membro_' + mId);
                const row = document.getElementById('row_membro_' + mId);

                if (reg && reg.status === 'ausente') {
                    chk.checked = true;
                    if (just) {
                        just.value = reg.justificativa || '';
                        just.style.display = 'block';
                    }
                    if (row) row.classList.add('is-ausente');
                } else {
                    chk.checked = false;
                    if (just) {
                        just.value = '';
                        just.style.display = 'none';
                    }
                    if (row) row.classList.remove('is-ausente');
                }
            });

            atualizarContadoresChamada();
            filtrarChamada();
            new bootstrap.Modal(document.getElementById('modalChamada')).show();
        }

        function obterAusentesDoMomento(espId) {
            const regs = presencasSalvas[espId] || {};
            const chamadaFeita = Object.keys(regs).length > 0;
            let ausentes = [];

            if (chamadaFeita) {
                todosMembros.forEach(m => {
                    const r = regs[m.id];
                    if (r && r.status === 'ausente') {
                        ausentes.push({
                            nome: m.nome,
                            justificativa: r.justificativa || ''
                        });
                    }
                });
            }
            return { chamadaFeita, ausentes };
        }

        function renderizarListaAusentes() {
            const select = document.getElementById('select_momento_ausentes');
            const container = document.getElementById('lista_ausentes_container');
            const badge = document.getElementById('total_ausentes_badge');
            if (!select || !container) return;

            const { chamadaFeita, ausentes } = obterAusentesDoMomento(select.value);

            if (!chamadaFeita) {
                badge.innerText = '0';
                container.innerHTML = `<div class="text-center py-4 text-muted bg-white border rounded-3">A chamada deste momento ainda não foi registrada.</div>`;
                return;
            }

            badge.innerText = ausentes.length;

            if (ausentes.length === 0) {
                container.innerHTML = `<div class="text-center py-4 text-success bg-success-subtle rounded-3 fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Nenhuma falta registrada neste momento!</div>`;
                return;
            }

            container.innerHTML = ausentes.map((a, idx) => `
                <div class="ausente-item-card bg-white">
                    <div class="fw-semibold text-dark">
                        <span class="fw-bold me-2" style="color: var(--gold-primary);">${idx + 1}.</span>${escaparHtml(a.nome)}
                    </div>
                    ${a.justificativa ? `<span class="badge bg-light text-secondary border">${escaparHtml(a.justificativa)}</span>` : ''}
                </div>
            `).join('');
        }

        function copiarListaAusentes() {
            const select = document.getElementById('select_momento_ausentes');
            if (!select) return;
            const tituloMomento = select.options[select.selectedIndex].text.trim();
            const { ausentes } = obterAusentesDoMomento(select.value);

            const linhas = ausentes.map(a => `• ${a.nome}${a.justificativa ? ' (' + a.justificativa + ')' : ''}`);
            const textoFinal = `*Ausentes - ${tituloMomento}*\n\n` + (linhas.length ? linhas.join('\n') : 'Nenhuma falta registrada.');

            navigator.clipboard.writeText(textoFinal).then(() => {
                const btnSpan = document.getElementById('texto_btn_copiar');
                btnSpan.innerText = 'Copiado!';
                setTimeout(() => { btnSpan.innerText = 'Copiar Lista'; }, 2000);
            });
        }

        // IMPRESSÃO DEDICADA CONFORME A ABA ATIVA
        function imprimirRelatorioAtivo() {
            const area = document.getElementById('area_impressao_dedicada');
            const abaAusentesAtiva = document.getElementById('tab-ausentes-btn')?.classList.contains('active');

            if (abaAusentesAtiva) {
                const select = document.getElementById('select_momento_ausentes');
                if (!select) return;

                const tituloMomento = select.options[select.selectedIndex].text.trim();
                const { chamadaFeita, ausentes } = obterAusentesDoMomento(select.value);

                let linhasTabela = '';
                if (!chamadaFeita) {
                    linhasTabela = `<tr><td colspan="3" style="text-align:center;">A chamada deste momento ainda não foi registrada.</td></tr>`;
                } else if (ausentes.length === 0) {
                    linhasTabela = `<tr><td colspan="3" style="text-align:center;">Nenhuma falta registrada neste momento.</td></tr>`;
                } else {
                    linhasTabela = ausentes.map((a, i) => `
                        <tr>
                            <td style="width: 45px; text-align: center;">${i + 1}</td>
                            <td><strong>${escaparHtml(a.nome)}</strong></td>
                            <td>${a.justificativa ? escaparHtml(a.justificativa) : '-'}</td>
                        </tr>
                    `).join('');
                }

                area.innerHTML = `
                    <div style="text-align: center; margin-bottom: 14px;">
                        <h3 style="margin: 0; font-weight: 700; color: #0A192F;">${escaparHtml(tituloMomento)}</h3>
                        <h5 style="margin: 4px 0 0 0; font-weight: 600; color: #C5A059; text-transform: uppercase; letter-spacing: 1px;">Faltas</h5>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 45px; text-align: center;">#</th>
                                <th style="text-align: left;">Nome do Integrante</th>
                                <th style="text-align: left;">Justificativa</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${linhasTabela}
                        </tbody>
                    </table>
                `;
            } else {
                const wrapperTabela = document.getElementById('wrapper_tabela_planilha_geral');
                area.innerHTML = `
                    <div style="text-align: center; margin-bottom: 12px;">
                        <h3 style="margin: 0; font-weight: 700; color: #0A192F;">ÉFATA - Planilha Geral de Frequência</h3>
                        <small style="color: #64748B;">Espiritualidades e Vivências</small>
                    </div>
                    ${wrapperTabela ? wrapperTabela.innerHTML : ''}
                `;
            }

            window.print();
        }

        function atualizarRotulosForm() {
            const tipo = document.getElementById('form_tipo').value;
            const label1 = document.getElementById('label_campo_1');
            const label2 = document.getElementById('label_campo_2');

            if (tipo === 'Vivência' || tipo === 'vivencia') {
                label1.innerHTML = 'Momento 1 <small class="text-muted fw-normal">(Atividade e Responsável)</small>';
                label2.innerHTML = 'Momento 2 <small class="text-muted fw-normal">(Atividade e Responsável)</small>';
            } else {
                label1.innerHTML = 'Pregação - Tema';
                label2.innerHTML = 'Pregação - Pregador(a)';
            }
        }

        function abrirModalForm(dados = null) {
            const modalEl = new bootstrap.Modal(document.getElementById('modalForm'));

            if (dados) {
                document.getElementById('modalTitulo').innerText = 'Editar Momento';
                document.getElementById('form_id').value = dados.id;
                document.getElementById('form_tipo').value = dados.tipo || 'Espiritualidade';
                document.getElementById('form_data').value = dados.data_momento || '';
                document.getElementById('form_horario').value = dados.horario_momento || '';
                document.getElementById('form_animacao').value = dados.animacao_responsavel || '';
                document.getElementById('form_oracao').value = dados.oracao_inicial_responsavel || '';
                document.getElementById('form_tema').value = dados.pregacao_tema || '';
                document.getElementById('form_pregador').value = dados.pregacao_pregador || '';
                document.getElementById('form_detalhes').value = dados.detalhes || '';
            } else {
                document.getElementById('modalTitulo').innerText = 'Novo Momento';
                document.getElementById('form_id').value = '0';
                document.getElementById('form_tipo').value = 'Espiritualidade';
                document.getElementById('form_data').value = '';
                document.getElementById('form_horario').value = '';
                document.getElementById('form_animacao').value = '';
                document.getElementById('form_oracao').value = '';
                document.getElementById('form_tema').value = '';
                document.getElementById('form_pregador').value = '';
                document.getElementById('form_detalhes').value = '';
            }

            atualizarRotulosForm();
            modalEl.show();
        }
    </script>
</body>
</html>