<?php
// gestao-acessos.php
require_once 'config/database.php';
session_start();

// Trava de segurança: Apenas COORDENADORA_GERAL acessa esta página
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'COORDENADORA_GERAL') {
    header("Location: dashboard-geral.php");
    exit;
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// PROCESSA O CADASTRO DE NOVO USUÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_cadastrar'])) {
    $nome   = trim($_POST['nome'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $senha  = trim($_POST['senha'] ?? '');
    $perfil = trim($_POST['perfil'] ?? 'COORDENADOR');
    $mods   = $_POST['modalidades'] ?? []; // Array de IDs selecionados

    if (!empty($nome) && !empty($email) && !empty($senha)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem_erro = "Por favor, insira um e-mail válido.";
        } else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $email, $senha_hash, $perfil);

            if ($stmt->execute()) {
                $usr_id = $conn->insert_id;

                // Insere as relações de acampamentos
                if (!empty($mods) && is_array($mods)) {
                    $stmt_m = $conn->prepare("INSERT INTO usuario_modalidade (usuario_id, modalidade_id) VALUES (?, ?)");
                    foreach ($mods as $m_id) {
                        $m_id_int = intval($m_id);
                        $stmt_m->bind_param("ii", $usr_id, $m_id_int);
                        $stmt_m->execute();
                    }
                }
                $mensagem_sucesso = "Usuário cadastrado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao cadastrar: " . $conn->error;
            }
        }
    } else {
        $mensagem_erro = "Preencha o nome, e-mail e a senha.";
    }
}

// PROCESSA A EDIÇÃO DO USUÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_editar'])) {
    $id         = intval($_POST['id'] ?? 0);
    $nome       = trim($_POST['nome'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $perfil     = trim($_POST['perfil'] ?? 'COORDENADOR');
    $ativo      = isset($_POST['ativo']) ? intval($_POST['ativo']) : 1;
    $nova_senha = trim($_POST['senha'] ?? '');
    $mods       = $_POST['modalidades'] ?? [];

    if ($id > 0 && !empty($nome) && !empty($email)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem_erro = "Por favor, insira um e-mail válido.";
        } else {
            if (!empty($nova_senha)) {
                $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ?, senha = ? WHERE id = ?");
                $stmt->bind_param("sssisi", $nome, $email, $perfil, $ativo, $senha_hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, perfil = ?, ativo = ? WHERE id = ?");
                $stmt->bind_param("sssii", $nome, $email, $perfil, $ativo, $id);
            }

            if ($stmt->execute()) {
                // Limpa as modalidades antigas
                $stmt_del = $conn->prepare("DELETE FROM usuario_modalidade WHERE usuario_id = ?");
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();

                // Insere as novas seleções
                if (!empty($mods) && is_array($mods)) {
                    $stmt_m = $conn->prepare("INSERT INTO usuario_modalidade (usuario_id, modalidade_id) VALUES (?, ?)");
                    foreach ($mods as $m_id) {
                        $m_id_int = intval($m_id);
                        $stmt_m->bind_param("ii", $id, $m_id_int);
                        $stmt_m->execute();
                    }
                }
                $mensagem_sucesso = "Usuário atualizado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao atualizar: " . $conn->error;
            }
        }
    } else {
        $mensagem_erro = "Preencha o nome e o e-mail.";
    }
}

// PROCESSA A EXCLUSÃO DO USUÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir'])) {
    $id_excluir = intval($_POST['id_excluir'] ?? 0);

    // Evita que o usuário logado exclua a si mesmo
    if ($id_excluir > 0 && $id_excluir !== (int)$_SESSION['usuario_id']) {
        // Remove vínculos com modalidades
        $stmt_del_mods = $conn->prepare("DELETE FROM usuario_modalidade WHERE usuario_id = ?");
        $stmt_del_mods->bind_param("i", $id_excluir);
        $stmt_del_mods->execute();

        // Exclui o usuário
        $stmt_del_usr = $conn->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt_del_usr->bind_param("i", $id_excluir);

        if ($stmt_del_usr->execute()) {
            $mensagem_sucesso = "Usuário excluído com sucesso!";
        } else {
            $mensagem_erro = "Erro ao excluir usuário: " . $conn->error;
        }
    } else {
        $mensagem_erro = "Você não pode excluir seu próprio usuário ou a requisição é inválida.";
    }
}

// Lista todas as modalidades para os selects
$modalidades_result = $conn->query("SELECT id, nome FROM modalidades ORDER BY nome ASC");
$lista_modalidades = [];
if ($modalidades_result) {
    while ($m = $modalidades_result->fetch_assoc()) {
        $lista_modalidades[] = $m;
    }
}

// Busca todos os usuários com seus acampamentos vinculados agrupados
$query_usuarios = "
    SELECT u.*, GROUP_CONCAT(m.nome SEPARATOR ', ') as modalidades_nomes,
           GROUP_CONCAT(m.id SEPARATOR ',') as modalidades_ids
    FROM usuarios u
    LEFT JOIN usuario_modalidade um ON u.id = um.usuario_id
    LEFT JOIN modalidades m ON um.modalidade_id = m.id
    GROUP BY u.id
    ORDER BY u.id DESC";
$usuarios = $conn->query($query_usuarios);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Acessos e Usuários</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #fcfbf9; color: #1c1917; }
        .card-custom { background: #ffffff; border: 1px solid #e7e5e4; border-radius: 16px; padding: 24px; }
    </style>
</head>
<body>

    <header class="bg-white border-bottom py-3 px-4 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard-geral.php" class="btn btn-sm btn-light rounded-circle"><i class="bi bi-arrow-left"></i></a>
            <h5 class="fw-bold m-0">Gestão de Acessos e Usuários</h5>
        </div>
        <button class="btn btn-dark rounded-3 px-3 fw-medium" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
            <i class="bi bi-person-plus-fill me-1"></i> Criar Novo Usuário
        </button>
    </header>

    <main class="container py-5">
        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success rounded-3 mb-4"><?= htmlspecialchars($mensagem_sucesso) ?></div>
        <?php endif; ?>
        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger rounded-3 mb-4"><?= htmlspecialchars($mensagem_erro) ?></div>
        <?php endif; ?>

        <div class="card-custom">
            <h6 class="fw-bold mb-3">Usuários Cadastrados</h6>
            <div class="table-responsive">
                <table class="table table-hover align-middle m-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Acampamentos Vinculados</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($usuarios && $usuarios->num_rows > 0): ?>
                            <?php while ($u = $usuarios->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($u['nome']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($u['perfil']) ?></span></td>
                                    <td>
                                        <?php if ($u['perfil'] === 'COORDENADORA_GERAL'): ?>
                                            <span class="text-success fw-medium">Todos (Acesso Geral)</span>
                                        <?php else: ?>
                                            <?= !empty($u['modalidades_nomes']) ? htmlspecialchars($u['modalidades_nomes']) : '<span class="text-muted">Nenhum</span>' ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= ($u['ativo'] ?? 1) ? 'bg-success' : 'bg-danger' ?>">
                                            <?= ($u['ativo'] ?? 1) ? 'Ativo' : 'Inativo' ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-dark rounded-start-2 btn-editar" 
                                                    data-id="<?= $u['id'] ?>"
                                                    data-nome="<?= htmlspecialchars($u['nome']) ?>"
                                                    data-email="<?= htmlspecialchars($u['email']) ?>"
                                                    data-perfil="<?= htmlspecialchars($u['perfil']) ?>"
                                                    data-modalidades="<?= $u['modalidades_ids'] ?? '' ?>"
                                                    data-ativo="<?= $u['ativo'] ?? 1 ?>"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarUsuario">
                                                <i class="bi bi-pencil me-1"></i> Editar
                                            </button>
                                            
                                            <?php if ((int)$u['id'] !== (int)$_SESSION['usuario_id']): ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger rounded-end-2 btn-excluir" 
                                                        data-id="<?= $u['id'] ?>"
                                                        data-nome="<?= htmlspecialchars($u['nome']) ?>"
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#modalExcluirUsuario">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL NOVO USUÁRIO -->
    <div class="modal fade" id="modalNovoUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Novo Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="gestao-acessos.php" method="POST">
                    <input type="hidden" name="acao_cadastrar" value="1">
                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">Nome do Usuário</label>
                            <input type="text" name="nome" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">E-mail</label>
                            <input type="email" name="email" class="form-control rounded-3" placeholder="usuario@email.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">Senha</label>
                            <input type="password" name="senha" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">Perfil de Acesso</label>
                            <select name="perfil" class="form-select rounded-3" required>
                                <option value="COORDENADOR">Coordenador(a)</option>
                                <option value="DIRETOR">Diretor(a)</option>
                                <option value="COORDENADORA_GERAL">Coordenadora Geral (Acesso Total)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted d-block">Acampamentos Vinculados</label>
                            <div class="border rounded-3 p-3 bg-light">
                                <?php foreach ($lista_modalidades as $m): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="modalidades[]" value="<?= $m['id'] ?>" id="cad_m_<?= $m['id'] ?>">
                                        <label class="form-check-label" for="cad_m_<?= $m['id'] ?>">
                                            <?= htmlspecialchars($m['nome']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar Usuário</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EDITAR USUÁRIO -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Editar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="gestao-acessos.php" method="POST">
                    <input type="hidden" name="acao_editar" value="1">
                    <input type="hidden" name="id" id="edit_id">
                    
                    <div class="modal-body py-4">
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">Nome do Usuário</label>
                            <input type="text" name="nome" id="edit_nome" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">E-mail</label>
                            <input type="email" name="email" id="edit_email" class="form-control rounded-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">Nova Senha <span class="fw-normal text-muted">(Deixe em branco para manter a atual)</span></label>
                            <input type="password" name="senha" class="form-control rounded-3" placeholder="••••••••">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">Perfil de Acesso</label>
                            <select name="perfil" id="edit_perfil" class="form-select rounded-3" required>
                                <option value="COORDENADOR">Coordenador(a)</option>
                                <option value="DIRETOR">Diretor(a)</option>
                                <option value="COORDENADORA_GERAL">Coordenadora Geral (Acesso Total)</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted d-block">Acampamentos Vinculados</label>
                            <div class="border rounded-3 p-3 bg-light">
                                <?php foreach ($lista_modalidades as $m): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input edit-checkbox-modalidade" type="checkbox" name="modalidades[]" value="<?= $m['id'] ?>" id="edit_m_<?= $m['id'] ?>">
                                        <label class="form-check-label" for="edit_m_<?= $m['id'] ?>">
                                            <?= htmlspecialchars($m['nome']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-medium text-muted">Status da Conta</label>
                            <select name="ativo" id="edit_ativo" class="form-select rounded-3">
                                <option value="1">Ativo</option>
                                <option value="0">Inativo</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL EXCLUIR USUÁRIO -->
    <div class="modal fade" id="modalExcluirUsuario" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-danger">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="gestao-acessos.php" method="POST">
                    <input type="hidden" name="acao_excluir" value="1">
                    <input type="hidden" name="id_excluir" id="excluir_id">
                    
                    <div class="modal-body py-4">
                        <p class="mb-0">Tem certeza de que deseja excluir o usuário <strong id="excluir_nome"></strong>?</p>
                        <small class="text-muted">Esta ação não poderá ser desfeita e removerá os acessos vinculados.</small>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-light rounded-3" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger rounded-3 px-4">Excluir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Modal de Edição
        document.querySelectorAll('.btn-editar').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('edit_id').value = this.dataset.id;
                document.getElementById('edit_nome').value = this.dataset.nome;
                document.getElementById('edit_email').value = this.dataset.email;
                document.getElementById('edit_perfil').value = this.dataset.perfil;
                document.getElementById('edit_ativo').value = this.dataset.ativo;

                // Reseta todas as checkboxes do modal de edição
                document.querySelectorAll('.edit-checkbox-modalidade').forEach(cb => cb.checked = false);

                // Marca as checkboxes que o usuário possui vinculo
                const idsArray = this.dataset.modalidades ? this.dataset.modalidades.split(',') : [];
                idsArray.forEach(id => {
                    const checkbox = document.getElementById('edit_m_' + id.trim());
                    if (checkbox) checkbox.checked = true;
                });
            });
        });

        // Modal de Exclusão
        document.querySelectorAll('.btn-excluir').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('excluir_id').value = this.dataset.id;
                document.getElementById('excluir_nome').textContent = this.dataset.nome;
            });
        });
    </script>
</body>
</html>