<?php
// historico-campistas.php
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

// 2. EXCLUSÃO DE PESSOA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'excluir_pessoa') {
    $p_id = intval($_POST['pessoa_id'] ?? 0);
    if ($p_id > 0) {
        $stmt_del = $conn->prepare("DELETE FROM pessoas WHERE id = ?");
        $stmt_del->bind_param("i", $p_id);
        $stmt_del->execute();
        $mensagem_sucesso = "Registro excluído com sucesso!";
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
            $stmt_u = $conn->prepare("UPDATE pessoas SET nome=?, telefone=?, data_nascimento=?, equipe_trabalho_origem=?, campista_origem=?, foto=? WHERE id=?");
            $stmt_u->bind_param("ssssssi", $nome, $telefone, $data_nascimento, $equipe_trabalho_origem, $campista_origem, $foto_path, $pessoa_id);
        } else {
            $stmt_u = $conn->prepare("UPDATE pessoas SET nome=?, telefone=?, data_nascimento=?, equipe_trabalho_origem=?, campista_origem=? WHERE id=?");
            $stmt_u->bind_param("sssssi", $nome, $telefone, $data_nascimento, $equipe_trabalho_origem, $campista_origem,$pessoa_id);
        }
        $stmt_u->execute();
    } else {
        $stmt_i =$conn->prepare("INSERT INTO pessoas (nome, telefone, data_nascimento, equipe_trabalho_origem, campista_origem, foto) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE telefone=VALUES(telefone)");
        $stmt_i->bind_param("ssssss", $nome, $telefone,$data_nascimento, $equipe_trabalho_origem,$campista_origem, $foto_path);$stmt_i->execute();
    }
    $mensagem_sucesso = "Dados salvos no histórico com sucesso!";
}

// 4. PESQUISA CENTRALIZADA (NOME, ACAMPAMENTO/EDIÇÃO, ANO E HISTÓRICO TEXTUAL)
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
    LEFT JOIN pessoas p ON LOWER(p.nome) = LOWER(unificados.nome)
    
    -- Joins para busca em Histórico de Campistas (Edições e Anos)
    LEFT JOIN equipes_membros em ON LOWER(em.nome) = LOWER(unificados.nome)
    LEFT JOIN equipes_campistas ec ON em.equipe_id = ec.id
    LEFT JOIN edicoes ed_camp ON ec.edicao_id = ed_camp.id
    
    -- Joins para busca em Histórico de Equipes de Trabalho (Edições e Anos)
    LEFT JOIN equipe_geral eg ON LOWER(eg.nome) = LOWER(unificados.nome)
    LEFT JOIN equipe_membros em_eq ON eg.id = em_eq.voluntario_id
    LEFT JOIN equipes_trabalho et ON em_eq.equipe_id = et.id
    LEFT JOIN edicoes ed_eq ON et.edicao_id = ed_eq.id
    WHERE 1=1
";

$params = [];$types = "";

if (!empty($busca)) {
    // Permite buscar por vários termos (ex: "sênior 2025") combinando na mesma consulta
    $palavras = explode(' ',$busca);
    foreach ($palavras as$palavra) {
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
            $params[] =$term; $params[] =$term; $params[] =$term;
            $params[] =$term; $params[] =$term; $params[] =$term; $params[] =$term;
            $types .= "sssssss";
        }
    }
}

$sql_unificado .= " GROUP BY unificados.nome ORDER BY unificados.nome ASC";

$stmt = $conn->prepare($sql_unificado);
if (!empty($params)) $stmt->bind_param($types, ...$params);$stmt->execute();
$lista_pessoas =$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Campistas e Servidores</title>
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
        .user-name { color: #ffffff; font-weight: 700; font-size: 0.95rem; line-height: 1.2; }
        .user-role { color: #94A3B8; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-nav-outline-red { color: #EF4444; border: 1px solid #EF4444; background: transparent; padding: 6px 16px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .btn-nav-outline-red:hover { background-color: #EF4444; color: #ffffff; }

        .card-pessoa-item { background: #fff; border-radius: 50px; border: 1px solid #E2E8F0; padding: 10px 20px; transition: all 0.2s; cursor: pointer; }
        .card-pessoa-item:hover { border-color: var(--efata-gold); box-shadow: 0 4px 12px rgba(0,0,0,0.06); transform: translateY(-1px); }
        .avatar-box { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--efata-gold); background-color: #E2E8F0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #64748B; flex-shrink: 0; }
        .avatar-modal { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--efata-gold); }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
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
                <a href="logout.php" class="btn-nav-outline-red">
                    <i class="bi bi-box-arrow-right"></i> Sair
                </a>
            </div>
        </div>
    </nav>

    <!-- CABEÇALHO DA PÁGINA -->
    <div class="bg-white border-bottom py-3 mb-4">
        <div class="container d-flex align-items-center justify-content-between" style="max-width: 900px;">
            <div class="d-flex align-items-center gap-3">
                <?php if ($edicao_id_param > 0): ?>
                    <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="btn btn-sm btn-outline-secondary rounded-circle">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                <?php endif; ?>
                <div>
                    <h5 class="fw-bold m-0 text-dark"><i class="bi bi-journal-bookmark-fill text-warning me-2"></i>Histórico Central de Campistas e Servidores</h5>
                    <small class="text-muted">Consulte o registro acumulado em todas as edições</small>
                </div>
            </div>

            <button class="btn btn-warning rounded-pill px-3 fw-bold text-dark d-flex align-items-center gap-2" onclick="abrirModalNovoMembro()">
                <i class="bi bi-person-plus-fill"></i> + Cadastrar Membro
            </button>
        </div>
    </div>

    <main class="container py-2" style="max-width: 900px;">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- BARRA DE PESQUISA (SUPORTA NOME, ACAMPAMENTO E ANO) -->
        <div class="mb-4">
            <form method="GET" action="historico-campistas.php">
                <input type="hidden" name="edicao_id" value="<?= $edicao_id_param ?>">
                <input type="hidden" name="modalidade_id" value="<?= $modalidade_id_param ?>">
                <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                    <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="busca" class="form-control border-0 ps-1" placeholder="Pesquisar por nome, acampamento ou ano (ex: Sênior 2025)..." value="<?= htmlspecialchars($busca) ?>">
                    <?php if (!empty($busca)): ?>
                        <a href="historico-campistas.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="btn btn-white border-0 d-flex align-items-center"><i class="bi bi-x-lg text-muted"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- LISTA RESUMIDA -->
        <div class="d-flex flex-column gap-3">
            <?php if (empty($lista_pessoas)): ?>
                <div class="text-center py-5 text-muted bg-white rounded-4 border p-4 shadow-sm">
                    <i class="bi bi-person-exclamation display-4 d-block mb-2"></i>
                    Nenhum registo encontrado.
                </div>
            <?php else: ?>
                <?php foreach ($lista_pessoas as$p): ?>
                    <?php
                    // 1. Busca histórico do Campista nas edições do sistema
                    $stmt_c =$conn->prepare("
                        SELECT ed.nome as acampamento, ed.ano, ec.nome as equipe_cor
                        FROM equipes_membros em
                        LEFT JOIN equipes_campistas ec ON em.equipe_id = ec.id
                        LEFT JOIN edicoes ed ON ec.edicao_id = ed.id
                        WHERE LOWER(em.nome) = LOWER(?)
                    ");
                    $stmt_c->bind_param("s", $p['nome']);$stmt_c->execute();
                    $historico_campista =$stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);

                    // 2. Busca histórico da Equipe/Servidor nas edições do sistema
                    $stmt_e =$conn->prepare("
                        SELECT ed.nome as acampamento, ed.ano, et.nome as equipe_nome
                        FROM equipe_membros em
                        JOIN equipe_geral eg ON em.voluntario_id = eg.id
                        JOIN equipes_trabalho et ON em.equipe_id = et.id
                        JOIN edicoes ed ON et.edicao_id = ed.id
                        WHERE LOWER(eg.nome) = LOWER(?)
                    ");
                    $stmt_e->bind_param("s", $p['nome']);$stmt_e->execute();
                    $historico_equipe =$stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);

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

                    <div class="card-pessoa-item d-flex align-items-center justify-content-between" onclick='abrirModalDetalhes(<?= $dados_json ?>)'>
                        <div class="d-flex align-items-center gap-3">
                            <?php if (!empty($p['foto']) && file_exists($p['foto'])): ?>
                                <img src="<?= htmlspecialchars($p['foto']) ?>" class="avatar-box" alt="Foto">
                            <?php else: ?>
                                <div class="avatar-box"><i class="bi bi-person-fill"></i></div>
                            <?php endif; ?>

                            <div>
                                <h6 class="fw-bold m-0 text-dark"><?= htmlspecialchars($p['nome']) ?></h6>
                                <small class="text-muted"><i class="bi bi-telephone me-1"></i><?= !empty($p['telefone']) ? htmlspecialchars($p['telefone']) : 'Sem telefone' ?></small>
                            </div>
                        </div>
                        <i class="bi bi-chevron-right text-muted me-2"></i>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>

    <!-- MODAL DE VISUALIZAÇÃO DOS DETALHES -->
    <div class="modal fade" id="modalDetalhes" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow">
                <div class="modal-body p-4">
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
                    <form method="POST" action="historico-campistas.php" onsubmit="return confirm('Tem certeza que deseja excluir este registo?');">
                        <input type="hidden" name="acao" value="excluir_pessoa">
                        <input type="hidden" name="pessoa_id" id="modal_excluir_id">
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
                <form action="historico-campistas.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="salvar_pessoa">
                    <input type="hidden" name="pessoa_id" id="edt_pessoa_id">

                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome Completo *</label>
                            <input type="text" name="nome" id="edt_nome" class="form-control" required placeholder="Ex: João da Silva">
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
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">Salvar Registros</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let pessoaAtual = null;

        function abrirModalDetalhes(dados) {
            pessoaAtual = dados;

            document.getElementById('modal_nome').innerText = dados.nome;
            document.getElementById('modal_telefone').innerText = dados.telefone;
            document.getElementById('modal_data_nasc').innerText = dados.data_nascimento;
            document.getElementById('modal_excluir_id').value = dados.pessoa_id || 0;

            const fotoContainer = document.getElementById('modal_foto_container');
            if (dados.foto) {
                fotoContainer.innerHTML = `<img src="${dados.foto}" class="avatar-modal" alt="Foto">`;
            } else {
                fotoContainer.innerHTML = `<div class="avatar-modal bg-light d-flex align-items-center justify-content-center fs-2 text-secondary"><i class="bi bi-person-fill"></i></div>`;
            }

            // Histórico Campista
            const containerCampista = document.getElementById('modal_campista_conteudo');
            let htmlCampista = '';

            if (dados.campista_origem) {
                const linhas = dados.campista_origem.split(/\r?\n/);
                linhas.forEach(linha => {
                    if (linha.trim() !== '') htmlCampista += `<div class="fw-semibold text-dark mb-1">• ${linha.trim()}</div>`;
                });
            }

            if (dados.historico_campista && dados.historico_campista.length > 0) {
                dados.historico_campista.forEach(c => {
                    htmlCampista += `<div class="fw-semibold text-dark mb-1">• ${c.acampamento || 'Acampamento'} (${c.ano || '-'}) ${c.equipe_cor ? '- '+c.equipe_cor : ''}</div>`;
                });
            }
            containerCampista.innerHTML = htmlCampista || '<em>Nenhum registo como campista.</em>';

            // Histórico Equipe
            const containerEquipe = document.getElementById('modal_equipe_conteudo');
            let htmlEquipe = '';

            if (dados.equipe_trabalho_origem) {
                const linhasEq = dados.equipe_trabalho_origem.split(/\r?\n/);
                linhasEq.forEach(linha => {
                    if (linha.trim() !== '') htmlEquipe += `<div class="fw-semibold text-dark mb-1">• ${linha.trim()}</div>`;
                });
            }

            if (dados.historico_equipe && dados.historico_equipe.length > 0) {
                dados.historico_equipe.forEach(e => {
                    htmlEquipe += `<div class="fw-semibold text-dark mb-1">• ${e.acampamento || ''} ${e.ano || ''} - <strong>${e.equipe_nome || 'Apoio'}</strong></div>`;
                });
            }
            containerEquipe.innerHTML = htmlEquipe || '<em>Nenhum registo em equipa de trabalho.</em>';

            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        }

        function abrirModalNovoMembro() {
            pessoaAtual = null;
            document.getElementById('titulo_modal_membro').innerText = 'Cadastrar Novo Membro Anterior';
            document.getElementById('edt_pessoa_id').value = '0';
            document.getElementById('edt_nome').value = '';
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
            document.getElementById('edt_nome').value = pessoaAtual.nome || '';
            document.getElementById('edt_telefone').value = pessoaAtual.telefone !== 'Não informado' ? pessoaAtual.telefone : '';
            document.getElementById('edt_data_nascimento').value = pessoaAtual.data_nascimento_raw || '';

            // Consolida histórico automático do BD + histórico salvo em texto (Campistas)
            let listaCampistas = [];
            if (pessoaAtual.historico_campista && pessoaAtual.historico_campista.length > 0) {
                pessoaAtual.historico_campista.forEach(c => {
                    let txt = `${c.acampamento || 'Acampamento'} ${c.ano || ''}`;
                    if (c.equipe_cor) txt += ` - ${c.equipe_cor}`;
                    listaCampistas.push(txt.trim());
                });
            }
            if (pessoaAtual.campista_origem) {
                pessoaAtual.campista_origem.split(/\r?\n/).forEach(l => {
                    if (l.trim() !== '' && !listaCampistas.includes(l.trim())) {
                        listaCampistas.push(l.trim());
                    }
                });
            }
            document.getElementById('edt_campista_origem').value = listaCampistas.join('\n');

            // Consolida histórico automático do BD + histórico salvo em texto (Equipe de Trabalho)
            let listaEquipes = [];
            if (pessoaAtual.historico_equipe && pessoaAtual.historico_equipe.length > 0) {
                pessoaAtual.historico_equipe.forEach(e => {
                    let txt = `${e.acampamento || ''} ${e.ano || ''} - ${e.equipe_nome || 'Apoio'}`;
                    listaEquipes.push(txt.trim());
                });
            }
            if (pessoaAtual.equipe_trabalho_origem) {
                pessoaAtual.equipe_trabalho_origem.split(/\r?\n/).forEach(l => {
                    if (l.trim() !== '' && !listaEquipes.includes(l.trim())) {
                        listaEquipes.push(l.trim());
                    }
                });
            }
            document.getElementById('edt_equipe_trabalho_origem').value = listaEquipes.join('\n');

            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }
    </script>
</body>
</html>