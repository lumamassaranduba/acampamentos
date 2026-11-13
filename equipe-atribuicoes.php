<?php
// equipe-atribuicoes.php
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

// 1. Verificar se a edição está inativa
$stmt_ed = $conn->prepare("SELECT * FROM edicoes WHERE id = ?");
$stmt_ed->bind_param("i", $edicao_id);
$stmt_ed->execute();
$edicao = $stmt_ed->get_result()->fetch_assoc();

$edicao_inativa = false;
if ($edicao) {
    $status_edicao = strtoupper($edicao['status'] ?? '');
    if ($status_edicao === 'INATIVA' || $status_edicao === 'FINALIZADA' || (isset($edicao['ativa']) && $edicao['ativa'] == 0)) {
        $edicao_inativa = true;
    }
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// PROCESSAMENTO: Salvar / Editar Atribuições da Equipe
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_atribuicoes') {
    if ($edicao_inativa) {
        $mensagem_erro = "Esta edição está inativa. Não é possível alterar atribuições.";
    } else {
        $equipe_id = intval($_POST['equipe_id'] ?? 0);
        $atribuicoes = trim($_POST['atribuicoes'] ?? '');

        $stmt = $conn->prepare("UPDATE equipes_trabalho SET atribuicoes = ? WHERE id = ? AND edicao_id = ?");
        $stmt->bind_param("sii", $atribuicoes, $equipe_id, $edicao_id);
        $stmt->execute();
        $mensagem_sucesso = "Funções e atribuições atualizadas com sucesso!";
    }
}

// PROCESSAMENTO: Resgatar Atribuições de Outra Edição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'resgatar_atribuicoes') {
    if ($edicao_inativa) {
        $mensagem_erro = "Esta edição está inativa. Não é possível importar atribuições.";
    } else {
        $edicao_origem_id = intval($_POST['edicao_origem_id'] ?? 0);

        if ($edicao_origem_id > 0) {
            // Busca equipes da edição escolhida
            $stmt_orig = $conn->prepare("SELECT nome, atribuicoes FROM equipes_trabalho WHERE edicao_id = ?");
            $stmt_orig->bind_param("i", $edicao_origem_id);
            $stmt_orig->execute();
            $equipes_origem = $stmt_orig->get_result()->fetch_all(MYSQLI_ASSOC);

            if (empty($equipes_origem)) {
                $mensagem_erro = "A edição selecionada não possui equipes cadastradas para resgatar.";
            } else {
                $copiadas = 0;
                foreach ($equipes_origem as $eq_orig) {
                    $nome_eq = $eq_orig['nome'];
                    $atrib_eq = $eq_orig['atribuicoes'];

                    // Verifica se essa equipe já existe na edição atual
                    $stmt_check = $conn->prepare("SELECT id FROM equipes_trabalho WHERE edicao_id = ? AND LOWER(TRIM(nome)) = LOWER(TRIM(?)) LIMIT 1");
                    $stmt_check->bind_param("is", $edicao_id, $nome_eq);
                    $stmt_check->execute();
                    $existe = $stmt_check->get_result()->fetch_assoc();

                    if ($existe) {
                        // Atualiza as atribuições da equipe existente
                        $stmt_upd = $conn->prepare("UPDATE equipes_trabalho SET atribuicoes = ? WHERE id = ?");
                        $stmt_upd->bind_param("si", $atrib_eq, $existe['id']);
                        $stmt_upd->execute();
                    } else {
                        // Cria a equipe na nova edição já com as atribuições
                        $stmt_ins = $conn->prepare("INSERT INTO equipes_trabalho (edicao_id, nome, atribuicoes) VALUES (?, ?, ?)");
                        $stmt_ins->bind_param("iss", $edicao_id, $nome_eq, $atrib_eq);
                        $stmt_ins->execute();
                    }
                    $copiadas++;
                }
                $mensagem_sucesso = "Atribuições de {$copiadas} equipe(s) resgatadas com sucesso!";
            }
        }
    }
}

// BUSCA DE EDIÇÕES ANTERIORES DA MESMA MODALIDADE PARA O MODAL DE RESGATE
$mod_id_busca = $modalidade_id ?: ($edicao['modalidade_id'] ?? 0);
$stmt_ed_ant = $conn->prepare("
    SELECT DISTINCT e.id, e.ano, e.nome 
    FROM edicoes e
    INNER JOIN equipes_trabalho et ON et.edicao_id = e.id
    WHERE e.modalidade_id = ? AND e.id != ?
    ORDER BY e.ano DESC, e.id DESC
");
$stmt_ed_ant->bind_param("ii", $mod_id_busca, $edicao_id);
$stmt_ed_ant->execute();
$edicoes_anteriores = $stmt_ed_ant->get_result()->fetch_all(MYSQLI_ASSOC);

// BUSCA DE EQUIPES DA EDIÇÃO ATUAL
$stmt = $conn->prepare("SELECT * FROM equipes_trabalho WHERE edicao_id = ? ORDER BY nome ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$equipes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atribuições e Funções das Equipes - Éfata</title>
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

        .card-custom { 
            background: #ffffff; 
            border-radius: 20px; 
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

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

        /* Botão Discreto para Resgatar Edição */
        .btn-resgatar-discreto {
            background-color: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            font-weight: 500;
            font-size: 0.82rem;
            white-space: nowrap;
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

        .card-equipe-atribuicao {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            transition: all 0.25s ease;
            cursor: pointer;
        }

        .card-equipe-atribuicao:hover {
            transform: translateY(-4px);
            border-color: var(--gold-primary);
            box-shadow: 0 10px 25px rgba(10, 25, 47, 0.08);
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        /* ESTILOS DE IMPRESSÃO / PDF (UMA EQUIPE POR PÁGINA) */
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

            .print-card-equipe {
                border: 1px solid #000000 !important;
                padding: 20px !important;
                margin-bottom: 0 !important;
                min-height: 90vh;
                box-sizing: border-box;
                page-break-after: always !important;
                break-after: page !important;
            }

            .print-card-equipe:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }

            .print-card-header {
                font-size: 16pt !important;
                font-weight: bold !important;
                text-transform: uppercase !important;
                text-align: center !important;
                border-bottom: 2px solid #000000 !important;
                padding-bottom: 8px !important;
                margin-bottom: 20px !important;
            }

            .print-card-content {
                font-size: 11pt !important;
                line-height: 1.6 !important;
                white-space: pre-line !important;
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

        <!-- 1º CARD: TOPO DE NAVEGAÇÃO E BOTÕES -->
        <div class="card-custom p-3 p-md-4 mt-2 mt-md-3 mb-4 no-print d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" class="btn text-dark border-0 p-0 fs-4 d-flex align-items-center justify-content-center me-1" title="Voltar ao Hub da Equipe">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h4 class="fw-bold m-0 text-dark fs-5 fs-md-4">Atribuições e Funções das Equipes</h4>
                        <?php if ($edicao_inativa): ?>
                            <span class="badge bg-secondary"><i class="bi bi-lock-fill me-1"></i>Edição Inativa</span>
                        <?php endif; ?>
                    </div>
                    <p class="text-muted small m-0">Consulte ou edite as tarefas de cada equipe da edição.</p>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if (!$edicao_inativa): ?>
                    <button type="button" class="btn btn-resgatar-discreto rounded-3 px-3 py-2 btn-sm d-flex align-items-center justify-content-center gap-1" data-bs-toggle="modal" data-bs-target="#modalResgatarEdicao" title="Importar atribuições de outra edição">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Resgatar edição</span>
                    </button>
                <?php endif; ?>

                <!-- BOTÃO PARA DISPARAR DIRETO A IMPRESSÃO / SALVAR EM PDF -->
                <button onclick="window.print()" class="btn btn-navy rounded-3 px-3 py-2 btn-sm d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-file-earmark-pdf-fill text-danger fs-6"></i>
                    <span> IMPRIMIR </span>
                </button>
            </div>
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

        <!-- CARDS DAS ATRIBUIÇÕES DAS EQUIPES -->
        <div class="row g-4 no-print">
            <?php if (empty($equipes)): ?>
                <div class="col-12 text-center py-5 text-muted card-custom">
                    <i class="bi bi-journal-text fs-1 text-muted opacity-50 d-block mb-2"></i>
                    <h5 class="fw-bold">Nenhuma equipe cadastrada para esta edição.</h5>
                    <?php if (!$edicao_inativa && !empty($edicoes_anteriores)): ?>
                        <p class="small mb-3">Você pode importar as equipes e atribuições de uma edição anterior.</p>
                        <button type="button" class="btn btn-resgatar-discreto rounded-3 px-3 py-2 btn-sm" data-bs-toggle="modal" data-bs-target="#modalResgatarEdicao">
                            <i class="bi bi-arrow-repeat me-1"></i> Resgatar edição
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($equipes as $eq): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card-equipe-atribuicao p-4 h-100 d-flex flex-column justify-content-between" 
                             onclick='abrirModalFuncoes(<?= $eq["id"] ?>, <?= json_encode($eq["nome"]) ?>, <?= json_encode($eq["atribuicoes"] ?? "") ?>)'>
                            <div>
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                        <i class="bi bi-journal-text fs-4"></i>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-dark m-0 fs-6"><?= htmlspecialchars($eq['nome']) ?></h5>
                                        <small class="text-muted" style="font-size: 0.78rem;">
                                            <?= $edicao_inativa ? 'Clique para ver as funções' : 'Clique para ver ou editar as funções' ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="bg-light p-3 rounded-3 border">
                                    <small class="fw-bold text-uppercase text-muted d-block mb-1" style="font-size: 0.72rem; letter-spacing: 0.5px;">RESUMO DAS ATRIBUIÇÕES:</small>
                                    <p class="small text-secondary mb-0" style="display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; white-space: pre-line; font-size: 0.85rem;">
                                        <?= !empty($eq['atribuicoes']) ? htmlspecialchars($eq['atribuicoes']) : 'Nenhuma função/atribuição cadastrada ainda.' ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- ESTRUTURA DIRETA PARA IMPRESSÃO / SALVAR EM PDF (1 PÁGINA POR EQUIPE) -->
        <div class="print-wrapper d-none d-print-block">
            <?php foreach ($equipes as $eq): ?>
                <div class="print-card-equipe">
                    <div class="print-card-header">EQUIPE: <?= mb_strtoupper(htmlspecialchars($eq['nome'])) ?></div>
                    <div class="print-card-content">
                        <?= !empty($eq['atribuicoes']) ? htmlspecialchars($eq['atribuicoes']) : 'Nenhuma função/atribuição cadastrada.' ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </main>

    <!-- MODAL EDITAR / VISUALIZAR ATRIBUIÇÕES -->
    <div class="modal fade no-print" id="modalFuncoes" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 overflow-hidden shadow-lg">
                <div class="modal-header modal-header-custom p-4">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2 fs-6">
                        <i class="bi bi-journal-check text-warning fs-5"></i>
                        <span>Funções da Equipe: <span id="modal_nome_equipe"></span></span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipe-atribuicoes.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" method="POST">
                    <input type="hidden" name="acao" value="salvar_atribuicoes">
                    <input type="hidden" name="equipe_id" id="modal_equipe_id">
                    
                    <div class="modal-body p-4">
                        <label class="form-label small fw-bold text-dark mb-2">Descreva todas as funções e responsabilidades desta equipe:</label>
                        <textarea name="atribuicoes" id="modal_texto_atribuicoes" class="form-control rounded-3 p-3" rows="12" <?= $edicao_inativa ? 'readonly' : '' ?> placeholder="Digite ou cole aqui todas as atribuições da equipe..."></textarea>
                    </div>

                    <div class="modal-footer bg-light p-3 border-top justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Fechar</button>
                        <?php if (!$edicao_inativa): ?>
                            <button type="submit" class="btn btn-navy rounded-3 px-4">Salvar Atribuições</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL RESGATAR ATRIBUIÇÕES DE OUTRA EDIÇÃO -->
    <div class="modal fade no-print" id="modalResgatarEdicao" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 overflow-hidden shadow-lg">
                <div class="modal-header modal-header-custom p-4">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2 fs-6">
                        <i class="bi bi-arrow-repeat text-warning fs-5"></i>
                        <span>Resgatar Atribuições de Outra Edição</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipe-atribuicoes.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" method="POST">
                    <input type="hidden" name="acao" value="resgatar_atribuicoes">
                    
                    <div class="modal-body p-4">
                        <?php if (empty($edicoes_anteriores)): ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-info-circle fs-2 d-block mb-2 text-warning"></i>
                                <p class="mb-0 small">Nenhuma outra edição desta modalidade possui equipes e atribuições cadastradas ainda.</p>
                            </div>
                        <?php else: ?>
                            <label class="form-label small fw-bold text-dark mb-2">Selecione o ano / edição de origem:</label>
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
                                    As equipes e textos de atribuições do ano escolhido serão copiados para esta edição. Alterações feitas aqui <strong>não</strong> afetarão a edição anterior.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer bg-light p-3 border-top justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                        <?php if (!empty($edicoes_anteriores)): ?>
                            <button type="submit" class="btn btn-navy rounded-3 px-4">
                                <i class="bi bi-check2-circle me-1"></i> Importar Atribuições
                            </button>
                        <?php endif; ?>
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
    <script>
        let modalElement = document.getElementById('modalFuncoes');
        let modalFuncoesBs = null;

        function abrirModalFuncoes(id, nome, textoAtribuicoes) {
            document.getElementById('modal_equipe_id').value = id;
            document.getElementById('modal_nome_equipe').innerText = nome;
            document.getElementById('modal_texto_atribuicoes').value = textoAtribuicoes;
            
            if (!modalFuncoesBs) {
                modalFuncoesBs = new bootstrap.Modal(modalElement);
            }
            modalFuncoesBs.show();
        }
    </script>
</body>
</html>