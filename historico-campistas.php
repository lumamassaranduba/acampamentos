<?php
// historico-campistas.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil       = strtoupper(trim($_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL'));

$edicao_id_param = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : (isset($_POST['edicao_id']) ? intval($_POST['edicao_id']) : 0);
$modalidade_id_param = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : (isset($_POST['modalidade_id']) ? intval($_POST['modalidade_id']) : 0);

$mensagem_sucesso = '';
$mensagem_erro = '';

// 2. EXCLUSÃO DE PESSOA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_pessoa') {
    $p_id = intval($_POST['pessoa_id'] ?? 0);
    
    if ($p_id > 0) {
        $stmt_p = $conn->prepare("SELECT nome FROM pessoas WHERE id = ?");
        $stmt_p->bind_param("i", $p_id);
        $stmt_p->execute();
        $res_p = $stmt_p->get_result()->fetch_assoc();
        $nome_pessoa = $res_p['nome'] ?? '';

        if (!empty($nome_pessoa)) {
            $stmt_c = $conn->prepare("SELECT COUNT(*) as total FROM equipes_membros WHERE LOWER(TRIM(nome)) = LOWER(TRIM(?))");
            $stmt_c->bind_param("s", $nome_pessoa);
            $stmt_c->execute();
            $total_camp = $stmt_c->get_result()->fetch_assoc()['total'] ?? 0;

            $stmt_e = $conn->prepare("
                SELECT COUNT(*) as total 
                FROM equipe_membros em 
                JOIN equipe_geral eg ON em.voluntario_id = eg.id 
                WHERE LOWER(TRIM(eg.nome)) = LOWER(TRIM(?))
            ");
            $stmt_e->bind_param("s", $nome_pessoa);
            $stmt_e->execute();
            $total_serv = $stmt_e->get_result()->fetch_assoc()['total'] ?? 0;

            if ($total_camp > 0 || $total_serv > 0) {
                $mensagem_erro = "Não foi possível excluir <strong>" . htmlspecialchars($nome_pessoa) . "</strong> pois possui vínculos ativos.";
            } else {
                $conn->query("DELETE FROM equipe_geral WHERE LOWER(TRIM(nome)) = LOWER(TRIM('" . $conn->real_escape_string($nome_pessoa) . "'))");
                
                $stmt_del = $conn->prepare("DELETE FROM pessoas WHERE id = ?");
                $stmt_del->bind_param("i", $p_id);
                $stmt_del->execute();
                
                $mensagem_sucesso = "Registro de <strong>" . htmlspecialchars($nome_pessoa) . "</strong> excluído com sucesso!";
            }
        } else {
            $mensagem_erro = "Registro não encontrado.";
        }
    }
}

// 3. SALVAR / EDITAR PESSOA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_pessoa') {
    $pessoa_id = intval($_POST['pessoa_id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $data_nascimento = !empty($_POST['data_nascimento']) ? $_POST['data_nascimento'] : NULL;
    $equipe_trabalho_origem = trim($_POST['equipe_trabalho_origem'] ?? '');
    $campista_origem = trim($_POST['campista_origem'] ?? '');

    $foto_path = NULL;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        $novo_nome = 'perfil_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($extensao);
        $diretorio_destino = 'uploads/perfis/';
        if (!is_dir($diretorio_destino)) mkdir($diretorio_destino, 0777, true);
        if (move_uploaded_file($_FILES['foto']['tmp_name'], $diretorio_destino . $novo_nome)) {
            $foto_path = $diretorio_destino . $novo_nome;
        }
    }

    if ($pessoa_id > 0) {
        if ($foto_path) {
            $stmt_u = $conn->prepare("UPDATE pessoas SET telefone=?, data_nascimento=?, equipe_trabalho_origem=?, campista_origem=?, foto=? WHERE id=?");
            $stmt_u->bind_param("sssssi", $telefone, $data_nascimento, $equipe_trabalho_origem, $campista_origem, $foto_path, $pessoa_id);
        } else {
            $stmt_u = $conn->prepare("UPDATE pessoas SET telefone=?, data_nascimento=?, equipe_trabalho_origem=?, campista_origem=? WHERE id=?");
            $stmt_u->bind_param("ssssi", $telefone, $data_nascimento, $equipe_trabalho_origem, $campista_origem, $pessoa_id);
        }
        $stmt_u->execute();
    } else {
        $stmt_i = $conn->prepare("INSERT INTO pessoas (nome, telefone, data_nascimento, equipe_trabalho_origem, campista_origem, foto) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE telefone=VALUES(telefone)");
        $stmt_i->bind_param("ssssss", $nome, $telefone, $data_nascimento, $equipe_trabalho_origem, $campista_origem, $foto_path);
        $stmt_i->execute();
    }
    $mensagem_sucesso = "Dados salvos no histórico com sucesso!";
}

// 4. PESQUISA CENTRALIZADA
$busca = trim($_GET['busca'] ?? '');

$sql_unificado = "
    SELECT 
        p.id AS pessoa_id,
        unificados.nome AS nome,
        p.telefone,
        p.data_nascimento,
        p.equipe_trabalho_origem,
        p.campista_origem,
        p.foto
    FROM (
        SELECT nome FROM equipes_membros WHERE nome IS NOT NULL AND nome != ''
        UNION
        SELECT nome FROM equipe_geral WHERE nome IS NOT NULL AND nome != ''
        UNION
        SELECT nome FROM pessoas WHERE nome IS NOT NULL AND nome != ''
    ) AS unificados
    LEFT JOIN pessoas p ON LOWER(TRIM(p.nome)) = LOWER(TRIM(unificados.nome))
    
    LEFT JOIN equipes_membros em ON LOWER(TRIM(em.nome)) = LOWER(TRIM(unificados.nome))
    LEFT JOIN equipes_campistas ec ON em.equipe_id = ec.id
    LEFT JOIN edicoes ed_camp ON ec.edicao_id = ed_camp.id
    
    LEFT JOIN equipe_geral eg ON LOWER(TRIM(eg.nome)) = LOWER(TRIM(unificados.nome))
    LEFT JOIN equipe_membros em_eq ON eg.id = em_eq.voluntario_id
    LEFT JOIN equipes_trabalho et ON em_eq.equipe_id = et.id
    LEFT JOIN edicoes ed_eq ON et.edicao_id = ed_eq.id
    WHERE 1=1
";

$params = [];
$types = "";

if (!empty($busca)) {
    $palavras = explode(' ', $busca);
    foreach ($palavras as $palavra) {
        $palavra = trim($palavra);
        if ($palavra !== '') {
            $term = "%{$palavra}%";
            $sql_unificado .= " AND (
                unificados.nome LIKE ? 
                OR p.campista_origem LIKE ? 
                OR p.equipe_trabalho_origem LIKE ? 
                OR ed_camp.nome LIKE ? 
                OR CAST(ed_camp.ano AS CHAR) LIKE ? 
                OR ed_eq.nome LIKE ? 
                OR CAST(ed_eq.ano AS CHAR) LIKE ?
            )";
            $params[] = $term; $params[] = $term; $params[] = $term;
            $params[] = $term; $params[] = $term; $params[] = $term; $params[] = $term;
            $types .= "sssssss";
        }
    }
}

$sql_unificado .= " GROUP BY unificados.nome ORDER BY unificados.nome ASC";

$stmt = $conn->prepare($sql_unificado);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$lista_pessoas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$query_contexto = "edicao_id={$edicao_id_param}&modalidade_id={$modalidade_id_param}";
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Campistas e Servidores</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { 
            --navy-dark: #0A192F; 
            --gold-primary: #C5A059; 
            --gold-hover: #B28D46;
            --bg-main: #F8FAFC; 
        }

        body { font-family: 'Outfit', sans-serif; background-color: var(--bg-main); margin: 0; padding: 0; }
        
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

        .btn-only-arrow {
            color: #475569;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 8px;
            border-radius: 6px;
            transition: color 0.2s ease, background-color 0.2s ease;
            border: none;
            background: transparent;
        }

        .btn-only-arrow:hover {
            color: var(--navy-dark);
            background-color: rgba(0, 0, 0, 0.05);
        }

        .search-box-custom {
            background: #ffffff;
            border: 1.5px solid #E2E8F0;
            border-radius: 50px;
            padding: 4px 16px;
            transition: all 0.2s ease;
        }

        .search-box-custom:focus-within {
            border-color: var(--gold-primary);
            box-shadow: 0 4px 14px rgba(197, 160, 89, 0.15);
        }

        .search-input-custom {
            font-size: 0.92rem;
            padding: 6px 8px;
        }

        .search-input-custom:focus { box-shadow: none !important; }

        .card-pessoa-compacto {
            background: #ffffff;
            border-radius: 18px;
            border: 1.5px solid #E2E8F0;
            padding: 14px 16px;
            height: 100%;
            transition: all 0.2s ease;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            overflow: hidden;
        }

        .card-pessoa-compacto:hover {
            border-color: var(--gold-primary);
            box-shadow: 0 6px 18px rgba(10, 25, 47, 0.09);
            transform: translateY(-2px);
        }

        .avatar-box-sm {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--gold-primary);
            background-color: #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: #64748B;
            flex-shrink: 0;
        }

        .pessoa-nome-text {
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--navy-dark);
            margin: 0;
            line-height: 1.25;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        .pessoa-tel-text {
            font-size: 0.78rem;
            color: #64748B;
            margin: 2px 0 0 0;
            white-space: nowrap;
        }

        .avatar-modal { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--gold-primary); }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
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
        <div class="container d-flex align-items-center justify-content-between" style="max-width: 1280px;">
            <div class="d-flex align-items-center gap-2">
                <?php if ($edicao_id_param > 0): ?>
                    <a href="edicao-detalhes.php?<?= $query_contexto ?>" class="btn-only-arrow me-1" title="Voltar">
                        <i class="bi bi-arrow-left fs-4"></i>
                    </a>
                <?php else: ?>
                    <button type="button" onclick="window.history.back()" class="btn-only-arrow me-1" title="Voltar">
                        <i class="bi bi-arrow-left fs-4"></i>
                    </button>
                <?php endif; ?>

                <div>
                    <h5 class="fw-bold m-0 text-dark"><i class="bi bi-journal-bookmark-fill me-2" style="color: var(--gold-primary);"></i>Histórico de Campistas e Servos</h5>
                </div>
            </div>

            <button class="btn btn-outline-gold rounded-pill px-3 py-2 btn-sm d-flex align-items-center gap-2" onclick="abrirModalNovoMembro()">
                <i class="bi bi-person-plus-fill"></i> Cadastrar Membro
            </button>
        </div>
    </div>

    <main class="container py-2" style="max-width: 1280px;">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4"><?= $mensagem_erro ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- BARRA DE PESQUISA -->
        <div class="mb-4">
            <form method="GET" action="historico-campistas.php" autocomplete="off">
                <input type="hidden" name="edicao_id" value="<?= $edicao_id_param ?>">
                <input type="hidden" name="modalidade_id" value="<?= $modalidade_id_param ?>">
                
                <div class="search-box-custom d-flex align-items-center shadow-sm">
                    <i class="bi bi-search text-muted fs-6 ms-2 me-2"></i>
                    <input type="text" name="busca" class="form-control border-0 bg-transparent search-input-custom" placeholder="Pesquisar por nome, acampamento ou ano (ex: Sênior 2025)..." value="<?= htmlspecialchars($busca) ?>" autocomplete="off">
                    <?php if (!empty($busca)): ?>
                        <a href="historico-campistas.php?<?= $query_contexto ?>" class="btn p-0 border-0 text-muted ms-2 me-1" title="Limpar pesquisa">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- GRADE DE CARDS -->
        <?php if (empty($lista_pessoas)): ?>
            <div class="text-center py-5 text-muted bg-white rounded-4 border p-4 shadow-sm">
                <i class="bi bi-person-exclamation display-4 d-block mb-2"></i>
                Nenhum registo encontrado.
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($lista_pessoas as $p): ?>
                    <?php
                    // 1. Busca o registro mais recente em cada edição como Campista
                    $stmt_c = $conn->prepare("
                        SELECT ed.nome as acampamento, ed.ano, ec.nome as equipe_cor, ed.id as edicao_id
                        FROM equipes_membros em
                        LEFT JOIN equipes_campistas ec ON em.equipe_id = ec.id
                        LEFT JOIN edicoes ed ON ec.edicao_id = ed.id
                        WHERE LOWER(TRIM(em.nome)) = LOWER(TRIM(?))
                        AND em.id IN (
                            SELECT MAX(em_sub.id)
                            FROM equipes_membros em_sub
                            LEFT JOIN equipes_campistas ec_sub ON em_sub.equipe_id = ec_sub.id
                            WHERE LOWER(TRIM(em_sub.nome)) = LOWER(TRIM(?))
                            GROUP BY ec_sub.edicao_id
                        )
                        ORDER BY ed.ano DESC
                    ");
                    $stmt_c->bind_param("ss", $p['nome'], $p['nome']);
                    $stmt_c->execute();
                    $historico_campista = $stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);

                    // 2. Busca histórico em Equipes de Trabalho
                    $stmt_e = $conn->prepare("
                        SELECT ed.nome as acampamento, ed.ano, et.nome as equipe_nome, ed.id as edicao_id
                        FROM equipe_membros em
                        JOIN equipe_geral eg ON em.voluntario_id = eg.id
                        JOIN equipes_trabalho et ON em.equipe_id = et.id
                        JOIN edicoes ed ON et.edicao_id = ed.id
                        WHERE LOWER(TRIM(eg.nome)) = LOWER(TRIM(?))
                        AND em.id IN (
                            SELECT MAX(em_sub.id)
                            FROM equipe_membros em_sub
                            JOIN equipe_geral eg_sub ON em_sub.voluntario_id = eg_sub.id
                            JOIN equipes_trabalho et_sub ON em_sub.equipe_id = et_sub.id
                            WHERE LOWER(TRIM(eg_sub.nome)) = LOWER(TRIM(?))
                            GROUP BY et_sub.edicao_id
                        )
                        ORDER BY ed.ano DESC
                    ");
                    $stmt_e->bind_param("ss", $p['nome'], $p['nome']);
                    $stmt_e->execute();
                    $historico_equipe = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

                    $dados_json = htmlspecialchars(json_encode([
                        'pessoa_id' => $p['pessoa_id'],
                        'nome' => $p['nome'],
                        'telefone' => $p['telefone'] ?? 'Não informado',
                        'data_nascimento' => !empty($p['data_nascimento']) ? date('d/m/Y', strtotime($p['data_nascimento'])) : 'Não informada',
                        'data_nascimento_raw' => $p['data_nascimento'] ?? '',
                        'equipe_trabalho_origem' => $p['equipe_trabalho_origem'] ?? '',
                        'campista_origem' => $p['campista_origem'] ?? '',
                        'foto' => $p['foto'],
                        'historico_campista' => $historico_campista,
                        'historico_equipe' => $historico_equipe
                    ]), ENT_QUOTES, 'UTF-8');
                    ?>

                    <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                        <div class="card-pessoa-compacto shadow-sm" onclick='abrirModalDetalhes(<?= $dados_json ?>)'>
                            <?php if (!empty($p['foto']) && file_exists($p['foto'])): ?>
                                <img src="<?= htmlspecialchars($p['foto']) ?>" class="avatar-box-sm" alt="Foto">
                            <?php else: ?>
                                <div class="avatar-box-sm"><i class="bi bi-person-fill"></i></div>
                            <?php endif; ?>

                            <div class="min-w-0 flex-grow-1">
                                <h6 class="pessoa-nome-text"><?= htmlspecialchars($p['nome']) ?></h6>
                                <p class="pessoa-tel-text">
                                    <i class="bi bi-telephone me-1 opacity-75"></i><?= !empty($p['telefone']) ? htmlspecialchars($p['telefone']) : 'Sem telefone' ?>
                                </p>
                            </div>

                            <i class="bi bi-chevron-right text-muted small ms-auto opacity-50 flex-shrink-0"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <!-- MODAL DE VISUALIZAÇÃO DOS DETALHES -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-body p-4">

                    <!-- ALERTA DE ERRO DENTRO DO MODAL -->
                    <div id="modal_alerta_erro" class="alert alert-danger border-0 shadow-sm rounded-3 mb-3 d-none">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-exclamation-triangle-fill fs-5 text-danger flex-shrink-0"></i>
                            <div class="small fw-semibold" id="modal_texto_erro"></div>
                        </div>
                    </div>

                    <div class="d-flex align-items-start gap-3 mb-4">
                        <div id="modal_foto_container"></div>
                        <div class="flex-grow-1">
                            <small class="text-muted d-block">Nome:</small>
                            <h5 class="fw-bold text-dark m-0" id="modal_nome"></h5>
                            
                            <div class="row mt-2">
                                <div class="col-6">
                                    <small class="text-muted d-block">Telefone:</small>
                                    <span class="fw-semibold small" id="modal_telefone"></span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted d-block">Data Nasc:</small>
                                    <span class="fw-semibold small" id="modal_data_nasc"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <h6 class="fw-bold text-uppercase text-primary small mb-2"><i class="bi bi-person-badge me-1"></i> HISTÓRICO COMO CAMPISTA</h6>
                        <div id="modal_campista_conteudo" class="ps-1 small text-muted"></div>
                    </div>

                    <hr class="my-3">

                    <div class="mb-3">
                        <h6 class="fw-bold text-uppercase text-success small mb-2"><i class="bi bi-people-fill me-1"></i> HISTÓRICO EM EQUIPE DE TRABALHO</h6>
                        <div id="modal_equipe_conteudo" class="ps-1 small text-muted"></div>
                    </div>
                </div>

                <div class="modal-footer bg-light d-flex justify-content-between rounded-bottom-4">
                    <form id="form_excluir_pessoa" method="POST" action="historico-campistas.php?<?= $query_contexto ?>" onsubmit="return validarExclusao(event)">
                        <input type="hidden" name="acao" value="excluir_pessoa">
                        <input type="hidden" name="pessoa_id" id="modal_excluir_id">
                        <input type="hidden" name="edicao_id" value="<?= $edicao_id_param ?>">
                        <input type="hidden" name="modalidade_id" value="<?= $modalidade_id_param ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3"><i class="bi bi-trash me-1"></i> Excluir</button>
                    </form>

                    <button type="button" class="btn btn-dark btn-sm rounded-pill px-4" onclick="abrirModalEdicao()"><i class="bi bi-pencil me-1"></i> Editar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE EDIÇÃO / CADASTRO -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="titulo_modal_membro">Membro / Servidor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="historico-campistas.php?<?= $query_contexto ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="salvar_pessoa">
                    <input type="hidden" name="pessoa_id" id="edt_pessoa_id">
                    <input type="hidden" name="nome" id="edt_nome_hidden">
                    <input type="hidden" name="edicao_id" value="<?= $edicao_id_param ?>">
                    <input type="hidden" name="modalidade_id" value="<?= $modalidade_id_param ?>">

                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome Completo *</label>
                            <input type="text" id="edt_nome" class="form-control" required placeholder="Ex: João da Silva">
                            <small id="hint_nome_readonly" class="text-muted d-none mt-1 d-block"><i class="bi bi-info-circle me-1"></i>O nome está associado às edições e não pode ser modificado.</small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Telefone / WhatsApp</label>
                                <input type="text" name="telefone" id="edt_telefone" class="form-control" placeholder="(18) 99999-9999">
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold">Data de Nascimento</label>
                                <input type="date" name="data_nascimento" id="edt_data_nascimento" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Foto de Perfil</label>
                            <input type="file" name="foto" class="form-control" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Histórico Anterior como Campista <small class="text-muted fw-normal">(Uma linha por evento)</small></label>
                            <textarea name="campista_origem" id="edt_campista_origem" class="form-control" rows="3" placeholder="FAC 2024&#10;JOAM 2025"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Histórico Anterior em Equipes de Trabalho <small class="text-muted fw-normal">(Uma linha por evento)</small></label>
                            <textarea name="equipe_trabalho_origem" id="edt_equipe_trabalho_origem" class="form-control" rows="3" placeholder="Sênior 2025 - Cozinha&#10;FAC 2026 - Apoio"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold rounded-pill px-4">Salvar Registros</button>
                    </div>
                </form>
            </div>
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
        let pessoaAtual = null;

        function normalizarTexto(txt) {
            if (!txt) return '';
            return txt.toLowerCase()
                      .replace(/[\(\)\-\s]/g, '')
                      .normalize("NFD")
                      .replace(/[\u0300-\u036f]/g, "");
        }

        // Extrai o nome/ano base do acampamento (ex: "1 pac 2026")
        function extrairBaseAcampamento(txt) {
            if (!txt) return '';
            let norm = normalizarTexto(txt);
            let match = norm.match(/\d+pac\d+/);
            return match ? match[0] : norm;
        }

        function abrirModalDetalhes(dados) {
            pessoaAtual = dados;

            document.getElementById('modal_nome').innerText = dados.nome;
            document.getElementById('modal_telefone').innerText = dados.telefone;
            document.getElementById('modal_data_nasc').innerText = dados.data_nascimento;
            document.getElementById('modal_excluir_id').value = dados.pessoa_id || 0;

            const alertaErro = document.getElementById('modal_alerta_erro');
            alertaErro.classList.add('d-none');
            document.getElementById('modal_texto_erro').innerHTML = '';

            const fotoContainer = document.getElementById('modal_foto_container');
            if (dados.foto) {
                fotoContainer.innerHTML = `<img src="${dados.foto}" class="avatar-modal" alt="Foto">`;
            } else {
                fotoContainer.innerHTML = `<div class="avatar-modal bg-light d-flex align-items-center justify-content-center fs-2 text-secondary"><i class="bi bi-person-fill"></i></div>`;
            }

            // --- HISTÓRICO CAMPISTA ---
            const containerCampista = document.getElementById('modal_campista_conteudo');
            let htmlCampista = '';
            let edicoesOficiaisCampista = [];

            if (dados.historico_campista && dados.historico_campista.length > 0) {
                dados.historico_campista.forEach(c => {
                    let txt = `${c.acampamento || 'Acampamento'} ${c.ano || ''} ${c.equipe_cor ? '- '+c.equipe_cor : ''}`.trim();
                    htmlCampista += `<div class="fw-semibold text-dark mb-1">• ${txt}</div>`;
                    edicoesOficiaisCampista.push(extrairBaseAcampamento(txt));
                });
            }

            if (dados.campista_origem) {
                const linhas = dados.campista_origem.split(/\r?\n/);
                linhas.forEach(linha => {
                    let lTrim = linha.trim();
                    if (lTrim !== '') {
                        let baseManual = extrairBaseAcampamento(lTrim);
                        // Exibe texto manual apenas se NÃO pertencer a um acampamento oficial já listado acima
                        let jaExisteOficial = edicoesOficiaisCampista.some(baseOficial => baseOficial !== '' && baseManual.includes(baseOficial));
                        if (!jaExisteOficial) {
                            htmlCampista += `<div class="fw-semibold text-dark mb-1">• ${lTrim}</div>`;
                        }
                    }
                });
            }

            containerCampista.innerHTML = htmlCampista || '<em>Nenhum registo como campista.</em>';

            // --- HISTÓRICO EQUIPE DE TRABALHO ---
            const containerEquipe = document.getElementById('modal_equipe_conteudo');
            let htmlEquipe = '';
            let edicoesOficiaisEquipe = [];

            if (dados.historico_equipe && dados.historico_equipe.length > 0) {
                dados.historico_equipe.forEach(e => {
                    let txt = `${e.acampamento || ''} ${e.ano || ''} - ${e.equipe_nome || 'Apoio'}`.trim();
                    htmlEquipe += `<div class="fw-semibold text-dark mb-1">• ${txt}</div>`;
                    edicoesOficiaisEquipe.push(extrairBaseAcampamento(txt));
                });
            }

            if (dados.equipe_trabalho_origem) {
                const linhasEq = dados.equipe_trabalho_origem.split(/\r?\n/);
                linhasEq.forEach(linha => {
                    let lTrim = linha.trim();
                    if (lTrim !== '') {
                        let baseManual = extrairBaseAcampamento(lTrim);
                        let jaExisteOficial = edicoesOficiaisEquipe.some(baseOficial => baseOficial !== '' && baseManual.includes(baseOficial));
                        if (!jaExisteOficial) {
                            htmlEquipe += `<div class="fw-semibold text-dark mb-1">• ${lTrim}</div>`;
                        }
                    }
                });
            }

            containerEquipe.innerHTML = htmlEquipe || '<em>Nenhum registo em equipa de trabalho.</em>';

            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        }

        function validarExclusao(event) {
            if (!pessoaAtual) return true;

            const temCampista = (pessoaAtual.historico_campista && pessoaAtual.historico_campista.length > 0) || (pessoaAtual.campista_origem && pessoaAtual.campista_origem.trim() !== '');
            const temEquipe = (pessoaAtual.historico_equipe && pessoaAtual.historico_equipe.length > 0) || (pessoaAtual.equipe_trabalho_origem && pessoaAtual.equipe_trabalho_origem.trim() !== '');

            if (temCampista || temEquipe) {
                event.preventDefault();
                
                const alertaErro = document.getElementById('modal_alerta_erro');
                document.getElementById('modal_texto_erro').innerHTML = `Não é possível excluir <strong>${pessoaAtual.nome}</strong> pois possui vínculo ativo com acampamento(s) ou equipe(s) de trabalho registrados.`;
                alertaErro.classList.remove('d-none');
                
                return false;
            }

            return confirm('Tem certeza que deseja excluir este registo?');
        }

        function abrirModalNovoMembro() {
            pessoaAtual = null;
            document.getElementById('titulo_modal_membro').innerText = 'Cadastrar Novo Membro Anterior';
            document.getElementById('edt_pessoa_id').value = '0';
            
            const inputNome = document.getElementById('edt_nome');
            inputNome.value = '';
            inputNome.removeAttribute('readonly');
            inputNome.setAttribute('name', 'nome');
            inputNome.style.backgroundColor = '#ffffff';
            document.getElementById('edt_nome_hidden').value = '';
            document.getElementById('hint_nome_readonly').classList.add('d-none');

            document.getElementById('edt_telefone').value = '';
            document.getElementById('edt_data_nascimento').value = '';
            document.getElementById('edt_campista_origem').value = '';
            document.getElementById('edt_equipe_trabalho_origem').value = '';

            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }

        function abrirModalEdicao() {
            if (!pessoaAtual) return;

            bootstrap.Modal.getInstance(document.getElementById('modalDetalhes')).hide();

            document.getElementById('titulo_modal_membro').innerText = 'Editar Informações';
            document.getElementById('edt_pessoa_id').value = pessoaAtual.pessoa_id || 0;
            
            const inputNome = document.getElementById('edt_nome');
            inputNome.value = pessoaAtual.nome || '';
            inputNome.setAttribute('readonly', 'readonly');
            inputNome.removeAttribute('name');
            inputNome.style.backgroundColor = '#e9ecef';
            
            document.getElementById('edt_nome_hidden').value = pessoaAtual.nome || '';
            document.getElementById('hint_nome_readonly').classList.remove('d-none');

            document.getElementById('edt_telefone').value = pessoaAtual.telefone !== 'Não informado' ? pessoaAtual.telefone : '';
            document.getElementById('edt_data_nascimento').value = pessoaAtual.data_nascimento_raw || '';

            // Filtra linhas manuais que sejam de acampamentos oficiais atuais
            let edicoesOficiaisCampista = [];
            if (pessoaAtual.historico_campista && pessoaAtual.historico_campista.length > 0) {
                pessoaAtual.historico_campista.forEach(c => {
                    edicoesOficiaisCampista.push(extrairBaseAcampamento(`${c.acampamento} ${c.ano}`));
                });
            }

            let linhasManualCampista = [];
            if (pessoaAtual.campista_origem) {
                pessoaAtual.campista_origem.split(/\r?\n/).forEach(l => {
                    let baseManual = extrairBaseAcampamento(l.trim());
                    if (l.trim() !== '' && !edicoesOficiaisCampista.some(baseOficial => baseOficial !== '' && baseManual.includes(baseOficial))) {
                        linhasManualCampista.push(l.trim());
                    }
                });
            }
            document.getElementById('edt_campista_origem').value = linhasManualCampista.join('\n');

            let edicoesOficiaisEquipe = [];
            if (pessoaAtual.historico_equipe && pessoaAtual.historico_equipe.length > 0) {
                pessoaAtual.historico_equipe.forEach(e => {
                    edicoesOficiaisEquipe.push(extrairBaseAcampamento(`${e.acampamento} ${e.ano}`));
                });
            }

            let linhasManualEquipe = [];
            if (pessoaAtual.equipe_trabalho_origem) {
                pessoaAtual.equipe_trabalho_origem.split(/\r?\n/).forEach(l => {
                    let baseManual = extrairBaseAcampamento(l.trim());
                    if (l.trim() !== '' && !edicoesOficiaisEquipe.some(baseOficial => baseOficial !== '' && baseManual.includes(baseOficial))) {
                        linhasManualEquipe.push(l.trim());
                    }
                });
            }
            document.getElementById('edt_equipe_trabalho_origem').value = linhasManualEquipe.join('\n');

            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }
    </script>
</body>
</html>