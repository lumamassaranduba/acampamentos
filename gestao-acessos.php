<?php
// gestao-acessos.php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Trava de segurança: Apenas COORDENADORA_GERAL acessa esta página
if (!isset($_SESSION['usuario_id']) || !in_array(strtoupper(trim($_SESSION['usuario_perfil'] ?? '')), ['COORDENADORA_GERAL', 'COORDENADOR_GERAL', 'ADMIN'], true)) {
    header("Location: dashboard-geral.php");
    exit;
}

$perfil_sessao = strtoupper(trim($_SESSION['usuario_perfil'] ?? ''));
$mensagem_sucesso = '';
$mensagem_erro = '';

// PROCESSA O CADASTRO DE NOVO USUÁRIO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_cadastrar'])) {
    $nome   = trim($_POST['nome'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $senha  = trim($_POST['senha'] ?? '');
    $perfil = trim($_POST['perfil'] ?? 'COORDENADOR');
    $mods   = $_POST['modalidades'] ?? [];

    if (!empty($nome) && !empty($email) && !empty($senha)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mensagem_erro = "Por favor, insira um e-mail válido.";
        } else {
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nome, $email, $senha_hash, $perfil);

            if ($stmt->execute()) {
                $usr_id = $conn->insert_id;

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
                $stmt_del = $conn->prepare("DELETE FROM usuario_modalidade WHERE usuario_id = ?");
                $stmt_del->bind_param("i", $id);
                $stmt_del->execute();

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

    if ($id_excluir > 0 && $id_excluir !== (int)$_SESSION['usuario_id']) {
        $stmt_del_mods = $conn->prepare("DELETE FROM usuario_modalidade WHERE usuario_id = ?");
        $stmt_del_mods->bind_param("i", $id_excluir);
        $stmt_del_mods->execute();

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

// Lista modalidades para os selects
$modalidades_result = $conn->query("SELECT id, nome FROM modalidades ORDER BY nome ASC");
$lista_modalidades = [];
if ($modalidades_result) {
    while ($m = $modalidades_result->fetch_assoc()) {
        $lista_modalidades[] = $m;
    }
}

// Busca todos os usuários
$query_usuarios = "
    SELECT u.*, GROUP_CONCAT(m.nome SEPARATOR ', ') as modalidades_nomes,
           GROUP_CONCAT(m.id SEPARATOR ',') as modalidades_ids
    FROM usuarios u
    LEFT JOIN usuario_modalidade um ON u.id = um.usuario_id
    LEFT JOIN modalidades m ON um.modalidade_id = m.id
    GROUP BY u.id
    ORDER BY u.id DESC";
$usuarios_result = $conn->query($query_usuarios);
$usuarios_lista = [];
if ($usuarios_result) {
    while ($usr = $usuarios_result->fetch_assoc()) {
        $usuarios_lista[] = $usr;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Acessos - Éfata</title>
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

        /* Navbar Padronizada */
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

        .main-container {
            padding: 2rem 1.25rem;
            max-width: 1300px;
            margin: 0 auto;
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

        /* Card Container Principal */
        .card-custom {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
        }

        /* Botões */
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

        /* Badge de Perfil em Azul Escuro */
        .badge-navy {
            background-color: var(--navy-dark) !important;
            color: #ffffff !important;
            font-weight: 500;
            padding: 0.4em 0.7em;
            border-radius: 8px;
            font-size: 0.78rem;
        }

        .table-custom-header th {
            background-color: #f8fafc;
            color: #334155;
            font-weight: 700;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.88rem;
            white-space: nowrap;
        }

        .table td {
            font-size: 0.88rem;
            padding: 0.75rem 0.5rem;
        }

        .modal-header-custom {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-bottom: 2px solid var(--gold-primary);
        }

        /* CARD DE USUÁRIO PARA DISPOSITIVOS MÓVEIS E TABLETS */
        .user-card-mobile {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        }

        @media (max-width: 991px) {
            .top-navbar {
                padding: 0.65rem 0.85rem;
            }

            .brand-icon {
                width: 36px;
                height: 36px;
                min-width: 36px;
            }

            .user-info-text h6 {
                font-size: 0.82rem !important;
            }

            .user-info-text small {
                font-size: 0.68rem !important;
            }

            .main-container {
                padding: 1.25rem 0.85rem;
            }
            
            .card-custom {
                padding: 1rem;
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
            <small class="text-light opacity-75 d-block text-truncate" style="font-size: 0.72rem;"><?= htmlspecialchars($perfil_sessao) ?></small>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 nav-actions">
        <button class="btn btn-outline-danger-custom btn-sm rounded-3 px-2 px-sm-3 py-2 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalConfirmarLogout" title="Sair">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-md-inline">Sair</span>
        </button>
    </div>
</nav>

<div class="main-container">
    
    <!-- NOTIFICAÇÕES -->
    <?php if (!empty($mensagem_sucesso)): ?>
        <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($mensagem_sucesso) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($mensagem_erro)): ?>
        <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= htmlspecialchars($mensagem_erro) ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- TÍTULO COM ÍCONE DE SETA E BOTÃO COMPACTO NORMAL -->
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <a href="dashboard-geral.php" class="btn text-dark border-0 p-0 fs-4 d-flex align-items-center justify-content-center" title="Voltar ao Painel">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div>
                <h3 class="fw-bold m-0 text-dark">Gestão de Acessos</h3>
                <p class="text-muted small m-0">Cadastre e controle as permissões dos perfis de utilizadores do sistema.</p>
            </div>
        </div>

        <button class="btn btn-navy rounded-3 px-3 py-2 d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovoUsuario">
            <i class="bi bi-person-plus-fill text-warning"></i>
            <span>Criar Novo Usuário</span>
        </button>
    </div>

    <!-- PAINEL PRINCIPAL -->
    <div class="card-custom">
        <h6 class="fw-bold mb-3 text-dark">Usuários Cadastrados</h6>

        <!-- 1. VISÃO EM TABELA (DESKTOP ACIMA DE 992px: d-none d-lg-block) -->
        <div class="table-responsive d-none d-lg-block">
            <table class="table table-hover align-middle m-0">
                <thead class="table-custom-header">
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
                    <?php if (!empty($usuarios_lista)): ?>
                        <?php foreach ($usuarios_lista as $u): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($u['nome']) ?></td>
                                <td class="text-muted small"><?= htmlspecialchars($u['email']) ?></td>
                                <td>
                                    <span class="badge badge-navy"><?= htmlspecialchars($u['perfil']) ?></span>
                                </td>
                                <td>
                                    <?php if ($u['perfil'] === 'COORDENADORA_GERAL' || $u['perfil'] === 'COORDENADOR_GERAL' || $u['perfil'] === 'ADMIN'): ?>
                                        <span class="text-success fw-medium"><i class="bi bi-shield-check me-1"></i>Todos (Acesso Geral)</span>
                                    <?php else: ?>
                                        <?= !empty($u['modalidades_nomes']) ? htmlspecialchars($u['modalidades_nomes']) : '<span class="text-muted">Nenhum</span>' ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= ($u['ativo'] ?? 1) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> rounded-pill px-3 py-1" style="font-size: 0.75rem;">
                                        <?= ($u['ativo'] ?? 1) ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group">
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-dark rounded-start-3 btn-editar" 
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
                                                    class="btn btn-sm btn-outline-danger rounded-end-3 btn-excluir" 
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
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">Nenhum usuário cadastrado até o momento.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. VISÃO EM CARDS (SMARTPHONES E TABLETS ATÉ 991px: d-block d-lg-none) -->
        <div class="d-block d-lg-none">
            <?php if (!empty($usuarios_lista)): ?>
                <?php foreach ($usuarios_lista as $u): ?>
                    <div class="user-card-mobile">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 class="fw-bold text-dark m-0"><?= htmlspecialchars($u['nome']) ?></h6>
                                <small class="text-muted d-block text-break" style="font-size: 0.82rem;"><?= htmlspecialchars($u['email']) ?></small>
                            </div>
                            <span class="badge <?= ($u['ativo'] ?? 1) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle' ?> rounded-pill px-2.5 py-1" style="font-size: 0.72rem;">
                                <?= ($u['ativo'] ?? 1) ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </div>

                        <div class="mb-3">
                            <span class="badge badge-navy me-1"><?= htmlspecialchars($u['perfil']) ?></span>
                            <div class="mt-2 small text-secondary">
                                <strong>Acampamentos:</strong> 
                                <?php if ($u['perfil'] === 'COORDENADORA_GERAL' || $u['perfil'] === 'COORDENADOR_GERAL' || $u['perfil'] === 'ADMIN'): ?>
                                    <span class="text-success fw-medium"><i class="bi bi-shield-check me-1"></i>Todos (Acesso Geral)</span>
                                <?php else: ?>
                                    <?= !empty($u['modalidades_nomes']) ? htmlspecialchars($u['modalidades_nomes']) : '<span class="text-muted">Nenhum</span>' ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 border-top pt-2">
                            <button type="button" 
                                    class="btn btn-sm btn-outline-dark rounded-3 btn-editar px-3" 
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
                                        class="btn btn-sm btn-outline-danger rounded-3 btn-excluir px-3" 
                                        data-id="<?= $u['id'] ?>"
                                        data-nome="<?= htmlspecialchars($u['nome']) ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#modalExcluirUsuario">
                                    <i class="bi bi-trash me-1"></i> Excluir
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4 text-muted small">Nenhum usuário cadastrado até o momento.</div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- MODAL NOVO USUÁRIO -->
<div class="modal fade" id="modalNovoUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header modal-header-custom p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-person-plus-fill text-warning"></i> Novo Usuário
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="gestao-acessos.php" method="POST">
                <input type="hidden" name="acao_cadastrar" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Nome do Usuário *</label>
                        <input type="text" name="nome" class="form-control rounded-3" placeholder="Ex: João da Silva" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">E-mail *</label>
                        <input type="email" name="email" class="form-control rounded-3" placeholder="usuario@email.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Senha *</label>
                        <input type="password" name="senha" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Perfil de Acesso *</label>
                        <select name="perfil" class="form-select rounded-3" required>
                            <option value="COORDENADOR">Coordenador(a)</option>
                            <option value="DIRETOR">Diretor(a)</option>
                            <option value="COORDENADORA_GERAL">Coordenadora Geral (Acesso Total)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark d-block">Acampamentos Vinculados</label>
                        <div class="border rounded-3 p-3 bg-light">
                            <?php foreach ($lista_modalidades as $m): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="modalidades[]" value="<?= $m['id'] ?>" id="cad_m_<?= $m['id'] ?>">
                                    <label class="form-check-label text-dark" for="cad_m_<?= $m['id'] ?>">
                                        <?= htmlspecialchars($m['nome']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-navy rounded-3 px-4">Salvar Usuário</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EDITAR USUÁRIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header modal-header-custom p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-pencil-square text-warning"></i> Editar Usuário
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="gestao-acessos.php" method="POST">
                <input type="hidden" name="acao_editar" value="1">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Nome do Usuário *</label>
                        <input type="text" name="nome" id="edit_nome" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">E-mail *</label>
                        <input type="email" name="email" id="edit_email" class="form-control rounded-3" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Nova Senha <span class="fw-normal text-muted">(Deixe em branco para manter a atual)</span></label>
                        <input type="password" name="senha" class="form-control rounded-3" placeholder="••••••••">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Perfil de Acesso *</label>
                        <select name="perfil" id="edit_perfil" class="form-select rounded-3" required>
                            <option value="COORDENADOR">Coordenador(a)</option>
                            <option value="DIRETOR">Diretor(a)</option>
                            <option value="COORDENADORA_GERAL">Coordenadora Geral (Acesso Total)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark d-block">Acampamentos Vinculados</label>
                        <div class="border rounded-3 p-3 bg-light">
                            <?php foreach ($lista_modalidades as $m): ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input edit-checkbox-modalidade" type="checkbox" name="modalidades[]" value="<?= $m['id'] ?>" id="edit_m_<?= $m['id'] ?>">
                                    <label class="form-check-label text-dark" for="edit_m_<?= $m['id'] ?>">
                                        <?= htmlspecialchars($m['nome']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-medium text-dark">Status da Conta</label>
                        <select name="ativo" id="edit_ativo" class="form-select rounded-3">
                            <option value="1">Ativo</option>
                            <option value="0">Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-navy rounded-3 px-4">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL EXCLUIR USUÁRIO -->
<div class="modal fade" id="modalExcluirUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 overflow-hidden shadow-lg">
            <div class="modal-header bg-danger text-white p-4">
                <h5 class="modal-title fw-bold d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i> Confirmar Exclusão
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="gestao-acessos.php" method="POST">
                <input type="hidden" name="acao_excluir" value="1">
                <input type="hidden" name="id_excluir" id="excluir_id">
                
                <div class="modal-body p-4">
                    <p class="mb-1 text-dark fs-5">Tem certeza de que deseja excluir o usuário <strong id="excluir_nome"></strong>?</p>
                    <small class="text-muted">Esta ação não poderá ser desfeita e removerá todos os acessos vinculados.</small>
                </div>
                <div class="modal-footer bg-light p-3 border-top">
                    <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Preenchimento Modal de Edição
    document.querySelectorAll('.btn-editar').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.dataset.id;
            document.getElementById('edit_nome').value = this.dataset.nome;
            document.getElementById('edit_email').value = this.dataset.email;
            document.getElementById('edit_perfil').value = this.dataset.perfil;
            document.getElementById('edit_ativo').value = this.dataset.ativo;

            document.querySelectorAll('.edit-checkbox-modalidade').forEach(cb => cb.checked = false);

            const idsArray = this.dataset.modalidades ? this.dataset.modalidades.split(',') : [];
            idsArray.forEach(id => {
                const checkbox = document.getElementById('edit_m_' + id.trim());
                if (checkbox) checkbox.checked = true;
            });
        });
    });

    // Preenchimento Modal de Exclusão
    document.querySelectorAll('.btn-excluir').forEach(button => {
        button.addEventListener('click', function() {
            document.getElementById('excluir_id').value = this.dataset.id;
            document.getElementById('excluir_nome').textContent = this.dataset.nome;
        });
    });
</script>
</body>
</html>