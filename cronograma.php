<?php
// cronograma.php
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

$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil       = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL'));

$edicao_id     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

// Verificar permissão / estado da edição
$stmt_ed = $conn->prepare("SELECT status, nome, ano FROM edicoes WHERE id = ?");
$stmt_ed->bind_param("i", $edicao_id);
$stmt_ed->execute();
$edicao = $stmt_ed->get_result()->fetch_assoc();
$is_inativa = ($edicao && strtolower($edicao['status']) === 'inativa');

// Garante as colunas na tabela edicoes (caminho e nome original do PDF)
try {
    $conn->query("ALTER TABLE edicoes ADD COLUMN cronograma_pdf VARCHAR(255) DEFAULT NULL;");
} catch (mysqli_sql_exception $e) {
    // Coluna já existente
}
try {
    $conn->query("ALTER TABLE edicoes ADD COLUMN cronograma_pdf_nome VARCHAR(255) DEFAULT NULL;");
} catch (mysqli_sql_exception $e) {
    // Coluna já existente
}

// Função auxiliar para ler o título interno (/Title) de PDFs enviados anteriormente
function extrairTituloInternoPdf($caminhoArquivo) {
    if (!file_exists($caminhoArquivo)) return null;
    $conteudo = @file_get_contents($caminhoArquivo);
    if ($conteudo && preg_match('/\/Title\s*\(([^)]+)\)/', $conteudo, $matches)) {
        $titulo = trim($matches[1]);
        $titulo = str_replace(['\\(', '\\)', '\\\\'], ['(', ')', '\\'], $titulo);
        $titulo = preg_replace('/\.docx?$/i', '', $titulo);
        $titulo = preg_replace('/[\/\\\\:*?"<>|]/', '', $titulo);
        if (!empty($titulo)) {
            return $titulo . '.pdf';
        }
    }
    return null;
}

// UPLOAD DO PDF DO CRONOGRAMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_upload_crono_pdf'])) {
    if (!$is_inativa && isset($_FILES['arquivo_crono_pdf']) && $_FILES['arquivo_crono_pdf']['error'] === UPLOAD_ERR_OK) {
        $nome_original = basename($_FILES['arquivo_crono_pdf']['name']);
        $ext = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            $pasta = 'uploads/cronogramas/';
            if (!is_dir($pasta)) {
                mkdir($pasta, 0777, true);
            }

            $caminho = $pasta . 'crono_edicao_' . $edicao_id . '_' . time() . '.pdf';

            if (move_uploaded_file($_FILES['arquivo_crono_pdf']['tmp_name'], $caminho)) {
                $up = $conn->prepare("UPDATE edicoes SET cronograma_pdf = ?, cronograma_pdf_nome = ? WHERE id = ?");
                $up->bind_param("ssi", $caminho, $nome_original, $edicao_id);
                $up->execute();
                $mensagem_sucesso = "Cronograma em PDF atualizado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao guardar o ficheiro no servidor.";
            }
        } else {
            $mensagem_erro = "Por favor, selecione um ficheiro no formato PDF.";
        }
    }
}

// Procurar PDF anexado e o seu nome original
$stmt_file = $conn->prepare("SELECT cronograma_pdf, cronograma_pdf_nome FROM edicoes WHERE id = ?");
$stmt_file->bind_param("i", $edicao_id);
$stmt_file->execute();
$res_file = $stmt_file->get_result()->fetch_assoc();

$caminhoPdf = !empty($res_file['cronograma_pdf']) && file_exists($res_file['cronograma_pdf']) ? $res_file['cronograma_pdf'] : null;

// Determina o melhor nome para download
$nomeOriginalPdf = 'Cronograma.pdf';
if (!empty($res_file['cronograma_pdf_nome'])) {
    $nomeOriginalPdf = $res_file['cronograma_pdf_nome'];
} elseif ($caminhoPdf) {
    $tituloExtraido = extrairTituloInternoPdf($caminhoPdf);
    if ($tituloExtraido) {
        $nomeOriginalPdf = $tituloExtraido;
    } elseif (!empty($edicao['nome'])) {
        $nomeOriginalPdf = 'Cronograma - ' . $edicao['nome'] . (!empty($edicao['ano']) ? ' ' . $edicao['ano'] : '') . '.pdf';
    }
}

// FORÇA DOWNLOAD COM O NOME EXATO VIA CABEÇALHO HTTP
if (isset($_GET['download_pdf']) && $caminhoPdf) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $nomeOriginalPdf) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($caminhoPdf));
    readfile($caminhoPdf);
    exit;
}

// Procurar dados da modalidade
$stmt_mod = $conn->prepare("SELECT nome FROM modalidades WHERE id = ?");
$stmt_mod->bind_param("i", $modalidade_id);
$stmt_mod->execute();
$modalidade = $stmt_mod->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cronograma - <?= htmlspecialchars($modalidade['nome'] ?? '') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-main: #F8FAFC;
            --navy-dark: #0A192F;
            --navy-hover: #172A46;
            --gold-primary: #C5A059;
            --gold-hover: #B28D46;
            --border-color: #E2E8F0;
        }

        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: var(--bg-main); 
            color: #0f172a; 
            margin: 0;
            padding: 0;
            min-height: 100vh;
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
            border: none;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-gold:hover {
            background-color: var(--gold-hover);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
        }

        .btn-outline-gold {
            border: 1.5px solid var(--gold-primary);
            color: var(--gold-primary);
            background: transparent;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-outline-gold:hover {
            background-color: var(--gold-primary);
            color: #ffffff;
            border-color: var(--gold-primary);
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.25);
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
            box-shadow: 0 4px 12px rgba(10, 25, 47, 0.2);
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
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background-color: #F1F5F9;
            transition: color 0.2s ease, background-color 0.2s ease;
            border: none;
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

        /* DISTRIBUIÇÃO DOS BOTÕES NO CABEÇALHO */
        .header-actions-grid {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .header-actions-grid .btn {
            white-space: nowrap;
            justify-content: center;
        }

        /* VISUALIZADOR DESKTOP */
        .pdf-viewer-desktop {
            width: 100%;
            height: calc(100vh - 220px);
            min-height: 620px;
            background: #ffffff;
            border: 1.5px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(10, 25, 47, 0.05);
        }

        .pdf-viewer-desktop object {
            width: 100%;
            height: 100%;
            border: none;
        }

        /* VISUALIZADOR MOBILE / TABLET (PDF.JS SEM BLOQUEIO) */
        .pdf-viewer-mobile {
            display: none;
            background: #E2E8F0;
            border: 1.5px solid var(--border-color);
            border-radius: 18px;
            padding: 16px;
            box-shadow: 0 4px 16px rgba(10, 25, 47, 0.05);
        }

        #pdf-mobile-pages canvas {
            width: 100% !important;
            height: auto !important;
            display: block;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 14px;
        }

        #pdf-mobile-pages canvas:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 767.98px) {
            .header-actions-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
                width: 100%;
                padding-top: 12px;
                border-top: 1px solid #F1F5F9;
            }

            .header-actions-grid .btn-upload-pdf {
                grid-column: span 2;
                padding-top: 10px !important;
                padding-bottom: 10px !important;
            }

            .header-actions-grid.no-upload .btn-fullscreen-pdf {
                grid-column: span 1;
            }

            .header-actions-grid .btn {
                padding-top: 9px !important;
                padding-bottom: 9px !important;
                font-size: 0.84rem;
            }

            .pdf-viewer-mobile {
                padding: 12px;
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

    <!-- CABEÇALHO DA PÁGINA -->
    <div class="bg-white border-bottom py-3 mb-4">
        <div class="container d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3" style="max-width: 1280px;">
            <div class="d-flex align-items-center gap-3">
                <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="btn-only-arrow" title="Voltar">
                    <i class="bi bi-arrow-left fs-5"></i>
                </a>
                <div>
                    <h5 class="fw-bold m-0 text-dark d-flex align-items-center gap-2">
                        <i class="bi bi-calendar2-week-fill" style="color: var(--gold-primary);"></i>Cronograma
                    </h5>
                    <small class="text-muted">Cronograma Oficial - <?= htmlspecialchars($modalidade['nome'] ?? '') ?> (<?= htmlspecialchars($edicao['nome'] ?? '') ?>)</small>
                </div>
            </div>

            <div class="header-actions-grid <?= $is_inativa ? 'no-upload' : '' ?>">
                <?php if (!$is_inativa): ?>
                    <button class="btn btn-gold rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2 btn-upload-pdf" data-bs-toggle="modal" data-bs-target="#modalUploadCronoPDF">
                        <i class="bi bi-file-earmark-arrow-up-fill"></i>
                        <span><?= $caminhoPdf ? 'Substituir PDF' : 'Anexar PDF' ?></span>
                    </button>
                <?php endif; ?>

                <?php if ($caminhoPdf): ?>
                    <a href="<?= htmlspecialchars($caminhoPdf) ?>?v=<?= time() ?>" target="_blank" class="btn btn-outline-navy rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2 btn-fullscreen-pdf">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>Tela cheia</span>
                    </a>
                    
                    <a href="cronograma.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&download_pdf=1" class="btn btn-outline-gold rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2">
                        <i class="bi bi-download"></i>
                        <span>Baixar PDF</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <main class="container py-2" style="max-width: 1280px;">

        <!-- NOTIFICAÇÕES -->
        <?php if ($mensagem_sucesso): ?>
            <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= $mensagem_sucesso ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($mensagem_erro): ?>
            <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= $mensagem_erro ?></div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- ÁREA DE VISUALIZAÇÃO DO PDF -->
        <?php if ($caminhoPdf): ?>
            <!-- DESKTOP: Leitor Nativo -->
            <div id="pdf-desktop-container" class="pdf-viewer-desktop mb-4">
                <object data="<?= htmlspecialchars($caminhoPdf) ?>?v=<?= time() ?>#toolbar=1&navpanes=0" type="application/pdf">
                    <div class="p-5 text-center">
                        <p>O navegador não suporta a pré-visualização direta de ficheiros PDF.</p>
                        <a href="cronograma.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&download_pdf=1" class="btn btn-gold rounded-pill px-4">Baixar Ficheiro PDF</a>
                    </div>
                </object>
            </div>

            <!-- CELULAR / TABLET / SIMULADOR: Renderização via PDF.js -->
            <div id="pdf-mobile-container" class="pdf-viewer-mobile mb-4">
                <div id="pdf-mobile-loading" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm me-2" style="color: var(--gold-primary);" role="status"></div>
                    <span>Carregando cronograma...</span>
                </div>
                <div id="pdf-mobile-pages"></div>
            </div>
        <?php else: ?>
            <div class="bg-white border rounded-4 p-5 text-center text-secondary shadow-sm my-4">
                <i class="bi bi-file-earmark-pdf fs-1 d-block mb-3" style="color: var(--gold-primary);"></i>
                <h4 class="fw-bold text-dark">Nenhum cronograma em PDF anexado</h4>
                <p class="m-0 text-muted mb-4">Envie o PDF oficial do cronograma para visualização direta.</p>
                <?php if (!$is_inativa): ?>
                    <button class="btn btn-gold rounded-pill px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalUploadCronoPDF">
                        <i class="bi bi-upload me-2"></i> Selecionar e Anexar PDF
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- MODAL UPLOAD PDF -->
    <?php if (!$is_inativa): ?>
    <div class="modal fade" id="modalUploadCronoPDF" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
                <div class="modal-header modal-header-custom p-4">
                    <h5 class="modal-title fw-bold d-flex align-items-center gap-2 text-white">
                        <i class="bi bi-file-earmark-pdf-fill" style="color: var(--gold-primary);"></i> Anexar Cronograma (PDF)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao_upload_crono_pdf" value="1">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Selecione o PDF *</label>
                            <input type="file" name="arquivo_crono_pdf" accept="application/pdf" class="form-control rounded-3" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light p-3 border-top">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold rounded-pill px-4">Enviar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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
        document.addEventListener("DOMContentLoaded", function () {
            // Detecta celular, tablet (iPad/Android), simulador touch ou navegador sem suporte nativo a PDF
            const isMobileOrTablet = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
                || (navigator.maxTouchPoints && navigator.maxTouchPoints > 0)
                || (navigator.pdfViewerEnabled === false)
                || window.innerWidth <= 1024;

            if (isMobileOrTablet) {
                const desktopContainer = document.getElementById('pdf-desktop-container');
                const mobileContainer = document.getElementById('pdf-mobile-container');
                const container = document.getElementById('pdf-mobile-pages');
                const loading = document.getElementById('pdf-mobile-loading');

                if (desktopContainer) desktopContainer.style.display = 'none';
                if (mobileContainer) mobileContainer.style.display = 'block';

                const urlPdf = "<?= htmlspecialchars($caminhoPdf) ?>?v=<?= time() ?>";

                pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

                pdfjsLib.getDocument(urlPdf).promise.then(async function (pdf) {
                    loading.style.display = 'none';
                    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                        const page = await pdf.getPage(pageNum);
                        const viewport = page.getViewport({ scale: 2.0 });
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d');
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;
                        container.appendChild(canvas);

                        await page.render({
                            canvasContext: context,
                            viewport: viewport
                        }).promise;
                    }
                }).catch(function () {
                    loading.innerHTML = `
                        <p class="text-dark fw-semibold mb-3">Não foi possível carregar a pré-visualização.</p>
                        <a href="cronograma.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&download_pdf=1" class="btn btn-gold rounded-pill px-4 btn-sm">
                            <i class="bi bi-download me-1"></i> Baixar PDF
                        </a>
                    `;
                });
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>