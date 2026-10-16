<?php
// espiritualidade.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil       = $_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL';

$edicao_id_param = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id_param = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

$mensagem_sucesso = '';
$mensagem_erro = '';

// 2. PROCESSAR SALVAMENTO DA CHAMADA / PRESENÇAS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_presencas') {
    $esp_id = intval($_POST['espiritualidade_id'] ?? 0);
    $presentes_ids = $_POST['presentes'] ?? [];
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
                $is_presente = in_array($m_id, $presentes_ids) ? 'presente' : 'ausente';
                $just = ($is_presente === 'ausente') ? trim($justificativas[$m_id] ?? '') : '';

                $stmt_p = $conn->prepare("
                    INSERT INTO espiritualidade_presencas (espiritualidade_id, membro_id, status, justificativa)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE status = VALUES(status), justificativa = VALUES(justificativa)
                ");
                $stmt_p->bind_param("iiss", $esp_id, $m_id, $is_presente, $just);
                $stmt_p->execute();
            }
        }
        $mensagem_sucesso = "Presenças atualizadas com sucesso!";
    }
}

// 3. EXCLUSÃO DE ESPIRITUALIDADE
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

// 4. CADASTRAR / EDITAR ESPIRITUALIDADE
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
        $mensagem_sucesso = "Registro atualizado com sucesso!";
    } else {
        $stmt_i = $conn->prepare("INSERT INTO espiritualidades (edicao_id, tipo, data_momento, horario_momento, animacao_responsavel, oracao_inicial_responsavel, pregacao_tema, pregacao_pregador, detalhes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_i->bind_param("issssssss", $edicao_id_param, $tipo, $data_momento, $horario_momento, $animacao_responsavel, $oracao_inicial_responsavel, $pregacao_tema, $pregacao_pregador, $detalhes);
        $stmt_i->execute();
        $mensagem_sucesso = "Registro criado com sucesso!";
    }
}

// 5. BUSCAR ESPIRITUALIDADES
$sql = "SELECT * FROM espiritualidades WHERE 1=1";
if ($edicao_id_param > 0) {
    $sql .= " AND (edicao_id = {$edicao_id_param} OR edicao_id IS NULL OR edicao_id = 0)";
}
$sql .= " ORDER BY data_momento ASC, horario_momento ASC, id DESC";
$res = $conn->query($sql);
$lista_espiritualidades = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// 6. BUSCAR INTEGRANTES DA EQUIPE GERAL
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

// 7. MATRIZ DE PRESENÇAS
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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        
        .navbar-custom { background-color: var(--efata-dark); padding: 10px 20px; border-bottom: 2px solid var(--efata-gold); }
        .user-profile-container { display: flex; align-items: center; gap: 12px; }
        .logo-box { width: 42px; height: 42px; background: #fff; border-radius: 8px; border: 1px solid var(--efata-gold); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .user-info-text { display: flex; flex-direction: column; }
        .user-name { color: #ffffff; font-weight: 700; font-size: 0.95rem; line-height: 1.2; }
        .user-role { color: #94A3B8; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-nav-outline-red { color: #EF4444; border: 1px solid #EF4444; background: transparent; padding: 6px 16px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-nav-outline-red:hover { background-color: #EF4444; color: #ffffff; }

        .card-espiritualidade { background: #fff; border-radius: 14px; border: 1px solid #E2E8F0; padding: 14px; transition: all 0.2s; height: 100%; display: flex; flex-direction: column; justify-content: space-between; }
        .card-espiritualidade:hover { border-color: var(--efata-gold); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .badge-tipo { font-size: 0.75rem; font-weight: 600; padding: 4px 10px; border-radius: 20px; }
        .badge-espiritualidade { background-color: #EEF2FF; color: #4F46E5; }
        .badge-vivencia { background-color: #FEF3C7; color: #D97706; }
        .item-ordem { padding: 6px 10px; background-color: #F8FAFC; border-radius: 6px; border-left: 3px solid #CBD5E1; font-size: 0.825rem; }

        .membro-check-item {
            padding: 10px 14px;
            border-bottom: 1px solid #F1F5F9;
            transition: background 0.15s;
        }
        .membro-check-item:hover { background-color: #F8FAFC; }
        .form-check-input:checked { background-color: #10B981; border-color: #10B981; }

        /* REGRAS ESPECÍFICAS PARA IMPRESSÃO / PDF PAISAGEM */
        @page {
            size: A4 landscape;
            margin: 10mm;
        }

        @media print {
            .no-print, .navbar-custom, .modal-header, .modal-footer, .btn, main, .bg-white.border-bottom { 
                display: none !important; 
            }
            body { 
                background: #fff !important; 
                padding: 0 !important; 
                margin: 0 !important; 
            }
            .modal {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                display: block !important;
            }
            .modal-dialog {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .modal-content {
                border: none !important;
                box-shadow: none !important;
                width: 100% !important;
            }
            .modal-body {
                padding: 0 !important;
                overflow: visible !important;
            }
            .table-responsive {
                overflow: visible !important;
            }
            table {
                width: 100% !important;
                table-layout: auto !important;
                font-size: 0.75rem !important;
            }
            th, td {
                padding: 4px 6px !important;
                word-wrap: break-word !important;
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
                    <img src="./uploads/logos/logoefata.png" alt="Logo" onerror="this.onerror=null; this.src='https://via.placeholder.com/40';">
                </div>
                <div class="user-info-text">
                    <div class="user-name"><?= htmlspecialchars($nome_usuario) ?></div>
                    <div class="user-role"><?= htmlspecialchars($perfil) ?></div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 ms-auto">
                <button class="btn btn-outline-light btn-sm rounded-3 fw-semibold px-3" data-bs-toggle="modal" data-bs-target="#modalRelatorioGeral">
                    <i class="bi bi-file-earmark-spreadsheet me-1 text-warning"></i> Relatório de Presenças
                </button>
                <a href="logout.php" class="btn-nav-outline-red">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <!-- CABEÇALHO -->
    <div class="bg-white border-bottom py-3 no-print">
        <div class="container d-flex align-items-center justify-content-between" style="max-width: 1000px;">
            <div class="d-flex align-items-center gap-3">
                <?php if ($edicao_id_param > 0): ?>
                    <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="btn btn-sm btn-outline-secondary rounded-circle">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                <?php endif; ?>
                <div>
                    <h5 class="fw-bold m-0 text-dark"><i class="bi bi-heart-pulse-fill text-warning me-2"></i>Espiritualidade & Vivência</h5>
                    <small class="text-muted">Cronograma dos momentos</small>
                </div>
            </div>

            <button class="btn btn-warning rounded-pill px-3 fw-bold text-dark d-flex align-items-center gap-2" onclick="abrirModalForm()">
                <i class="bi bi-plus-circle-fill"></i> Novo
            </button>
        </div>
    </div>

    <!-- CARDS -->
    <main class="container py-4" style="max-width: 1000px;">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4 rounded-3 shadow-sm no-print"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (empty($lista_espiritualidades)): ?>
            <div class="text-center py-5 bg-white rounded-4 border p-4 shadow-sm no-print">
                <i class="bi bi-journal-x display-3 text-muted d-block mb-3"></i>
                <h5 class="fw-bold text-dark">Nenhum momento cadastrado</h5>
                <p class="text-muted small">Clique no botão "Novo" acima para criar uma Espiritualidade ou Vivência.</p>
            </div>
        <?php else: ?>
            <div class="row row-cols-1 row-cols-md-2 g-3">
                <?php foreach ($lista_espiritualidades as $item): ?>
                    <?php
                    $tipo_lc = strtolower($item['tipo']);
                    $is_vivencia = ($tipo_lc === 'vivência' OR $tipo_lc === 'vivencia');
                    $badge_class = $is_vivencia ? 'badge-vivencia' : 'badge-espiritualidade';
                    
                    $data_formatada = !empty($item['data_momento']) ? date('d/m/Y', strtotime($item['data_momento'])) : 'Sem data';
                    $hora_formatada = !empty($item['horario_momento']) ? date('H:i', strtotime($item['horario_momento'])) : 'Sem horário';

                    $json_item = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
                    ?>

                    <div class="col">
                        <div class="card-espiritualidade shadow-sm">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge-tipo <?= $badge_class ?>"><?= htmlspecialchars($item['tipo']) ?></span>
                                        
                                        <span class="badge bg-primary text-white fw-bold px-2 py-1 rounded-3" style="font-size: 0.825rem;">
                                            <i class="bi bi-calendar-event me-1"></i><?= $data_formatada ?>
                                        </span>
                                        <span class="badge bg-light text-dark border fw-semibold px-2 py-1 rounded-3" style="font-size: 0.825rem;">
                                            <i class="bi bi-clock me-1 text-primary"></i><?= $hora_formatada ?>
                                        </span>
                                    </div>

                                    <div class="d-flex align-items-center gap-1 no-print">
                                        <button type="button" class="btn btn-sm btn-light rounded-circle p-1 px-2" title="Editar" onclick='abrirModalForm(<?= $json_item ?>)'>
                                            <i class="bi bi-pencil text-dark"></i>
                                        </button>

                                        <form method="POST" action="espiritualidade.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" onsubmit="return confirm('Tem certeza que deseja excluir este momento?');" class="d-inline">
                                            <input type="hidden" name="acao" value="excluir_espiritualidade">
                                            <input type="hidden" name="espiritualidade_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-light rounded-circle text-danger p-1 px-2" title="Excluir">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>

                                <div class="d-flex flex-column gap-2">
                                    <?php if (!empty($item['animacao_responsavel'])): ?>
                                        <div class="item-ordem d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-music-note-beaming text-primary me-1"></i><strong>Animação:</strong></span>
                                            <span class="text-dark fw-medium"><?= htmlspecialchars($item['animacao_responsavel']) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($item['oracao_inicial_responsavel'])): ?>
                                        <div class="item-ordem d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-hand-thumbs-up text-success me-1"></i><strong>Oração Inicial:</strong></span>
                                            <span class="text-dark fw-medium"><?= htmlspecialchars($item['oracao_inicial_responsavel']) ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!$is_vivencia): ?>
                                        <?php if (!empty($item['pregacao_tema']) OR !empty($item['pregacao_pregador'])): ?>
                                            <div class="item-ordem">
                                                <div class="fw-bold text-secondary mb-1"><i class="bi bi-mic text-warning me-1"></i>Pregação:</div>
                                                <div class="ps-2">
                                                    <?php if (!empty($item['pregacao_tema'])): ?>
                                                        <div><strong>Tema:</strong> <?= htmlspecialchars($item['pregacao_tema']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['pregacao_pregador'])): ?>
                                                        <div><strong>Pregador:</strong> <?= htmlspecialchars($item['pregacao_pregador']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if (!empty($item['pregacao_tema'])): ?>
                                            <div class="item-ordem">
                                                <div class="fw-bold text-secondary mb-1"><i class="bi bi-1-circle text-info me-1"></i>Momento 1:</div>
                                                <div class="ps-2"><?= htmlspecialchars($item['pregacao_tema']) ?></div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($item['pregacao_pregador'])): ?>
                                            <div class="item-ordem">
                                                <div class="fw-bold text-secondary mb-1"><i class="bi bi-2-circle text-info me-1"></i>Momento 2:</div>
                                                <div class="ps-2"><?= htmlspecialchars($item['pregacao_pregador']) ?></div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (!empty($item['detalhes'])): ?>
                                <div class="mt-2 pt-2 border-top">
                                    <small class="text-muted d-block mb-1" style="font-size: 0.75rem;"><strong><i class="bi bi-info-circle me-1"></i>Detalhes:</strong></small>
                                    <p class="small text-dark m-0" style="white-space: pre-line; font-size: 0.8rem;"><?= htmlspecialchars($item['detalhes']) ?></p>
                                </div>
                            <?php endif; ?>

                            <div class="mt-3 pt-2 border-top no-print">
                                <button class="btn btn-outline-primary btn-sm w-100 rounded-3 fw-bold" onclick="abrirModalChamada(<?= $item['id'] ?>, '<?= htmlspecialchars($item['tipo']) ?> - <?=$data_formatada ?>')">
                                    <i class="bi bi-check2-square me-1"></i> Conferir Presenças da Lista / Forms
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- MODAL DE CHAMADA RÁPIDA -->
    <div class="modal fade" id="modalChamada" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form method="POST" action="espiritualidade.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="modal-content rounded-4 border-0" onkeydown="impedirSubmitEnter(event)">
                <input type="hidden" name="acao" value="salvar_presencas">
                <input type="hidden" name="espiritualidade_id" id="chamada_esp_id" value="0">
                
                <div class="modal-header bg-dark text-white p-3">
                    <div>
                        <h5 class="modal-title fw-bold m-0" id="chamada_titulo">Conferência de Presença</h5>
                        <small class="text-white-50">Marque apenas os nomes que estão no formulário/lista impressa</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>

                <div class="p-3 bg-light border-bottom d-flex gap-2 align-items-center">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="filtro_nome" class="form-control border-start-0" placeholder="Digite o nome para buscar na lista..." onkeyup="filtrarNomes()" onkeydown="impedirEnterInput(event)">
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap rounded-3" onclick="alternarMarcarTodos()">Marcar Todos</button>
                </div>

                <div class="modal-body p-0" style="max-height: 420px; overflow-y: auto;">
                    <?php if (empty($lista_membros)): ?>
                        <div class="text-center py-5 text-muted">Nenhum integrante cadastrado na equipe.</div>
                    <?php else: ?>
                        <div class="d-flex flex-column" id="container_membros">
                            <?php foreach ($lista_membros as$m): ?>
                                <div class="membro-check-item d-flex align-items-center justify-content-between gap-3" id="row_membro_<?= $m['id'] ?>">
                                    
                                    <div class="form-check d-flex align-items-center gap-2 m-0 flex-grow-1">
                                        <input class="form-check-input fs-5" type="checkbox" name="presentes[]" value="<?= $m['id'] ?>" id="check_membro_<?= $m['id'] ?>" onchange="toggleCampoJustificativa(<?= $m['id'] ?>)">
                                        <label class="form-check-label fw-bold text-dark fs-6 pointer-event" for="check_membro_<?= $m['id'] ?>" style="cursor: pointer;">
                                            <?= htmlspecialchars($m['nome']) ?>
                                        </label>
                                    </div>

                                    <input type="text" name="justificativa[<?= $m['id'] ?>]" id="just_membro_<?= $m['id'] ?>" class="form-control form-control-sm" placeholder="Motivo se faltou (opcional)..." style="max-width: 250px;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success rounded-pill px-4 fw-bold"><i class="bi bi-floppy me-1"></i> Salvar Presenças</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL RELATÓRIO GERAL DE FREQUÊNCIA -->
    <div class="modal fade" id="modalRelatorioGeral" tabindex="-1">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white no-print">
                    <div>
                        <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-spreadsheet me-2 text-warning"></i>Relatório Geral de Frequência</h5>
                        <small class="text-muted">Presenças acumuladas na equipe</small>
                    </div>
                    <div class="d-flex gap-2">
                        <button onclick="window.print()" class="btn btn-warning btn-sm rounded-pill fw-bold"><i class="bi bi-printer me-1"></i> Imprimir Relatório</button>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <div class="modal-body p-4 bg-white">
                    <div class="text-center mb-4 d-none d-print-block">
                        <h3 class="fw-bold m-0">ÉFATA - Relatório Geral de Presenças</h3>
                        <p class="text-muted m-0">Frequência da Equipe nas Espiritualidades e Vivências</p>
                    </div>

                    <?php if (empty($lista_membros) || empty($lista_espiritualidades)): ?>
                        <div class="alert alert-info text-center">É necessário ter momentos cadastrados e integrantes na equipe para gerar o relatório.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center" style="font-size: 0.875rem;">
                                <thead class="table-dark">
                                    <tr>
                                        <th class="text-start" style="min-width: 200px;">Nome do Integrante</th>
                                        <?php foreach ($lista_espiritualidades as$esp): ?>
                                            <?php $dt = !empty($esp['data_momento']) ? date('d/m', strtotime($esp['data_momento'])) : 'S/D'; ?>
                                            <th>
                                                <div><?= htmlspecialchars($esp['tipo']) ?></div>
                                                <small class="fw-normal text-warning"><?= $dt ?></small>
                                            </th>
                                        <?php endforeach; ?>
                                        <th style="width: 90px;">Total Presenças</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_membros as$m): ?>
                                        <?php $total_p = 0; ?>
                                        <tr>
                                            <td class="text-start fw-bold text-dark"><?= htmlspecialchars($m['nome']) ?></td>
                                            <?php foreach ($lista_espiritualidades as$esp): ?>
                                                <?php 
                                                    $reg =$matriz_presencas[$esp['id']][$m['id']] ?? null;
                                                    $st =$reg['status'] ?? null;
                                                    $j =$reg['justificativa'] ?? '';
                                                ?>
                                                <td>
                                                    <?php if ($st === 'presente'): ?>
                                                        <?php $total_p++; ?>
                                                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">P</span>
                                                    <?php elseif ($st === 'ausente'): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger px-2 py-1" title="<?= htmlspecialchars($j) ?>">F</span>
                                                        <?php if (!empty($j)): ?>
                                                            <div class="small text-muted fst-italic mt-1" style="font-size: 0.7rem;"><?= htmlspecialchars($j) ?></div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="fw-bold bg-light text-primary"><?= $total_p ?> / <?= count($lista_espiritualidades) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL FORM DE CADASTRO/EDIÇÃO -->
    <div class="modal fade no-print" id="modalForm" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="modalTitulo">Novo Momento</h5>
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
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Salvar Momento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const presencasSalvas = <?= json_encode($matriz_presencas) ?>;

        function impedirEnterInput(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
            }
        }

        function impedirSubmitEnter(event) {
            if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
                event.preventDefault();
            }
        }

        function normalizarTexto(texto) {
            return texto.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
        }

        function filtrarNomes() {
            const termo = normalizarTexto(document.getElementById('filtro_nome').value);
            
            document.querySelectorAll('.membro-check-item').forEach(row => {
                const labelElement = row.querySelector('.form-check-label');
                if (labelElement) {
                    const nomeMembro = normalizarTexto(labelElement.innerText);
                    if (termo === '' || nomeMembro.includes(termo)) {
                        row.style.setProperty('display', 'flex', 'important');
                    } else {
                        row.style.setProperty('display', 'none', 'important');
                    }
                }
            });
        }

        function toggleCampoJustificativa(membroId) {
            const chk = document.getElementById('check_membro_' + membroId);
            const just = document.getElementById('just_membro_' + membroId);
            if (chk && just) {
                if (chk.checked) {
                    just.style.display = 'none';
                    just.value = '';
                } else {
                    just.style.display = 'block';
                }
            }
        }

        let todosMarcados = false;
        function alternarMarcarTodos() {
            todosMarcados = !todosMarcados;
            document.querySelectorAll('[id^="check_membro_"]').forEach(chk => {
                const row = chk.closest('.membro-check-item');
                if (row && row.style.display !== 'none') {
                    chk.checked = todosMarcados;
                    const mId = chk.id.replace('check_membro_', '');
                    toggleCampoJustificativa(mId);
                }
            });
        }

        function abrirModalChamada(espId, titulo) {
            document.getElementById('chamada_esp_id').value = espId;
            document.getElementById('chamada_titulo').innerText = 'Presença: ' + titulo;
            document.getElementById('filtro_nome').value = '';
            
            const dadosEsp = presencasSalvas[espId] || {};

            document.querySelectorAll('[id^="check_membro_"]').forEach(chk => {
                const mId = chk.id.replace('check_membro_', '');
                const reg = dadosEsp[mId];
                const just = document.getElementById('just_membro_' + mId);

                if (reg) {
                    chk.checked = (reg.status === 'presente');
                    if (just) just.value = reg.justificativa || '';
                } else {
                    chk.checked = false;
                    if (just) just.value = '';
                }
                toggleCampoJustificativa(mId);
            });

            filtrarNomes();
            new bootstrap.Modal(document.getElementById('modalChamada')).show();
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