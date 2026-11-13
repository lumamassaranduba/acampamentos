<?php
// apostila-manutencao.php
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

// Garante a existência da coluna 'apostila_pdf' na tabela modalidades e edicoes
try {
    $conn->query("ALTER TABLE modalidades ADD COLUMN apostila_pdf VARCHAR(255) DEFAULT NULL");
} catch (mysqli_sql_exception $e) {
    // Ignora se a coluna já existir
}

try {
    $conn->query("ALTER TABLE edicoes ADD COLUMN apostila_pdf VARCHAR(255) DEFAULT NULL");
} catch (mysqli_sql_exception $e) {
    // Ignora se a coluna já existir
}

// Busca dados da edição atual (caso edicao_id esteja presente)
$edicao = null;
$is_inativa = false;
if ($edicao_id > 0) {
    $stmt_ed = $conn->prepare("SELECT status, nome, ano, modalidade_id, apostila_pdf FROM edicoes WHERE id = ?");
    $stmt_ed->bind_param("i", $edicao_id);
    $stmt_ed->execute();
    $edicao = $stmt_ed->get_result()->fetch_assoc();
    $is_inativa = ($edicao && strtolower($edicao['status']) === 'inativa');

    if ($modalidade_id === 0 && !empty($edicao['modalidade_id'])) {
        $modalidade_id = intval($edicao['modalidade_id']);
    }
}

// Busca os dados da modalidade
$stmt_mod = $conn->prepare("SELECT nome, apostila_pdf FROM modalidades WHERE id = ?");
$stmt_mod->bind_param("i", $modalidade_id);
$stmt_mod->execute();
$modalidade = $stmt_mod->get_result()->fetch_assoc();

// --- PROCESSAR UPLOAD DO PDF ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_upload_pdf'])) {
    if ($is_inativa) {
        $mensagem_erro = "Esta edição está inativa e não permite alterações.";
    } elseif (isset($_FILES['arquivo_pdf']) && $_FILES['arquivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['arquivo_pdf']['name'], PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            $pasta_destino = 'uploads/apostilas/';
            if (!is_dir($pasta_destino)) {
                mkdir($pasta_destino, 0777, true);
            }

            $nome_original_limpo = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['arquivo_pdf']['name'], PATHINFO_FILENAME));
            if (empty($nome_original_limpo)) {
                $nome_original_limpo = 'apostila_manutencao';
            }
            $sufixo        = $edicao_id > 0 ? '_ed' . $edicao_id : '';
            $nome_arquivo  = $nome_original_limpo . $sufixo . '.pdf';
            $caminho_final = $pasta_destino . $nome_arquivo;

            if (move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $caminho_final)) {
                if ($edicao_id > 0) {
                    $stmt_up = $conn->prepare("UPDATE edicoes SET apostila_pdf = ? WHERE id = ?");
                    $stmt_up->bind_param("si", $caminho_final, $edicao_id);
                    $ok = $stmt_up->execute();

                    // Atualiza também a modalidade como fallback padrão caso ainda não tenha
                    if ($modalidade_id > 0 && empty($modalidade['apostila_pdf'])) {
                        $stmt_up_mod = $conn->prepare("UPDATE modalidades SET apostila_pdf = ? WHERE id = ?");
                        $stmt_up_mod->bind_param("si", $caminho_final, $modalidade_id);
                        $stmt_up_mod->execute();
                        $modalidade['apostila_pdf'] = $caminho_final;
                    }
                } else {
                    $stmt_up = $conn->prepare("UPDATE modalidades SET apostila_pdf = ? WHERE id = ?");
                    $stmt_up->bind_param("si", $caminho_final, $modalidade_id);
                    $ok = $stmt_up->execute();
                    $modalidade['apostila_pdf'] = $caminho_final;
                }

                if ($ok) {
                    $mensagem_sucesso = "Apostila em PDF atualizada com sucesso!";
                    if ($edicao) {
                        $edicao['apostila_pdf'] = $caminho_final;
                    }
                } else {
                    $mensagem_erro = "Erro ao vincular o PDF no banco de dados.";
                }
            } else {
                $mensagem_erro = "Erro ao mover o arquivo para o servidor.";
            }
        } else {
            $mensagem_erro = "Por favor, selecione um arquivo válido no formato PDF.";
        }
    } else {
        $mensagem_erro = "Selecione um arquivo PDF para enviar.";
    }
}

// --- RESGATAR APOSTILA DE OUTRA EDIÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_resgatar_apostila_pdf'])) {
    if ($is_inativa) {
        $mensagem_erro = "Esta edição está inativa e não permite alterações.";
    } else {
        $edicao_origem_id = intval($_POST['edicao_origem_id'] ?? 0);
        if ($edicao_origem_id > 0 && $edicao_id > 0) {
            // Busca o PDF específico da edição de origem ou o PDF herdado da modalidade
            $stmt_orig = $conn->prepare("
                SELECT COALESCE(NULLIF(e.apostila_pdf, ''), m.apostila_pdf) AS pdf_origem
                FROM edicoes e
                LEFT JOIN modalidades m ON e.modalidade_id = m.id
                WHERE e.id = ?
            ");
            $stmt_orig->bind_param("i", $edicao_origem_id);
            $stmt_orig->execute();
            $res_orig = $stmt_orig->get_result()->fetch_assoc();

            $arquivo_origem = $res_orig['pdf_origem'] ?? '';

            if (!empty($arquivo_origem) && file_exists($arquivo_origem)) {
                $pasta_destino = 'uploads/apostilas/';
                if (!is_dir($pasta_destino)) {
                    mkdir($pasta_destino, 0777, true);
                }

                $base_nome = preg_replace('/_ed\d+$/', '', pathinfo($arquivo_origem, PATHINFO_FILENAME));
                $caminho_novo = $pasta_destino . $base_nome . '_ed' . $edicao_id . '.pdf';

                if (copy($arquivo_origem, $caminho_novo)) {
                    $stmt_up = $conn->prepare("UPDATE edicoes SET apostila_pdf = ? WHERE id = ?");
                    $stmt_up->bind_param("si", $caminho_novo, $edicao_id);
                    if ($stmt_up->execute()) {
                        $mensagem_sucesso = "Apostila da Manutenção resgatada com sucesso!";
                        if ($edicao) {
                            $edicao['apostila_pdf'] = $caminho_novo;
                        }
                    } else {
                        $mensagem_erro = "Erro ao vincular o PDF resgatado no banco de dados.";
                    }
                } else {
                    $mensagem_erro = "Erro ao duplicar o arquivo PDF no servidor.";
                }
            } else {
                $mensagem_erro = "O arquivo PDF da edição selecionada não foi encontrado no servidor.";
            }
        }
    }
}

// Buscar edições anteriores da mesma modalidade (considerando tanto o PDF próprio quanto o legado da modalidade)
$edicoes_anteriores = [];
if ($edicao_id > 0 && $modalidade_id > 0) {
    $stmt_ed_ant = $conn->prepare("
        SELECT e.id, e.ano, e.nome 
        FROM edicoes e
        LEFT JOIN modalidades m ON e.modalidade_id = m.id
        WHERE e.modalidade_id = ? 
          AND e.id != ? 
          AND COALESCE(NULLIF(e.apostila_pdf, ''), m.apostila_pdf) IS NOT NULL 
          AND COALESCE(NULLIF(e.apostila_pdf, ''), m.apostila_pdf) != ''
        ORDER BY e.ano DESC, e.id DESC
    ");
    $stmt_ed_ant->bind_param("ii", $modalidade_id, $edicao_id);
    $stmt_ed_ant->execute();
    $edicoes_anteriores = $stmt_ed_ant->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Prioriza o PDF específico da edição; se não houver, usa o da modalidade como fallback
$caminhoPdf = null;
if ($edicao_id > 0 && !empty($edicao['apostila_pdf']) && file_exists($edicao['apostila_pdf'])) {
    $caminhoPdf = $edicao['apostila_pdf'];
} elseif (!empty($modalidade['apostila_pdf']) && file_exists($modalidade['apostila_pdf'])) {
    $caminhoPdf = $modalidade['apostila_pdf'];
}

// Nome exato do arquivo para download
$nomeDownload = $caminhoPdf ? basename($caminhoPdf) : 'Apostila_Manutencao.pdf';

$url_voltar = "dashboard-geral.php";
if ($edicao_id > 0) {
    $url_voltar = "edicao-detalhes.php?edicao_id={$edicao_id}" . ($modalidade_id > 0 ? "&modalidade_id={$modalidade_id}" : "");
}

$subtitulo_header = "Montagem de Desafios";
$detalhe_edicao = trim(($modalidade['nome'] ?? '') . (!empty($edicao['nome']) ? ' (' . $edicao['nome'] . ')' : ''));
if ($detalhe_edicao !== '') {
    $subtitulo_header .= " - " . $detalhe_edicao;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Apostila da Manutenção - <?= htmlspecialchars($modalidade['nome'] ?? 'ÉFATA') ?></title>
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
            --border-color: #E2E8F0;
        }

        * { box-sizing: border-box; }

        body {
            max-width: 100%;
            overflow-x: hidden;
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-main);
            color: var(--navy-dark);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        body.modal-open {
            overflow: hidden !important;
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

        /* BOTÕES PADRONIZADOS */
        .btn-gold-pill {
            background-color: var(--gold-primary);
            color: #ffffff;
            font-weight: 600;
            border: 1.5px solid var(--gold-primary);
            border-radius: 50px;
            padding: 0.55rem 1.25rem;
            transition: all 0.2s ease;
        }
        .btn-gold-pill:hover {
            background-color: var(--gold-hover);
            border-color: var(--gold-hover);
            color: #ffffff;
        }

        .btn-outline-navy-pill {
            background-color: #ffffff;
            color: var(--navy-dark);
            font-weight: 600;
            border: 1.5px solid var(--navy-dark);
            border-radius: 50px;
            padding: 0.55rem 1.25rem;
            transition: all 0.2s ease;
        }
        .btn-outline-navy-pill:hover {
            background-color: var(--navy-dark);
            color: #ffffff;
        }

        .btn-outline-gold-pill {
            background-color: #ffffff;
            color: var(--gold-primary);
            font-weight: 600;
            border: 1.5px solid var(--gold-primary);
            border-radius: 50px;
            padding: 0.55rem 1.25rem;
            transition: all 0.2s ease;
        }
        .btn-outline-gold-pill:hover {
            background-color: var(--gold-primary);
            color: #ffffff;
        }

        /* BOTÃO DISCRETO RESGATAR EDIÇÃO */
        .btn-resgatar-discreto {
            background-color: transparent;
            color: #64748B;
            border: 1px solid #E2E8F0;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.82rem;
            padding: 0.45rem 1rem;
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

        /* VISUALIZADOR PDF DESKTOP */
        .pdf-viewer-desktop {
            width: 100%;
            height: calc(100vh - 210px);
            min-height: 620px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.04);
        }

        .pdf-viewer-desktop object,
        .pdf-viewer-desktop iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* VISUALIZADOR PDF MOBILE / TABLET (PDF.JS CANVAS) */
        .pdf-viewer-mobile {
            display: none;
            width: 100%;
            background: #E2E8F0;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 12px;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.04);
        }

        .pdf-mobile-toolbar {
            background: #ffffff;
            border-radius: 12px;
            padding: 8px 14px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--navy-dark);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
        }

        .pdf-pages-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-height: 72vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 10px;
        }

        .pdf-pages-stack canvas {
            width: 100% !important;
            height: auto !important;
            display: block;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(10, 25, 47, 0.08);
        }

        .header-actions-group {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* ATIVA RENDERIZADOR NATIVO PDF.JS EM TABLETS E CELULARES */
        @media (max-width: 991.98px) {
            .pdf-viewer-desktop {
                display: none !important;
            }
            .pdf-viewer-mobile {
                display: block !important;
            }
        }

        @media (max-width: 767.98px) {
            .header-actions-group {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
                padding-top: 12px;
                border-top: 1px solid #F1F5F9;
            }
            .header-actions-group .btn-principal-full,
            .header-actions-group .btn-resgatar-full {
                grid-column: 1 / -1;
            }
            .header-actions-group .btn {
                justify-content: center;
                padding: 10px 12px;
                font-size: 0.85rem;
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
                <small class="text-light opacity-75 d-block text-truncate" style="font-size: 0.72rem;"><?= htmlspecialchars($perfil_usuario) ?></small>
            </div>
        </div>

        <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout" title="Sair">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Sair</span>
        </button>
    </nav>

    <!-- CABEÇALHO DA PÁGINA COM BOTÕES PADRONIZADOS -->
    <div class="bg-white border-bottom py-3">
        <div class="container d-flex flex-column flex-lg-row align-items-stretch align-items-lg-center justify-content-between gap-3" style="max-width: 1400px;">
            <div class="d-flex align-items-center gap-3">
                <a href="<?= htmlspecialchars($url_voltar) ?>" class="btn-only-arrow" title="Voltar">
                    <i class="bi bi-arrow-left fs-5"></i>
                </a>
                <div class="min-w-0">
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2" style="font-size: 1.05rem;">
                        <i class="bi bi-tools flex-shrink-0" style="color: var(--gold-primary);"></i>
                        <span>Apostila da Manutenção</span>
                    </h5>
                    <small class="text-muted fw-medium d-block">
                        <?= htmlspecialchars($subtitulo_header) ?>
                    </small>
                </div>
            </div>

            <div class="header-actions-group">
                <?php if (!$is_inativa && $edicao_id > 0): ?>
                    <button type="button" class="btn btn-resgatar-discreto btn-sm d-inline-flex align-items-center gap-1 btn-resgatar-full" data-bs-toggle="modal" data-bs-target="#modalResgatarApostilaPDF" title="Importar apostila de outra edição">
                        <i class="bi bi-arrow-repeat"></i>
                        <span>Resgatar edição</span>
                    </button>
                <?php endif; ?>

                <?php if (!$is_inativa): ?>
                    <button type="button" class="btn btn-gold-pill btn-sm d-inline-flex align-items-center gap-2 btn-principal-full" data-bs-toggle="modal" data-bs-target="#modalUploadPDF">
                        <i class="bi bi-file-earmark-arrow-up-fill"></i>
                        <span><?= $caminhoPdf ? 'Substituir PDF' : 'Anexar PDF da Apostila' ?></span>
                    </button>
                <?php endif; ?>

                <?php if ($caminhoPdf): ?>
                    <a href="<?= htmlspecialchars($caminhoPdf) ?>?v=<?= time() ?>" target="_blank" class="btn btn-outline-navy-pill btn-sm d-inline-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>Tela cheia</span>
                    </a>

                    <a href="<?= htmlspecialchars($caminhoPdf) ?>?v=<?= time() ?>" download="<?= htmlspecialchars($nomeDownload) ?>" class="btn btn-outline-gold-pill btn-sm d-inline-flex align-items-center gap-2">
                        <i class="bi bi-download"></i>
                        <span>Baixar PDF</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <main class="container py-4" style="max-width: 1400px;">

        <!-- MENSAGENS DE SUCESSO E ERRO -->
        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($mensagem_sucesso) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($mensagem_erro) ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ÁREA DE VISUALIZAÇÃO DO PDF -->
        <?php if ($caminhoPdf): ?>
            <!-- DESKTOP: Leitor nativo -->
            <div class="pdf-viewer-desktop">
                <object data="<?= htmlspecialchars($caminhoPdf) ?>?v=<?= time() ?>#toolbar=1&navpanes=0" type="application/pdf">
                    <iframe src="<?= htmlspecialchars($caminhoPdf) ?>?v=<?= time() ?>"></iframe>
                </object>
            </div>

            <!-- CELULAR E TABLET: Renderizador PDF.js (evita bloqueio do Chrome/Safari mobile) -->
            <div class="pdf-viewer-mobile" id="pdfMobileContainer">
                <div class="pdf-mobile-toolbar">
                    <span class="text-truncate me-2">
                        <i class="bi bi-file-earmark-pdf-fill me-1" style="color: var(--gold-primary);"></i>
                        <?= htmlspecialchars($nomeDownload) ?>
                    </span>
                    <span class="badge rounded-pill" style="background-color: var(--navy-dark); color: var(--gold-primary);" id="pdfPageBadge">
                        Carregando...
                    </span>
                </div>

                <div id="pdfMobileLoading" class="text-center py-5 bg-white rounded-3">
                    <div class="spinner-border spinner-border-sm mb-2" style="color: var(--gold-primary);" role="status"></div>
                    <div class="small text-muted fw-medium">Carregando páginas da apostila...</div>
                </div>

                <div class="pdf-pages-stack" id="pdfPagesStack"></div>
            </div>
        <?php else: ?>
            <!-- CARD MINIMALISTA QUANDO NÃO HÁ PDF CADASTRADO -->
            <div class="bg-white border rounded-4 p-4 p-md-5 text-center shadow-sm my-3">
                <i class="bi bi-file-earmark-pdf fs-1 d-block mb-3" style="color: var(--gold-primary);"></i>
                <h5 class="fw-bold text-dark mb-1">Nenhuma apostila em PDF anexada</h5>
                <p class="text-muted small mb-4">Envie o arquivo PDF oficial com os desafios e orientações de montagem, ou importe de uma edição anterior.</p>
                <?php if (!$is_inativa): ?>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <?php if ($edicao_id > 0 && !empty($edicoes_anteriores)): ?>
                            <button type="button" class="btn btn-resgatar-discreto btn-sm px-3 py-2 d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#modalResgatarApostilaPDF">
                                <i class="bi bi-arrow-repeat"></i>
                                <span>Resgatar edição</span>
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-gold-pill btn-sm px-4 py-2 d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalUploadPDF">
                            <i class="bi bi-file-earmark-arrow-up-fill"></i>
                            <span>Selecionar e Anexar PDF</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- MODAL UPLOAD PDF -->
    <?php if (!$is_inativa): ?>
    <div class="modal fade" id="modalUploadPDF" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
                <div class="modal-header modal-header-custom p-3 p-md-4">
                    <h5 class="modal-title fw-bold text-white m-0 d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-pdf-fill" style="color: var(--gold-primary);"></i> Anexar Apostila (PDF)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao_upload_pdf" value="1">
                    <div class="modal-body p-3 p-md-4">
                        <div class="mb-2">
                            <label class="form-label fw-semibold text-dark">Selecione o arquivo PDF oficial </label>
                            <input type="file" name="arquivo_pdf" accept="application/pdf" class="form-control rounded-3" required>
                            <div class="form-text small text-muted mt-2">
                                Selecione a apostila completa em formato PDF.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3 border-top justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold-pill btn-sm px-4">Salvar e Exibir PDF</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL RESGATAR APOSTILA DE OUTRA EDIÇÃO -->
    <?php if ($edicao_id > 0): ?>
    <div class="modal fade" id="modalResgatarApostilaPDF" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
                <div class="modal-header modal-header-custom p-3 p-md-4">
                    <h5 class="modal-title fw-bold fs-6 text-white m-0 d-flex align-items-center gap-2">
                        <i class="bi bi-arrow-repeat text-warning fs-5"></i>
                        <span>Resgatar Apostila da Manutenção</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <input type="hidden" name="acao_resgatar_apostila_pdf" value="1">
                    <div class="modal-body p-4">
                        <?php if (empty($edicoes_anteriores)): ?>
                            <div class="text-center py-3 text-muted">
                                <i class="bi bi-info-circle fs-2 d-block mb-2" style="color: var(--gold-primary);"></i>
                                <p class="mb-0 small">Nenhuma outra edição desta modalidade possui o PDF da Apostila da Manutenção anexado ainda.</p>
                            </div>
                        <?php else: ?>
                            <label class="form-label fw-semibold small text-dark mb-2">Selecione o ano / edição de origem:</label>
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
                                    Será criada uma cópia independente do arquivo PDF do ano escolhido para esta edição. Substituições futuras aqui <strong>não</strong> afetarão a edição anterior.
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer bg-light p-3 border-top justify-content-between">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4 btn-sm" data-bs-dismiss="modal">Cancelar</button>
                        <?php if (!empty($edicoes_anteriores)): ?>
                            <button type="submit" class="btn btn-gold-pill btn-sm px-4">
                                <i class="bi bi-check2-circle me-1"></i> Importar PDF
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

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

    <?php if ($caminhoPdf): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        (function () {
            const pdfUrl = <?= json_encode($caminhoPdf . '?v=' . time()) ?>;
            const stack = document.getElementById('pdfPagesStack');
            const loading = document.getElementById('pdfMobileLoading');
            const badge = document.getElementById('pdfPageBadge');
            let jaRenderizou = false;

            async function renderizarPdfMobile() {
                if (jaRenderizou || window.innerWidth > 991) return;
                jaRenderizou = true;

                try {
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                    const pdf = await pdfjsLib.getDocument(pdfUrl).promise;
                    const total = pdf.numPages;

                    badge.textContent = total + (total === 1 ? ' página' : ' páginas');
                    loading.style.display = 'none';

                    const dpr = Math.min(window.devicePixelRatio || 1, 2);

                    for (let pageNum = 1; pageNum <= total; pageNum++) {
                        const page = await pdf.getPage(pageNum);
                        const unscaledViewport = page.getViewport({ scale: 1 });
                        const containerWidth = stack.clientWidth || (window.innerWidth - 48);
                        const scale = (containerWidth / unscaledViewport.width) * dpr;
                        const viewport = page.getViewport({ scale: scale });

                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;

                        stack.appendChild(canvas);

                        await page.render({
                            canvasContext: context,
                            viewport: viewport
                        }).promise;
                    }
                } catch (err) {
                    loading.innerHTML = '<div class="text-danger small fw-semibold">Não foi possível pré-visualizar aqui. Use os botões <strong>Tela cheia</strong> ou <strong>Baixar PDF</strong> acima.</div>';
                    badge.textContent = 'PDF';
                }
            }

            renderizarPdfMobile();
            window.addEventListener('resize', renderizarPdfMobile);
        })();
    </script>
    <?php endif; ?>
</body>
</html>