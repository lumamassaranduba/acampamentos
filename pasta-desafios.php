<?php
// pasta-desafios.php
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

$edicao_id     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

// Verificar permissão / estado da edição
$stmt_ed = $conn->prepare("SELECT status, nome, ano FROM edicoes WHERE id = ?");
$stmt_ed->bind_param("i", $edicao_id);
$stmt_ed->execute();
$edicao = $stmt_ed->get_result()->fetch_assoc();
$is_inativa = ($edicao && strtolower($edicao['status']) === 'inativa');

// Garante a coluna pasta_desafios_pdf na tabela edicoes
try {
    $conn->query("ALTER TABLE edicoes ADD COLUMN pasta_desafios_pdf VARCHAR(255) DEFAULT NULL;");
} catch (mysqli_sql_exception $e) {
    // Coluna já existente
}

// UPLOAD DO PDF DA PASTA DOS DESAFIOS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_upload_pasta_pdf'])) {
    if (!$is_inativa && isset($_FILES['arquivo_pasta_pdf']) && $_FILES['arquivo_pasta_pdf']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['arquivo_pasta_pdf']['name'], PATHINFO_EXTENSION));

        if ($ext === 'pdf') {
            $pasta = 'uploads/pasta_desafios/';
            if (!is_dir($pasta)) {
                mkdir($pasta, 0777, true);
            }

            $caminho = $pasta . 'pasta_desafios_edicao_' . $edicao_id . '_' . time() . '.pdf';

            if (move_uploaded_file($_FILES['arquivo_pasta_pdf']['tmp_name'], $caminho)) {
                $up = $conn->prepare("UPDATE edicoes SET pasta_desafios_pdf = ? WHERE id = ?");
                $up->bind_param("si", $caminho, $edicao_id);
                $up->execute();
                $mensagem_sucesso = "Pasta dos Desafios em PDF atualizada com sucesso!";
            } else {
                $mensagem_erro = "Erro ao guardar o arquivo no servidor.";
            }
        } else {
            $mensagem_erro = "Por favor, selecione um arquivo no formato PDF.";
        }
    }
}

// Procurar PDF anexado
$stmt_file = $conn->prepare("SELECT pasta_desafios_pdf FROM edicoes WHERE id = ?");
$stmt_file->bind_param("i", $edicao_id);
$stmt_file->execute();
$res_file = $stmt_file->get_result()->fetch_assoc();
$caminhoPdf = !empty($res_file['pasta_desafios_pdf']) && file_exists($res_file['pasta_desafios_pdf']) ? $res_file['pasta_desafios_pdf'] : null;

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
    <title>Pasta dos Desafios - <?= htmlspecialchars($modalidade['nome'] ?? '') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-main: #f8fafc;
            --navy-dark: #0A192F;
            --gold-primary: #D4AF37;
            --border-color: #e2e8f0;
        }

        body { 
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; 
            background-color: var(--bg-main); 
            color: #0f172a; 
            min-height: 100vh;
        }

        .main-container {
            max-width: 1500px;
            margin: 0 auto;
            padding: 2rem;
        }

        .pdf-viewer-container {
            width: 100%;
            height: calc(100vh - 180px);
            min-height: 650px;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        }

        .pdf-viewer-container object,
        .pdf-viewer-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        @media (max-width: 768px) {
            .main-container { padding: 1rem; }
            .pdf-viewer-container { height: 500px; }
        }
    </style>
</head>
<body>

<div class="main-container">

    <!-- ALERTAS -->
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

    <!-- BARRA SUPERIOR -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="btn btn-outline-secondary rounded-3">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
            <div>
                <h3 class="fw-bold m-0">Pasta dos Desafios</h3>
                <p class="text-muted small m-0">Documento de Desafios e Comunidades - <?= htmlspecialchars($modalidade['nome'] ?? '') ?> (<?= htmlspecialchars($edicao['nome'] ?? '') ?>)</p>
            </div>
        </div>
        
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!$is_inativa): ?>
                <button class="btn btn-dark rounded-3 px-3 py-2 d-flex align-items-center gap-2 fw-medium" data-bs-toggle="modal" data-bs-target="#modalUploadPastaPDF">
                    <i class="bi bi-file-earmark-arrow-up-fill text-warning"></i>
                    <span><?= $caminhoPdf ? 'Substituir PDF dos Desafios' : 'Anexar PDF dos Desafios' ?></span>
                </button>
            <?php endif; ?>

            <?php if ($caminhoPdf): ?>
                <a href="<?= $caminhoPdf ?>?v=<?= time() ?>" target="_blank" class="btn btn-outline-dark rounded-3 px-3 py-2 d-flex align-items-center gap-2 fw-medium">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>Abrir em Tela Cheia</span>
                </a>
                
                <a href="<?= $caminhoPdf ?>?v=<?= time() ?>" download class="btn btn-outline-secondary rounded-3 px-3 py-2 d-flex align-items-center gap-2">
                    <i class="bi bi-download"></i>
                    <span>Baixar</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- ÁREA DE VISUALIZAÇÃO DO PDF -->
    <?php if ($caminhoPdf): ?>
        <div class="pdf-viewer-container">
            <object data="<?= $caminhoPdf ?>?v=<?= time() ?>#toolbar=1&navpanes=0" type="application/pdf">
                <iframe src="<?= $caminhoPdf ?>?v=<?= time() ?>">
                    <div class="p-5 text-center">
                        <p>O navegador não suporta a pré-visualização direta de arquivos PDF.</p>
                        <a href="<?= $caminhoPdf ?>" class="btn btn-primary" download>Baixar Arquivo PDF</a>
                    </div>
                </iframe>
            </object>
        </div>
    <?php else: ?>
        <div class="alert alert-light border rounded-4 p-5 text-center text-secondary shadow-sm my-4">
            <i class="bi bi-trophy-fill fs-1 d-block mb-3 text-warning opacity-75"></i>
            <h4 class="fw-bold text-dark">Nenhum PDF anexado na Pasta dos Desafios</h4>
            <p class="m-0 text-muted mb-4">Envie o arquivo PDF oficial contendo os desafios e orientações das comunidades.</p>
            <?php if (!$is_inativa): ?>
                <button class="btn btn-dark rounded-3 px-4 py-2" data-bs-toggle="modal" data-bs-target="#modalUploadPastaPDF">
                    <i class="bi bi-upload me-2"></i> Selecionar e Anexar PDF
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<!-- MODAL UPLOAD PDF -->
<?php if (!$is_inativa): ?>
<div class="modal fade" id="modalUploadPastaPDF" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-pdf-fill text-warning"></i> Anexar Pasta dos Desafios (PDF)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao_upload_pasta_pdf" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-medium text-dark">Selecione o arquivo PDF *</label>
                        <input type="file" name="arquivo_pasta_pdf" accept="application/pdf" class="form-control rounded-3" required>
                        <div class="form-text small text-muted mt-2">
                            Envie o documento completo da Pasta dos Desafios em formato PDF.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar e Exibir</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>