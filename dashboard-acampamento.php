<?php
// dashboard-acampamento.php
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

$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;
$perfil        = strtoupper(trim($_SESSION['usuario_perfil'] ?? ''));
$permitidos    = $_SESSION['modalidades_permitidas'] ?? [];

$perfis_gerais = ['COORDENADORA_GERAL', 'COORDENADOR_GERAL', 'DIRETOR', 'COORDENADOR'];

// Validação de acesso restrito
if (!in_array($perfil, $perfis_gerais, true)) {
    $permitidos_int = array_map('intval', (array)$permitidos);
    
    if (!in_array($modalidade_id, $permitidos_int, true)) {
        header("Location: dashboard-geral.php?erro=sem_permissao");
        exit;
    }
}

// --- 1. CRIAR NOVA EDIÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_criar_edicao'])) {
    $nome_edicao = trim($_POST['nome_edicao'] ?? '');
    $ano_edicao  = intval($_POST['ano_edicao'] ?? date('Y'));

    if (!empty($nome_edicao) && $modalidade_id > 0) {
        try {
            $stmt_add = $conn->prepare("INSERT INTO edicoes (modalidade_id, nome, ano, status) VALUES (?, ?, ?, 'ativa')");
            $stmt_add->bind_param("isi", $modalidade_id, $nome_edicao, $ano_edicao);
            
            if ($stmt_add->execute()) {
                $mensagem_sucesso = "Nova edição criada com sucesso!";
            } else {
                $mensagem_erro = "Erro ao cadastrar edição.";
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                $mensagem_erro = "Já existe uma edição cadastrada para o ano {$ano_edicao} nesta modalidade!";
            } else {
                $mensagem_erro = "Erro ao cadastrar edição: " . $e->getMessage();
            }
        }
    } else {
        $mensagem_erro = "Preencha todos os campos obrigatórios.";
    }
}

// --- 2. EDITAR EDIÇÃO EXISTENTE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_editar_edicao'])) {
    $edicao_id_edit = intval($_POST['edicao_id'] ?? 0);
    $nome_edit      = trim($_POST['nome'] ?? '');
    $ano_edit       = intval($_POST['ano'] ?? date('Y'));

    $stmt_check = $conn->prepare("SELECT status FROM edicoes WHERE id = ? AND modalidade_id = ?");
    $stmt_check->bind_param("ii", $edicao_id_edit, $modalidade_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();

    if ($res_check) {
        if ($res_check['status'] === 'inativa') {
            $mensagem_erro = "Edições inativas não podem ser alteradas!";
        } else {
            try {
                $stmt_up_ed = $conn->prepare("UPDATE edicoes SET nome = ?, ano = ? WHERE id = ? AND modalidade_id = ?");
                $stmt_up_ed->bind_param("siii", $nome_edit, $ano_edit, $edicao_id_edit, $modalidade_id);
                if ($stmt_up_ed->execute()) {
                    $mensagem_sucesso = "Edição alterada com sucesso!";
                } else {
                    $mensagem_erro = "Erro ao atualizar edição.";
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $mensagem_erro = "Já existe outra edição com o ano {$ano_edit} nesta modalidade!";
                } else {
                    $mensagem_erro = "Erro ao atualizar edição: " . $e->getMessage();
                }
            }
        }
    }
}

// --- 3. ALTERAR STATUS DA EDIÇÃO (ATIVAR / INATIVAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_toggle_status_edicao'])) {
    $edicao_id_status  = intval($_POST['edicao_id'] ?? 0);
    $novo_status_input = $_POST['novo_status'] ?? ''; 
    $novo_status       = ($novo_status_input === 'ativar') ? 'ativa' : 'inativa';

    $stmt_up_status = $conn->prepare("UPDATE edicoes SET status = ? WHERE id = ? AND modalidade_id = ?");
    $stmt_up_status->bind_param("sii", $novo_status, $edicao_id_status, $modalidade_id);
    
    if ($stmt_up_status->execute()) {
        $mensagem_sucesso = "Status da edição atualizado para " . ucfirst($novo_status) . "!";
    } else {
        $mensagem_erro = "Erro ao alterar status da edição.";
    }
}

// --- 4. EXCLUIR EDIÇÃO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_edicao'])) {
    $edicao_id_del = intval($_POST['edicao_id'] ?? 0);

    $stmt_check = $conn->prepare("SELECT status FROM edicoes WHERE id = ? AND modalidade_id = ?");
    $stmt_check->bind_param("ii", $edicao_id_del, $modalidade_id);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();

    if ($res_check) {
        if ($res_check['status'] === 'inativa') {
            $mensagem_erro = "Edições inativas não podem ser excluídas!";
        } else {
            $stmt_del = $conn->prepare("DELETE FROM edicoes WHERE id = ? AND modalidade_id = ?");
            $stmt_del->bind_param("ii", $edicao_id_del, $modalidade_id);
            if ($stmt_del->execute()) {
                $mensagem_sucesso = "Edição removida com sucesso!";
            } else {
                $mensagem_erro = "Erro ao excluir edição.";
            }
        }
    }
}

// --- 5. ATUALIZAR INFORMAÇÕES DA MODALIDADE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_editar_modalidade'])) {
    $nome_novo         = trim($_POST['nome'] ?? '');
    $lema_novo         = trim($_POST['lema'] ?? '');
    $publico_alvo_novo = trim($_POST['publico_alvo'] ?? '');
    $cor_nova          = trim($_POST['cor'] ?? '#C5A059');

    if (!empty($nome_novo)) {
        try {
            $conn->query("ALTER TABLE modalidades ADD COLUMN logo VARCHAR(255) DEFAULT NULL");
        } catch (mysqli_sql_exception $e) {
        }

        $stmt_up = $conn->prepare("UPDATE modalidades SET nome = ?, lema = ?, publico_alvo = ?, cor = ? WHERE id = ?");
        $stmt_up->bind_param("ssssi", $nome_novo, $lema_novo, $publico_alvo_novo, $cor_nova, $modalidade_id);
        
        if ($stmt_up->execute()) {
            $mensagem_sucesso = "Informações atualizadas!";
            
            $pasta_destino = 'uploads/logos/';
            if (!is_dir($pasta_destino)) {
                mkdir($pasta_destino, 0777, true);
            }

            $nomeLimpo = mb_strtolower(trim($nome_novo), 'UTF-8');
            $nomeLimpo = preg_replace('/[áàãâä]/u', 'a', $nomeLimpo);
            $nomeLimpo = preg_replace('/[éèêë]/u', 'e', $nomeLimpo);
            $nomeLimpo = preg_replace('/[íìîï]/u', 'i', $nomeLimpo);
            $nomeLimpo = preg_replace('/[óòõôö]/u', 'o', $nomeLimpo);
            $nomeLimpo = preg_replace('/[úùûü]/u', 'u', $nomeLimpo);

            if (!empty($_POST['logo_cropped'])) {
                $imgData = $_POST['logo_cropped'];
                $imgData = str_replace('data:image/png;base64,', '', $imgData);
                $imgData = str_replace(' ', '+', $imgData);
                $data = base64_decode($imgData);

                $caminho_final = $pasta_destino . $nomeLimpo . '.png';
                file_put_contents($caminho_final, $data);

                $stmt_img = $conn->prepare("UPDATE modalidades SET logo = ? WHERE id = ?");
                $stmt_img->bind_param("si", $caminho_final, $modalidade_id);
                $stmt_img->execute();
            } 
            elseif (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $extensoes_permitidas = ['png', 'jpg', 'jpeg', 'webp'];

                if (in_array($ext, $extensoes_permitidas)) {
                    $caminho_final = $pasta_destino . $nomeLimpo . '.' . $ext;
                    move_uploaded_file($_FILES['logo']['tmp_name'], $caminho_final);

                    $stmt_img = $conn->prepare("UPDATE modalidades SET logo = ? WHERE id = ?");
                    $stmt_img->bind_param("si", $caminho_final, $modalidade_id);
                    $stmt_img->execute();
                }
            }
        } else {
            $mensagem_erro = "Erro ao atualizar informações.";
        }
    }
}

// --- 6. EXCLUIR MODALIDADE / ACAMPAMENTO ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_modalidade'])) {
    $stmt_del_mod = $conn->prepare("DELETE FROM modalidades WHERE id = ?");
    $stmt_del_mod->bind_param("i", $modalidade_id);
    
    if ($stmt_del_mod->execute()) {
        header("Location: dashboard-geral.php?sucesso=acampamento_excluido");
        exit;
    } else {
        $mensagem_erro = "Erro ao excluir acampamento.";
    }
}

// --- BUSCA DADOS DA MODALIDADE ---
$stmt = $conn->prepare("SELECT * FROM modalidades WHERE id = ?");
$stmt->bind_param("i", $modalidade_id);
$stmt->execute();
$acampamento = $stmt->get_result()->fetch_assoc();

if (!$acampamento) {
    header("Location: dashboard-geral.php?erro=modalidade_nao_encontrada");
    exit;
}

$nome         = $acampamento['nome'] ?? 'Acampamento';
$lema         = !empty($acampamento['lema']) ? $acampamento['lema'] : 'Acampamento Paroquial Éfata';
$publico_alvo = $acampamento['publico_alvo'] ?? '';
$icone        = !empty($acampamento['icone']) ? $acampamento['icone'] : 'bi-shield-check';
$cor          = !empty($acampamento['cor']) ? $acampamento['cor'] : '#C5A059';

// Verificação do Logo
$nomeLimpo = mb_strtolower(trim($nome), 'UTF-8');
$nomeLimpo = preg_replace('/[áàãâä]/u', 'a', $nomeLimpo);
$nomeLimpo = preg_replace('/[éèêë]/u', 'e', $nomeLimpo);
$nomeLimpo = preg_replace('/[íìîï]/u', 'i', $nomeLimpo);
$nomeLimpo = preg_replace('/[óòõôö]/u', 'o', $nomeLimpo);
$nomeLimpo = preg_replace('/[úùûü]/u', 'u', $nomeLimpo);

$extensoes = ['png', 'jpg', 'jpeg', 'webp'];
$caminhoLogo = null;

if (!empty($acampamento['logo']) && file_exists($acampamento['logo'])) {
    $caminhoLogo = $acampamento['logo'];
} else {
    foreach ($extensoes as $e) {
        if (file_exists('uploads/logos/' . $nomeLimpo . '.' . $e)) {
            $caminhoLogo = 'uploads/logos/' . $nomeLimpo . '.' . $e;
            break;
        }
    }
}

// --- BUSCA TODAS AS EDIÇÕES ---
$stmt_edicoes = $conn->prepare("SELECT * FROM edicoes WHERE modalidade_id = ? ORDER BY ano DESC, id DESC");
$stmt_edicoes->bind_param("i", $modalidade_id);
$stmt_edicoes->execute();
$res_edicoes = $stmt_edicoes->get_result();

$edicoes = [];
while ($row = $res_edicoes->fetch_assoc()) {
    $edicoes[] = $row;
}
$total_edicoes = count($edicoes);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel do Acampamento - <?= htmlspecialchars($nome) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Cropper.js CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">

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

        .main-content { 
            padding: 2rem 1.25rem; 
            width: 100%; 
            max-width: 1300px; 
            margin: 0 auto; 
            box-sizing: border-box;
        }

        /* Banner Header */
        .header-banner {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem 1.75rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            position: relative;
        }

        .logo-acampamento-circle {
            width: 70px;
            height: 70px;
            min-width: 70px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 3px solid var(--gold-primary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            padding: 4px;
        }

        .logo-acampamento-circle img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 50%;
        }

        .badge-lema {
            font-size: 0.88rem;
            color: var(--text-muted);
            font-weight: 500;
            font-style: italic;
        }

        .badge-target-custom {
            font-size: 0.72rem;
            font-weight: 600;
            background-color: var(--navy-dark);
            color: var(--gold-primary);
            padding: 3px 10px;
            border-radius: 12px;
        }

        /* Botões */
        .btn-navy {
            background-color: var(--navy-dark);
            color: #ffffff;
            font-weight: 600;
            border: 1px solid var(--navy-dark);
            transition: all 0.2s ease;
        }

        .btn-navy:hover {
            background-color: var(--gold-primary);
            border-color: var(--gold-primary);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(197, 160, 89, 0.3);
        }

        .btn-outline-gold {
            border: 1px solid var(--gold-primary);
            color: var(--navy-dark);
            font-weight: 600;
            background: transparent;
            transition: all 0.2s ease;
        }

        .btn-outline-gold:hover {
            background-color: var(--gold-primary);
            color: #ffffff;
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

        /* Grid Cards Edições */
        .row-expansiva {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.25rem;
            width: 100%;
        }

        .card-dash { 
            background: #ffffff; 
            border: 1px solid var(--border-color); 
            border-radius: 20px; 
            padding: 1.25rem; 
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); 
            height: 100%; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }
        
        .card-dash:hover { 
            border-color: var(--gold-primary); 
            box-shadow: 0 12px 24px -6px rgba(10, 25, 47, 0.1); 
            transform: translateY(-4px);
        }
        
        .badge-ordinal { 
            font-size: 0.8rem; 
            font-weight: 600; 
            background-color: #f8fafc; 
            color: var(--navy-dark); 
            padding: 5px 12px; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0; 
        }
        
        .card-status-ativa { 
            border-top: 4px solid var(--gold-primary); 
        }

        .card-status-inativa { 
            border-top: 4px solid #cbd5e1; 
            background-color: #f8fafc; 
        }
        
        .badge-ativa { 
            background-color: #ecfdf5; 
            color: #047857; 
            border: 1px solid #a7f3d0; 
            font-weight: 600;
        }

        .badge-inativa { 
            background-color: #f1f5f9; 
            color: #64748b; 
            border: 1px solid #e2e8f0; 
            font-weight: 500;
        }

        .btn-card-action {
            background-color: var(--navy-dark);
            color: #ffffff;
            font-weight: 600;
            border-radius: 12px;
            border: 1px solid var(--navy-dark);
            padding: 0.6rem 1rem;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-card-action:hover {
            background-color: var(--gold-primary);
            border-color: var(--gold-primary);
            color: #ffffff !important;
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        @media (max-width: 576px) {
            .top-navbar {
                padding: 0.65rem 0.85rem;
            }

            .main-content {
                padding: 1.25rem 0.85rem;
            }

            .header-banner {
                padding: 1.1rem;
            }

            .logo-acampamento-circle {
                width: 56px;
                height: 56px;
                min-width: 56px;
            }
        }
    </style>
</head>
<body>

<!-- NAVBAR LIMPA -->
<nav class="top-navbar d-flex align-items-center justify-content-between gap-2">
    <div class="d-flex align-items-center gap-2 gap-sm-3 user-brand-block">
        <div class="brand-icon shadow-sm">
            <img src="./uploads/logos/logoefata.png" alt="Logo Éfata" onerror="this.onerror=null; this.src='assets/img/logo.png';">
        </div>
        <div class="user-info-text">
            <h6 class="fw-bold m-0 text-white text-truncate" style="font-size: 0.92rem;"><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Usuário') ?></h6>
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

<main class="main-content">

    <?php if ($mensagem_sucesso): ?>
        <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div><?= $mensagem_sucesso ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensagem_erro): ?>
        <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= $mensagem_erro ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- CABEÇALHO DO ACAMPAMENTO COM LEMA -->
    <div class="header-banner mb-4 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3 w-100 w-md-auto">
            <a href="dashboard-geral.php" class="btn text-dark border-0 p-0 fs-4 d-flex align-items-center justify-content-center me-1" title="Voltar ao Painel Geral">
                <i class="bi bi-arrow-left"></i>
            </a>

            <div class="logo-acampamento-circle">
                <?php if ($caminhoLogo): ?>
                    <img src="<?= $caminhoLogo ?>?v=<?= time() ?>" alt="Logo <?= htmlspecialchars($nome) ?>">
                <?php else: ?>
                    <i class="bi <?= $icone ?> fs-2 text-warning"></i>
                <?php endif; ?>
            </div>

            <div class="text-truncate">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="fw-bold m-0 text-dark text-truncate" style="font-size: 1.45rem;"><?= htmlspecialchars($nome) ?></h3>
                    <?php if (!empty($publico_alvo)): ?>
                        <span class="badge-target-custom"><?= htmlspecialchars($publico_alvo) ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- FRASE LEMA EXIBIDA -->
                <p class="badge-lema m-0 mt-1 text-truncate">
                    <?= htmlspecialchars($lema) ?>
                </p>
            </div>
        </div>

        <div class="d-flex gap-2 w-100 w-md-auto justify-content-start justify-content-md-end flex-wrap">
            <button class="btn btn-outline-gold btn-sm rounded-3 d-flex align-items-center gap-2 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalEditarAcampamento">
                <i class="bi bi-gear-fill text-warning"></i>
                <span class="text-nowrap">Editar Informações</span>
            </button>

            <button class="btn btn-navy btn-sm rounded-3 d-flex align-items-center gap-2 px-3 py-2 fw-semibold" data-bs-toggle="modal" data-bs-target="#modalNovaEdicao">
                <i class="bi bi-plus-lg text-warning"></i>
                <span class="text-nowrap">Nova Edição</span>
            </button>
        </div>
    </div>

    <!-- SEÇÃO TÍTULO E CONTADOR REFINADO -->
    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between mb-4 gap-2">
        <div>
            <h4 class="fw-bold m-0 text-dark">Edições Registradas</h4>
            <p class="text-muted small m-0">Gerencie todas as edições ativas e finalizadas do acampamento <?= htmlspecialchars($nome) ?>.</p>
        </div>

        <!-- CONTADOR PADRONIZADO E ELEGANTE -->
        <div class="badge bg-white text-dark border px-3 py-2 rounded-3 shadow-sm d-flex align-items-center gap-2 fw-semibold fs-6">
            <i class="bi bi-layers-half text-warning fs-5"></i>
            <span><?= $total_edicoes ?> <?= $total_edicoes === 1 ? 'Edição Cadastrada' : 'Edições Cadastradas' ?></span>
        </div>
    </div>

    <!-- CARDS DAS EDIÇÕES -->
    <div class="row-expansiva">
        <?php 
        $index_ordinal = $total_edicoes;

        foreach ($edicoes as $ed): 
            $siglaModalidade = htmlspecialchars($nome);
            $anoEdicao       = intval($ed['ano']);
            $nomeEdicao      = !empty($ed['nome']) ? htmlspecialchars($ed['nome']) : "{$index_ordinal}° {$siglaModalidade}";
            
            $tituloCard      = "{$nomeEdicao} - {$anoEdicao}";
            $statusEdicao    = strtolower($ed['status'] ?? 'inativa');
            $isAtiva         = ($statusEdicao === 'ativa');
        ?>
            <div class="card-dash <?= $isAtiva ? 'card-status-ativa' : 'card-status-inativa' ?>">
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge-ordinal"><?= $nomeEdicao ?></span>
                        
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($isAtiva): ?>
                                <span class="badge badge-ativa rounded-pill px-3 py-1.5 small">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> Ativa
                                </span>
                            <?php else: ?>
                                <span class="badge badge-inativa rounded-pill px-3 py-1.5 small">
                                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem; vertical-align: middle;"></i> Inativa
                                </span>
                            <?php endif; ?>

                            <div class="dropdown">
                                <button class="btn btn-link text-muted p-1 border-0" type="button" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots-vertical fs-5"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg rounded-3">
                                    <li>
                                        <button class="dropdown-item small d-flex align-items-center gap-2 py-2" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalConfirmarStatus" 
                                                data-id="<?= $ed['id'] ?>"
                                                data-titulo="<?= htmlspecialchars($tituloCard) ?>"
                                                data-status="<?= $isAtiva ? 'inativar' : 'ativar' ?>">
                                            <i class="bi <?= $isAtiva ? 'bi-toggle-off text-secondary' : 'bi-toggle-on text-success' ?>"></i> 
                                            <?= $isAtiva ? 'Marcar como Inativa' : 'Marcar como Ativa' ?>
                                        </button>
                                    </li>
                                    
                                    <?php if ($isAtiva): ?>
                                        <li>
                                            <button class="dropdown-item small d-flex align-items-center gap-2 py-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarEdicao" 
                                                    data-id="<?= $ed['id'] ?>"
                                                    data-nome="<?= htmlspecialchars($ed['nome'] ?? '') ?>"
                                                    data-ano="<?= $ed['ano'] ?>">
                                                <i class="bi bi-pencil text-dark"></i> Editar
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider my-1"></li>
                                        <li>
                                            <button class="dropdown-item small text-danger d-flex align-items-center gap-2 py-2" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalExcluirEdicao" 
                                                    data-id="<?= $ed['id'] ?>"
                                                    data-titulo="<?= htmlspecialchars($tituloCard) ?>">
                                                <i class="bi bi-trash"></i> Excluir
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="fw-bold text-dark mt-2 mb-1"><?= $tituloCard ?></h5>
                    <p class="text-muted small mb-4">
                        <?= $isAtiva ? 'Gerenciar edição.' : 'Edição finalizada (Histórico em leitura).' ?>
                    </p>
                </div>

                <a href="edicao-detalhes.php?edicao_id=<?= $ed['id'] ?>&modalidade_id=<?=$modalidade_id ?>" class="btn btn-card-action w-100 d-flex align-items-center justify-content-center gap-2">
                    <span><?= $isAtiva ? 'Acessar Painel' : 'Visualizar Histórico' ?></span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        <?php 
            $index_ordinal--;
        endforeach; 
        ?>

        <?php if ($total_edicoes === 0): ?>
            <div class="alert alert-light border rounded-4 p-5 text-center text-secondary shadow-sm w-100">
                <i class="bi bi-layers fs-1 d-block mb-3 opacity-50 text-muted"></i>
                <h5 class="fw-bold text-dark">Nenhuma edição cadastrada para <?= htmlspecialchars($nome) ?>.</h5>
                <p class="m-0 text-muted">Utilize o botão acima para criar a primeira edição.</p>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- MODAL EDITAR ACAMPAMENTO -->
<div class="modal fade" id="modalEditarAcampamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header modal-header-custom p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-gear-fill text-warning"></i> Editar Informações do Acampamento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarAcampamento" action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao_editar_modalidade" value="1">
                <input type="hidden" name="logo_cropped" id="logoCroppedBase64Edit">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label small fw-medium text-dark">Nome / Sigla *</label>
                                <input type="text" name="nome" class="form-control rounded-3" value="<?= htmlspecialchars($nome) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-medium text-dark">Lema do Acampamento</label>
                                <input type="text" name="lema" class="form-control rounded-3" placeholder="Ex: Abre-te para uma nova forma..." value="<?= htmlspecialchars($lema) ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label small fw-medium text-dark">Público-Alvo</label>
                                <input type="text" name="publico_alvo" class="form-control rounded-3" value="<?= htmlspecialchars($publico_alvo) ?>" placeholder="Ex: Crismandos, Jovens...">
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-8">
                                    <label class="form-label small fw-medium text-dark">Alterar Logo</label>
                                    <input type="file" id="inputLogoAcampamentoEdit" name="logo" class="form-control rounded-3" accept="image/*">
                                </div>
                                <div class="col-4">
                                    <label class="form-label small fw-medium text-dark">Cor Tema</label>
                                    <input type="color" name="cor" class="form-control form-control-color w-100 rounded-3" value="<?= htmlspecialchars($cor) ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Área de Corte Cropper.js -->
                        <div class="col-md-6 text-center">
                            <label class="form-label small fw-medium text-dark d-block text-start">Enquadramento da Logo</label>
                            <div class="img-container-cropper bg-light rounded-3 border d-flex align-items-center justify-content-center overflow-hidden" style="height: 220px; width: 100%;">
                                <img id="imageToCropEdit" src="" alt="Pré-visualização" style="max-width: 100%; display: none;">
                                <div id="placeholderCropperTextEdit" class="text-muted small p-3">
                                    <?php if ($caminhoLogo): ?>
                                        <img src="<?= $caminhoLogo ?>?v=<?= time() ?>" alt="Logo Atual" style="max-height: 120px; max-width: 100%; object-fit: contain;" class="mb-2 d-block mx-auto rounded-circle border border-warning p-1">
                                        <span class="d-block text-secondary">Selecione uma imagem para ajustar o enquadramento.</span>
                                    <?php else: ?>
                                        <span>Selecione uma imagem para ajustar o enquadramento.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light p-3 border-top d-flex justify-content-between align-items-center">
                    <!-- BOTÃO ABRE O CARD DE CONFIRMAÇÃO EM VEZ DE CONFIRM() -->
                    <button type="button" class="btn btn-danger rounded-3 fw-medium" data-bs-toggle="modal" data-bs-target="#modalConfirmarExcluirAcampamento">
                        <i class="bi bi-trash3-fill me-1"></i> Excluir Acampamento
                    </button>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-navy rounded-3 px-4">Salvar Alterações</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CARD DE CONFIRMAÇÃO DE EXCLUSÃO DO ACAMPAMENTO -->
<div class="modal fade" id="modalConfirmarExcluirAcampamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header bg-danger text-white p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> Excluir Acampamento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="bi bi-trash3 text-danger display-3 d-block mb-3"></i>
                <h5 class="fw-bold text-dark">Tem certeza absoluta?</h5>
                <p class="text-secondary mb-0">
                    Você está prestes a excluir o acampamento <strong><?= htmlspecialchars($nome) ?></strong>. Esta ação é irreversível e removerá todas as edições e registros vinculados a ele.
                </p>
            </div>
            <div class="modal-footer bg-light p-3 border-top justify-content-center gap-2">
                <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger rounded-3 px-4 fw-semibold" onclick="document.getElementById('formExcluirAcampamento').submit();">
                    Sim, Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CONFIRMAR STATUS -->
<div class="modal fade" id="modalConfirmarStatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header modal-header-custom p-4">
                <h5 class="modal-title fw-bold" id="statusModalTitle">Confirmar Alteração</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_toggle_status_edicao" value="1">
                <input type="hidden" name="edicao_id" id="status_edicao_id">
                <input type="hidden" name="novo_status" id="status_novo_val">
                
                <div class="modal-body p-4">
                    <p class="m-0 text-dark fs-6" id="statusModalMessage"></p>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-navy rounded-3 px-4" id="statusModalSubmitBtn">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CRIAR EDIÇÃO -->
<div class="modal fade" id="modalNovaEdicao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header modal-header-custom p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-plus-circle-fill text-warning"></i> Criar Nova Edição
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_criar_edicao" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Nome da Edição *</label>
                        <input type="text" name="nome_edicao" class="form-control rounded-3" placeholder="Ex: <?= ($total_edicoes + 1) ?>° <?= htmlspecialchars($nome) ?>" value="<?= ($total_edicoes + 1) ?>° <?= htmlspecialchars($nome) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Ano de Realização *</label>
                        <input type="number" name="ano_edicao" class="form-control rounded-3" value="<?= date('Y') ?>" min="2000" max="2100" required>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-navy rounded-3 px-4">Criar Edição</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR EDIÇÃO -->
<div class="modal fade" id="modalEditarEdicao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header modal-header-custom p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-warning"></i> Editar Edição
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_editar_edicao" value="1">
                <input type="hidden" name="edicao_id" id="edit_edicao_id">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Nome da Edição *</label>
                        <input type="text" name="nome" id="edit_edicao_nome" class="form-control rounded-3" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Ano de Realização *</label>
                        <input type="number" name="ano" id="edit_edicao_ano" class="form-control rounded-3" min="2000" max="2100" required>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-navy rounded-3 px-4">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EXCLUIR EDIÇÃO -->
<div class="modal fade" id="modalExcluirEdicao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header bg-danger text-white p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> Excluir Edição
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_excluir_edicao" value="1">
                <input type="hidden" name="edicao_id" id="delete_edicao_id">
                
                <div class="modal-body p-4">
                    <p class="m-0 text-dark fs-6">Tem certeza que deseja excluir permanentemente a <strong id="delete_edicao_titulo"></strong>?</p>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4">Excluir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL LOGOUT -->
<div class="modal fade" id="modalConfirmarLogout" tabindex="-1" aria-hidden="true">
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

<form id="formExcluirAcampamento" action="" method="POST" style="display: none;">
    <input type="hidden" name="acao_excluir_modalidade" value="1">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>

<script>
let cropperEdit = null;

document.addEventListener('DOMContentLoaded', function () {
    const inputEdit = document.getElementById('inputLogoAcampamentoEdit');
    if (inputEdit) {
        inputEdit.addEventListener('change', function (e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const file = files[0];
                const reader = new FileReader();

                reader.onload = function (event) {
                    const image = document.getElementById('imageToCropEdit');
                    const placeholder = document.getElementById('placeholderCropperTextEdit');

                    image.src = event.target.result;
                    image.style.display = 'block';
                    placeholder.style.display = 'none';

                    if (cropperEdit) {
                        cropperEdit.destroy();
                    }

                    cropperEdit = new Cropper(image, {
                        aspectRatio: 1,
                        viewMode: 1,
                        background: false,
                        autoCropArea: 0.9,
                    });
                };

                reader.readAsDataURL(file);
            }
        });
    }

    const formEdit = document.getElementById('formEditarAcampamento');
    if (formEdit) {
        formEdit.addEventListener('submit', function (e) {
            if (cropperEdit) {
                e.preventDefault();
                
                const canvas = cropperEdit.getCroppedCanvas({
                    width: 400,
                    height: 400
                });

                document.getElementById('logoCroppedBase64Edit').value = canvas.toDataURL('image/png');
                this.submit();
            }
        });
    }

    const modalConfirmarStatus = document.getElementById('modalConfirmarStatus');
    if (modalConfirmarStatus) {
        modalConfirmarStatus.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const edicaoId = button.getAttribute('data-id');
            const titulo = button.getAttribute('data-titulo');
            const status = button.getAttribute('data-status');

            document.getElementById('status_edicao_id').value = edicaoId;
            document.getElementById('status_novo_val').value = status;

            const modalTitle = document.getElementById('statusModalTitle');
            const modalMsg = document.getElementById('statusModalMessage');

            if (status === 'inativar') {
                modalTitle.textContent = 'Inativar Edição';
                modalMsg.innerHTML = `Tem certeza que deseja marcar a <strong>${titulo}</strong> como <strong>Inativa</strong>?`;
            } else {
                modalTitle.textContent = 'Ativar Edição';
                modalMsg.innerHTML = `Tem certeza que deseja marcar a <strong>${titulo}</strong> como <strong>Ativa</strong>?`;
            }
        });
    }

    const modalEditarEdicao = document.getElementById('modalEditarEdicao');
    if (modalEditarEdicao) {
        modalEditarEdicao.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('edit_edicao_id').value   = button.getAttribute('data-id');
            document.getElementById('edit_edicao_nome').value = button.getAttribute('data-nome');
            document.getElementById('edit_edicao_ano').value  = button.getAttribute('data-ano');
        });
    }

    const modalExcluirEdicao = document.getElementById('modalExcluirEdicao');
    if (modalExcluirEdicao) {
        modalExcluirEdicao.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('delete_edicao_id').value = button.getAttribute('data-id');
            document.getElementById('delete_edicao_titulo').textContent = button.getAttribute('data-titulo');
        });
    }
});
</script>
</body>
</html>