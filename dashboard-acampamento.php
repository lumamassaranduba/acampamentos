<?php
// dashboard-acampamento.php (Início do arquivo)
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
    $cor_nova          = trim($_POST['cor'] ?? '#292524');

    if (!empty($nome_novo)) {
        $stmt_up = $conn->prepare("UPDATE modalidades SET nome = ?, lema = ?, publico_alvo = ?, cor = ? WHERE id = ?");
        $stmt_up->bind_param("ssssi", $nome_novo, $lema_novo, $publico_alvo_novo, $cor_nova, $modalidade_id);
        
        if ($stmt_up->execute()) {
            $mensagem_sucesso = "Informações atualizadas!";
            
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $extensoes_permitidas = ['png', 'jpg', 'jpeg', 'webp'];

                if (in_array($ext, $extensoes_permitidas)) {
                    $nomeLimpo = mb_strtolower(trim($nome_novo), 'UTF-8');
                    $nomeLimpo = preg_replace('/[áàãâä]/u', 'a', $nomeLimpo);
                    $nomeLimpo = preg_replace('/[éèêë]/u', 'e', $nomeLimpo);
                    $nomeLimpo = preg_replace('/[íìîï]/u', 'i', $nomeLimpo);
                    $nomeLimpo = preg_replace('/[óòõôö]/u', 'o', $nomeLimpo);
                    $nomeLimpo = preg_replace('/[úùûü]/u', 'u', $nomeLimpo);

                    $pasta_destino = 'uploads/logos/';
                    if (!is_dir($pasta_destino)) {
                        mkdir($pasta_destino, 0777, true);
                    }

                    $caminho_final = $pasta_destino . $nomeLimpo . '.' . $ext;
                    move_uploaded_file($_FILES['logo']['tmp_name'], $caminho_final);
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
$lema         = $acampamento['lema'] ?? '';
$publico_alvo = $acampamento['publico_alvo'] ?? 'Jovens e Adultos';
$icone        = !empty($acampamento['icone']) ? $acampamento['icone'] : 'bi-shield-check';
$cor          = !empty($acampamento['cor']) ? $acampamento['cor'] : '#292524';

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
    <style>
        :root {
            --brand-color: <?= $cor ?>;
            --bg-main: #f8fafc;
            --border-color: #e2e8f0;
            --text-primary: #0f172a;
            --text-muted: #64748b;
        }

        body { 
            font-family: 'Outfit', -apple-system, BlinkMacSystemFont, sans-serif; 
            background-color: var(--bg-main); 
            color: var(--text-primary); 
            min-height: 100vh;
        }
        
        .main-content { 
            padding: 2.5rem 2rem; 
            width: 100%; 
            max-width: 1600px; 
            margin: 0 auto; 
            box-sizing: border-box;
        }

        /* Banner Header */
        .header-banner {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem 2rem;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
        }

        .header-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background-color: var(--brand-color);
        }

        .btn-back {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        .btn-back:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .icon-circle { 
            width: 60px; 
            height: 60px; 
            border-radius: 16px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.5rem; 
            overflow: hidden; 
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        .icon-circle img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }

        .badge-target {
            font-size: 0.75rem;
            font-weight: 500;
            background-color: #f1f5f9;
            color: #475569;
            padding: 4px 12px;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
        }

        /* Grid Layout */
        .row-expansiva {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
            width: 100%;
        }

        /* Cards */
        .card-dash { 
            background: #ffffff; 
            border: 1px solid var(--border-color); 
            border-radius: 18px; 
            padding: 1.5rem; 
            transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1); 
            height: 100%; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            position: relative;
        }
        
        .card-dash:hover { 
            border-color: var(--brand-color); 
            box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.08); 
            transform: translateY(-3px);
        }
        
        .badge-ordinal { 
            font-size: 0.8rem; 
            font-weight: 600; 
            background-color: #f8fafc; 
            color: #334155; 
            padding: 6px 14px; 
            border-radius: 8px; 
            border: 1px solid #e2e8f0; 
        }
        
        .card-status-ativa { 
            border-top: 4px solid var(--brand-color); 
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
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            background-color: #ffffff;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-card-action:hover {
            background-color: var(--brand-color);
            border-color: var(--brand-color);
            color: #ffffff !important;
        }

        .dropdown-menu {
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            padding: 6px;
        }
        .dropdown-item {
            border-radius: 8px;
            font-weight: 500;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .modal-header {
            padding: 1.5rem 1.5rem 0.5rem 1.5rem;
        }
        .modal-body {
            padding: 1rem 1.5rem;
        }
        .modal-footer {
            padding: 0.5rem 1.5rem 1.5rem 1.5rem;
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 0.6rem 0.9rem;
        }
        .form-control:focus {
            border-color: var(--brand-color);
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.05);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1.25rem 1rem;
            }
            .header-banner {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body>

<main class="main-content">

    <?php if ($mensagem_sucesso): ?>
        <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div><?= $mensagem_sucesso ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensagem_erro): ?>
        <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= $mensagem_erro ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- CABEÇALHO SUPERIOR -->
    <div class="header-banner mb-4 d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3 w-100 w-md-auto">
            <a href="dashboard-geral.php" class="btn btn-back btn-sm rounded-3 d-flex align-items-center gap-2 px-3 py-2 flex-shrink-0">
                <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline fw-medium">Voltar</span>
            </a>

            <div class="icon-circle" style="background-color: <?= $cor ?>; color: #fff;">
                <?php if ($caminhoLogo): ?>
                    <img src="<?= $caminhoLogo ?>?v=<?= time() ?>" alt="Logo">
                <?php else: ?>
                    <i class="bi <?= $icone ?>"></i>
                <?php endif; ?>
            </div>
            <div class="text-truncate">
                <h3 class="fw-bold m-0 text-dark text-truncate" style="font-size: calc(1.2rem + 0.3vw);"><?= htmlspecialchars($nome) ?></h3>
                <span class="badge-target d-inline-block mt-1">
                    <?= htmlspecialchars($publico_alvo) ?>
                </span>
            </div>
        </div>

        <div class="d-flex gap-2 w-100 w-md-auto justify-content-start justify-content-md-end flex-wrap">
            <button class="btn btn-outline-dark btn-sm rounded-3 d-flex align-items-center gap-2 px-3 py-2 flex-fill flex-md-grow-0 justify-content-center fw-medium" data-bs-toggle="modal" data-bs-target="#modalEditarAcampamento">
                <i class="bi bi-gear-fill"></i>
                <span class="text-nowrap">Editar Informações</span>
            </button>

            <button class="btn btn-dark btn-sm rounded-3 d-flex align-items-center gap-2 px-3 py-2 flex-fill flex-md-grow-0 justify-content-center fw-medium" data-bs-toggle="modal" data-bs-target="#modalNovaEdicao">
                <i class="bi bi-plus-lg"></i>
                <span class="text-nowrap">Nova Edição</span>
            </button>
        </div>
    </div>

    <!-- TÍTULO DAS EDIÇÕES -->
    <div class="d-flex align-items-center justify-content-between mb-4 mt-2">
        <div>
            <h5 class="fw-bold m-0 text-dark">Edições Registradas</h5>
            <p class="text-muted small m-0">Gerencie todas as edições ativas e finalizadas de <?= htmlspecialchars($nome) ?></p>
        </div>
        <span class="badge bg-white text-secondary border px-3 py-2 rounded-3 fs-6 font-monospace fw-normal"><?= $total_edicoes ?> edição(ões)</span>
    </div>

    <!-- CARDS DE EDIÇÃO EXPANSIVOS -->
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
                                <ul class="dropdown-menu dropdown-menu-end border-0">
                                    <li>
                                        <button class="dropdown-item small d-flex align-items-center gap-2 py-2" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#modalConfirmarStatus" 
                                                data-id="<?= $ed['id'] ?>"
                                                data-titulo="<?= htmlspecialchars($tituloCard) ?>"
                                                data-status="<?= $isAtiva ? 'inativar' : 'ativar' ?>">
                                            <i class="bi <?= $isAtiva ? 'bi-toggle-off text-secondary' : 'bi-toggle-on text-dark' ?>"></i> 
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
                        <?= $isAtiva ? 'Gerenciar gestão.' : 'Edição finalizada (Apenas leitura/histórico).' ?>
                    </p>
                </div>

                <a href="edicao-detalhes.php?edicao_id=<?= $ed['id'] ?>&modalidade_id=<?= $modalidade_id ?>" class="btn btn-card-action w-100 rounded-3 btn-sm d-flex align-items-center justify-content-center gap-2 py-2">
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

<!-- MODAL CONFIRMAR MUDANÇA DE STATUS (ATIVA / INATIVA) -->
<div class="modal fade" id="modalConfirmarStatus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="statusModalTitle">Confirmar Alteração</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_toggle_status_edicao" value="1">
                <input type="hidden" name="edicao_id" id="status_edicao_id">
                <input type="hidden" name="novo_status" id="status_novo_val">
                
                <div class="modal-body">
                    <p class="m-0 text-secondary" id="statusModalMessage"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-medium" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark rounded-3 px-4 fw-medium" id="statusModalSubmitBtn">Confirmar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CRIAR NOVA EDIÇÃO -->
<div class="modal fade" id="modalNovaEdicao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Criar Nova Edição</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_criar_edicao" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Nome da Edição</label>
                        <input type="text" name="nome_edicao" class="form-control" placeholder="Ex: <?= ($total_edicoes + 1) ?>° <?= htmlspecialchars($nome) ?>" value="<?= ($total_edicoes + 1) ?>° <?= htmlspecialchars($nome) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Ano de Realização</label>
                        <input type="number" name="ano_edicao" class="form-control" value="<?= date('Y') ?>" min="2000" max="2100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-medium" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark rounded-3 px-4 fw-medium">Criar Edição</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR EDIÇÃO -->
<div class="modal fade" id="modalEditarEdicao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Editar Edição</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_editar_edicao" value="1">
                <input type="hidden" name="edicao_id" id="edit_edicao_id">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Edição</label>
                        <input type="text" name="nome" id="edit_edicao_nome" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Ano de Realização</label>
                        <input type="number" name="ano" id="edit_edicao_ano" class="form-control" min="2000" max="2100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-medium" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark rounded-3 px-4 fw-medium">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EXCLUIR EDIÇÃO -->
<div class="modal fade" id="modalExcluirEdicao" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Excluir Edição</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="acao_excluir_edicao" value="1">
                <input type="hidden" name="edicao_id" id="delete_edicao_id">
                
                <div class="modal-body">
                    <p class="m-0 text-secondary">Tem certeza que deseja excluir permanentemente a <strong id="delete_edicao_titulo" class="text-dark"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light rounded-3 fw-medium" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger rounded-3 px-4 fw-medium">Excluir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR ACAMPAMENTO -->
<div class="modal fade" id="modalEditarAcampamento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Editar Informações</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditarAcampamento" action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="acao_editar_modalidade" value="1">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Nome / Sigla</label>
                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($nome) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Lema</label>
                        <input type="text" name="lema" class="form-control" placeholder="Ex: frase do acampamento" value="<?= htmlspecialchars($lema) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium text-secondary">Público-Alvo</label>
                        <input type="text" name="publico_alvo" class="form-control" value="<?= htmlspecialchars($publico_alvo) ?>">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-medium text-secondary">Logo</label>
                            <input type="file" name="logo" class="form-control" accept="image/*">
                        </div>
                        <div class="col-4">
                            <label class="form-label small fw-medium text-secondary">Cor Tema</label>
                            <input type="color" name="cor" class="form-control form-control-color w-100" value="<?= htmlspecialchars($cor) ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-outline-danger rounded-3 fw-medium" onclick="confirmarExclusaoAcampamento()">
                        <i class="bi bi-trash3-fill me-1"></i> Excluir Acampamento
                    </button>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light rounded-3 fw-medium" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark rounded-3 px-4 fw-medium">Salvar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FORMULÁRIO OCULTO PARA EXCLUSÃO DO ACAMPAMENTO -->
<form id="formExcluirAcampamento" action="" method="POST" style="display: none;">
    <input type="hidden" name="acao_excluir_modalidade" value="1">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function confirmarExclusaoAcampamento() {
    if (confirm("Tem certeza que deseja excluir permanentemente este acampamento? Esta ação não poderá ser desfeita.")) {
        document.getElementById('formExcluirAcampamento').submit();
    }
}

document.addEventListener('DOMContentLoaded', function () {
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
                modalTitle.className = 'modal-title fw-bold text-dark';
                modalMsg.innerHTML = `Tem certeza que deseja marcar a <strong>${titulo}</strong> como <strong>Inativa</strong>?`;
            } else {
                modalTitle.textContent = 'Ativar Edição';
                modalTitle.className = 'modal-title fw-bold text-dark';
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