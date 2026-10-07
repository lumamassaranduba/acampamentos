<?php
// historico-campistas.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id_param = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id_param = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

$mensagem_sucesso = '';
$mensagem_erro = '';

// 1. Garantir estrutura da tabela 'pessoas'
$conn->query("
    CREATE TABLE IF NOT EXISTS `pessoas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nome` VARCHAR(255) NOT NULL,
        `telefone` VARCHAR(20) DEFAULT NULL,
        `data_nascimento` DATE DEFAULT NULL,
        `equipe_trabalho_origem` TEXT DEFAULT NULL,
        `campista_origem` TEXT DEFAULT NULL,
        `foto` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Adicionar colunas ou alterar tipo se necessário
$chk_c = $conn->query("SHOW COLUMNS FROM `pessoas` LIKE 'campista_origem'");
if ($chk_c && $chk_c->num_rows == 0) {
    $conn->query("ALTER TABLE `pessoas` ADD `campista_origem` TEXT DEFAULT NULL");
} else {
    $conn->query("ALTER TABLE `pessoas` MODIFY `campista_origem` TEXT DEFAULT NULL");
}

$chk_eq = $conn->query("SHOW COLUMNS FROM `pessoas` LIKE 'equipe_trabalho_origem'");
if ($chk_eq && $chk_eq->num_rows == 0) {
    $conn->query("ALTER TABLE `pessoas` ADD `equipe_trabalho_origem` TEXT DEFAULT NULL");
} else {
    $conn->query("ALTER TABLE `pessoas` MODIFY `equipe_trabalho_origem` TEXT DEFAULT NULL");
}

$chk_f = $conn->query("SHOW COLUMNS FROM `pessoas` LIKE 'foto'");
if ($chk_f && $chk_f->num_rows == 0) {
    $conn->query("ALTER TABLE `pessoas` ADD `foto` VARCHAR(255) DEFAULT NULL");
}

// 2. EXCLUSÃO DE PESSOA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) &&$_POST['acao'] === 'excluir_pessoa') {
    $p_id = intval($_POST['pessoa_id'] ?? 0);
    if ($p_id > 0) {
        $stmt_del =$conn->prepare("DELETE FROM pessoas WHERE id = ?");
        $stmt_del->bind_param("i", $p_id);
        $stmt_del->execute();$mensagem_sucesso = "Registro excluído com sucesso!";
    }
}

// 3. SALVAR / EDITAR PESSOA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'salvar_pessoa') {$pessoa_id = intval($_POST['pessoa_id'] ?? 0);$nome = trim($_POST['nome'] ?? '');$telefone = trim($_POST['telefone'] ?? '');$data_nascimento = !empty($_POST['data_nascimento']) ?$_POST['data_nascimento'] : NULL;
    $equipe_trabalho_origem = trim($_POST['equipe_trabalho_origem'] ?? '');
    $campista_origem = trim($_POST['campista_origem'] ?? '');

    $foto_path = NULL;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {$extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);$novo_nome = 'perfil_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($extensao);$diretorio_destino = 'uploads/perfis/';
        if (!is_dir($diretorio_destino)) mkdir($diretorio_destino, 0777, true);
        if (move_uploaded_file($_FILES['foto']['tmp_name'],$diretorio_destino . $novo_nome)) {$foto_path = $diretorio_destino .$novo_nome;
        }
    }

    if ($pessoa_id > 0) {
        if ($foto_path) {
            $stmt_u =$conn->prepare("UPDATE pessoas SET nome=?, telefone=?, data_nascimento=?, equipe_trabalho_origem=?, campista_origem=?, foto=? WHERE id=?");
            $stmt_u->bind_param("ssssssi", $nome, $telefone,$data_nascimento, $equipe_trabalho_origem,$campista_origem, $foto_path,$pessoa_id);
        } else {
            $stmt_u =$conn->prepare("UPDATE pessoas SET nome=?, telefone=?, data_nascimento=?, equipe_trabalho_origem=?, campista_origem=? WHERE id=?");
            $stmt_u->bind_param("sssssi", $nome,$telefone, $data_nascimento,$equipe_trabalho_origem, $campista_origem,$pessoa_id);
        }
        $stmt_u->execute();
    } else {
        $stmt_i =$conn->prepare("INSERT INTO pessoas (nome, telefone, data_nascimento, equipe_trabalho_origem, campista_origem, foto) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_i->bind_param("ssssss", $nome, $telefone,$data_nascimento, $equipe_trabalho_origem,$campista_origem, $foto_path);$stmt_i->execute();
    }
    $mensagem_sucesso = "Dados atualizados com sucesso!";
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
    LEFT JOIN pessoas p ON p.nome = unificados.nome
    WHERE 1=1
";

$params = [];$types = "";

if (!empty($busca)) {$sql_unificado .= " AND unificados.nome LIKE ?";
    $params[] = "%{$busca}%";
    $types .= "s";
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
    <title>Histórico Central</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        .topbar-efata { background-color: var(--efata-dark); border-bottom: 2px solid var(--efata-gold); color: #fff; }
        .card-pessoa-item { background: #fff; border-radius: 50px; border: 1px solid #E2E8F0; padding: 10px 20px; transition: all 0.2s; cursor: pointer; }
        .card-pessoa-item:hover { border-color: var(--efata-gold); box-shadow: 0 4px 12px rgba(0,0,0,0.06); transform: translateY(-1px); }
        .avatar-box { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid var(--efata-gold); background-color: #E2E8F0; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; color: #64748B; flex-shrink: 0; }
        .avatar-modal { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; border: 3px solid var(--efata-gold); }
    </style>
</head>
<body>

    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top">
        <div class="d-flex align-items-center gap-3">
            <?php if ($edicao_id_param > 0): ?>
                <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id_param ?>&modalidade_id=<?= $modalidade_id_param ?>" class="btn btn-sm btn-outline-light rounded-circle">
                    <i class="bi bi-arrow-left"></i>
                </a>
            <?php endif; ?>
            <h5 class="fw-bold m-0 text-white"><i class="bi bi-journal-bookmark-fill text-warning me-2"></i>Histórico</h5>
        </div>
    </header>

    <main class="container py-4" style="max-width: 800px;">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- BARRA DE PESQUISA -->
        <div class="mb-4">
            <form method="GET" action="historico-campistas.php">
                <input type="hidden" name="edicao_id" value="<?= $edicao_id_param ?>">
                <input type="hidden" name="modalidade_id" value="<?= $modalidade_id_param ?>">
                <div class="input-group input-group-lg shadow-sm rounded-pill overflow-hidden border">
                    <span class="input-group-text bg-white border-0 ps-3"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="busca" class="form-control border-0 ps-1" placeholder="Pesquisar por nome, equipe ou acampamento..." value="<?= htmlspecialchars($busca) ?>">
                    <?php if (!empty($busca)): ?>
                        <a href="historico-campistas.php" class="btn btn-white border-0 d-flex align-items-center"><i class="bi bi-x-lg text-muted"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- LISTA RESUMIDA -->
        <div class="d-flex flex-column gap-3">
            <?php if (empty($lista_pessoas)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-person-exclamation display-4 d-block mb-2"></i>
                    Nenhum registro encontrado.
                </div>
            <?php else: ?>
                <?php foreach ($lista_pessoas as$p): ?>
                    <?php
                    // Busca histórico do Campista
                    $stmt_c =$conn->prepare("
                        SELECT ed.nome as acampamento, ed.ano
                        FROM equipes_membros em
                        LEFT JOIN equipes_trabalho et ON em.equipe_id = et.id
                        LEFT JOIN edicoes ed ON et.edicao_id = ed.id
                        WHERE em.nome = ?
                    ");
                    $stmt_c->bind_param("s", $p['nome']);$stmt_c->execute();
                    $historico_campista =$stmt_c->get_result()->fetch_all(MYSQLI_ASSOC);

                    // Busca histórico da Equipe/Servidor
                    $stmt_e =$conn->prepare("
                        SELECT ed.nome as acampamento, ed.ano, et.nome as equipe_nome
                        FROM equipe_membros em
                        JOIN equipe_geral eg ON em.voluntario_id = eg.id
                        JOIN equipes_trabalho et ON em.equipe_id = et.id
                        JOIN edicoes ed ON et.edicao_id = ed.id
                        WHERE eg.nome = ?
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

                    <!-- SEÇÃO CAMPISTA -->
                    <div class="mb-3">
                        <h6 class="fw-bold text-uppercase text-primary small mb-2"><i class="bi bi-person-badge me-1"></i> CAMPISTA</h6>
                        <div id="modal_campista_conteudo" class="ps-1 small text-muted"></div>
                    </div>

                    <hr class="my-3">

                    <!-- SEÇÃO EQUIPE -->
                    <div class="mb-3">
                        <h6 class="fw-bold text-uppercase text-success small mb-2"><i class="bi bi-people-fill me-1"></i> EQUIPE DE TRABALHO</h6>
                        <div id="modal_equipe_conteudo" class="ps-1 small text-muted"></div>
                    </div>
                </div>

                <div class="modal-footer bg-light d-flex justify-content-between rounded-bottom-4">
                    <form method="POST" action="historico-campistas.php" onsubmit="return confirm('Tem certeza que deseja excluir este registro?');">
                        <input type="hidden" name="acao" value="excluir_pessoa">
                        <input type="hidden" name="pessoa_id" id="modal_excluir_id">
                        <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3"><i class="bi bi-trash me-1"></i> Excluir</button>
                    </form>

                    <button type="button" class="btn btn-dark btn-sm rounded-pill px-4" onclick="abrirModalEdicao()"><i class="bi bi-pencil me-1"></i> Editar</button>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL DE EDIÇÃO -->
    <div class="modal fade" id="modalEditar" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold">Editar Informações</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="historico-campistas.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="acao" value="salvar_pessoa">
                    <input type="hidden" name="pessoa_id" id="edt_pessoa_id">

                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome Completo</label>
                            <input type="text" name="nome" id="edt_nome" class="form-control" required>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold">Telefone</label>
                                <input type="text" name="telefone" id="edt_telefone" class="form-control">
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
                            <label class="form-label fw-semibold">Origem do Campista <small class="text-muted fw-normal">(Pule uma linha para cada acampamento)</small></label>
                            <textarea name="campista_origem" id="edt_campista_origem" class="form-control" rows="2" placeholder="JOAM 2026&#10;FAC 2024"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Equipe de Trabalho <small class="text-muted fw-normal">(Pule uma linha para cada acampamento)</small></label>
                            <textarea name="equipe_trabalho_origem" id="edt_equipe_trabalho_origem" class="form-control" rows="2" placeholder="2° CES 2026 - Direção&#10;FAC 2025 - Guardião"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
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

            // Foto
            const fotoContainer = document.getElementById('modal_foto_container');
            if (dados.foto) {
                fotoContainer.innerHTML = `<img src="${dados.foto}" class="avatar-modal" alt="Foto">`;
            } else {
                fotoContainer.innerHTML = `<div class="avatar-modal bg-light d-flex align-items-center justify-content-center fs-2 text-secondary"><i class="bi bi-person-fill"></i></div>`;
            }

            // Histórico Campista - Separa por quebras de linha (\n)
            const containerCampista = document.getElementById('modal_campista_conteudo');
            let htmlCampista = '';
            
            if (dados.campista_origem) {
                const linhas = dados.campista_origem.split(/\r?\n/);
                linhas.forEach(linha => {
                    if (linha.trim() !== '') {
                        htmlCampista += `<div class="fw-semibold text-dark mb-1">• ${linha.trim()}</div>`;
                    }
                });
            }

            if (dados.historico_campista && dados.historico_campista.length > 0) {
                dados.historico_campista.forEach(c => {
                    htmlCampista += `<div class="fw-semibold text-dark mb-1">• ${c.acampamento || 'Acampamento'} (${c.ano || '-'})</div>`;
                });
            }
            containerCampista.innerHTML = htmlCampista || '<em>Nenhum registro como campista.</em>';

            // Histórico Equipe - Separa por quebras de linha (\n)
            const containerEquipe = document.getElementById('modal_equipe_conteudo');
            let htmlEquipe = '';

            if (dados.equipe_trabalho_origem) {
                const linhasEq = dados.equipe_trabalho_origem.split(/\r?\n/);
                linhasEq.forEach(linha => {
                    if (linha.trim() !== '') {
                        htmlEquipe += `<div class="fw-semibold text-dark mb-1">• ${linha.trim()}</div>`;
                    }
                });
            }

            if (dados.historico_equipe && dados.historico_equipe.length > 0) {
                dados.historico_equipe.forEach(e => {
                    htmlEquipe += `<div class="fw-semibold text-dark mb-1">• ${e.acampamento || ''} ${e.ano || ''} - <strong>${e.equipe_nome || 'Apoio'}</strong></div>`;
                });
            }
            containerEquipe.innerHTML = htmlEquipe || '<em>Nenhum registro em equipe de trabalho.</em>';

            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        }

        function abrirModalEdicao() {
            if (!pessoaAtual) return;

            bootstrap.Modal.getInstance(document.getElementById('modalDetalhes')).hide();

            document.getElementById('edt_pessoa_id').value = pessoaAtual.pessoa_id || 0;
            document.getElementById('edt_nome').value = pessoaAtual.nome || '';
            document.getElementById('edt_telefone').value = pessoaAtual.telefone !== 'Não informado' ? pessoaAtual.telefone : '';
            document.getElementById('edt_data_nascimento').value = pessoaAtual.data_nascimento_raw || '';
            document.getElementById('edt_campista_origem').value = pessoaAtual.campista_origem || '';
            document.getElementById('edt_equipe_trabalho_origem').value = pessoaAtual.equipe_trabalho_origem || '';

            new bootstrap.Modal(document.getElementById('modalEditar')).show();
        }
    </script>
</body>
</html>