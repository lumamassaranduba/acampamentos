<?php
// equipe-geral.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Recupera dados do usuário para a Navbar
$nome_usuario = $_SESSION['usuario_nome'] ?? $_SESSION['nome'] ?? 'Valéria Guimarães';
$perfil       = $_SESSION['usuario_perfil'] ?? $_SESSION['perfil'] ?? 'COORDENADORA_GERAL';

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// Garantir que a tabela 'pessoas' exista para o histórico unificado
$conn->query("
    CREATE TABLE IF NOT EXISTS `pessoas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `nome` VARCHAR(255) NOT NULL,
        `telefone` VARCHAR(20) DEFAULT NULL,
        `data_nascimento` DATE DEFAULT NULL,
        `equipe_trabalho_origem` TEXT DEFAULT NULL,
        `campista_origem` TEXT DEFAULT NULL,
        `foto` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uk_nome` (`nome`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Processar formulários de Ação (Cadastrar, Editar, Excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    // 1. Cadastrar Voluntário com Integração ao Histórico
    if ($_POST['acao'] === 'cadastrar_voluntario') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $tamanho_camiseta = $_POST['tamanho_camiseta'] ?? '';

        if (!empty($nome) && !empty($tamanho_camiseta)) {
            
            // Verifica se a pessoa já existe na tabela de Histórico (pessoas)
            $stmt_p = $conn->prepare("SELECT id FROM pessoas WHERE LOWER(nome) = LOWER(?) LIMIT 1");
            $stmt_p->bind_param("s", $nome);
            $stmt_p->execute();
            $res_p = $stmt_p->get_result()->fetch_assoc();

            if ($res_p) {
                $pessoa_id = $res_p['id'];
                // Atualiza telefone se veio novo
                if (!empty($telefone)) {
                    $stmt_u = $conn->prepare("UPDATE pessoas SET telefone = ? WHERE id = ?");
                    $stmt_u->bind_param("si", $telefone, $pessoa_id);$stmt_u->execute();
                }
            } else {
                // Se não existe, cadastra na tabela de pessoas para ir direto ao histórico
                $stmt_i =$conn->prepare("INSERT INTO pessoas (nome, telefone) VALUES (?, ?)");
                $stmt_i->bind_param("ss", $nome, $telefone);$stmt_i->execute();
            }

            // Insere na Equipe Geral da Edição vinculando com a pessoa
            $stmt =$conn->prepare("INSERT INTO equipe_geral (edicao_id, nome, telefone, tamanho_camiseta) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $edicao_id,$nome, $telefone,$tamanho_camiseta);
            if ($stmt->execute()) {$mensagem_sucesso = "Voluntário cadastrado e vinculado ao histórico com sucesso!";
            } else {
                $mensagem_erro = "Erro ao cadastrar voluntário.";
            }
        } else {
            $mensagem_erro = "Preencha os campos obrigatórios.";
        }
    }

    // 2. Editar Voluntário
    if ($_POST['acao'] === 'editar_voluntario') {
        $id = intval($_POST['voluntario_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $tamanho_camiseta =$_POST['tamanho_camiseta'] ?? '';

        if ($id > 0 && !empty($nome) && !empty($tamanho_camiseta)) {
            $stmt =$conn->prepare("UPDATE equipe_geral SET nome = ?, telefone = ?, tamanho_camiseta = ? WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("sssii", $nome, $telefone,$tamanho_camiseta, $id,$edicao_id);
            if ($stmt->execute()) {
                
                // Atualiza também na tabela 'pessoas'
                $stmt_p =$conn->prepare("UPDATE pessoas SET telefone = ? WHERE LOWER(nome) = LOWER(?)");
                $stmt_p->bind_param("ss", $telefone, $nome);$stmt_p->execute();

                $mensagem_sucesso = "Voluntário atualizado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao atualizar voluntário.";
            }
        } else {
            $mensagem_erro = "Preencha todos os campos obrigatórios para editar.";
        }
    }

    // 3. Excluir Voluntário
    if ($_POST['acao'] === 'excluir_voluntario') {
        $id = intval($_POST['voluntario_id'] ?? 0);

        if ($id > 0) {
            $stmt =$conn->prepare("DELETE FROM equipe_geral WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("ii", $id,$edicao_id);
            if ($stmt->execute()) {$mensagem_sucesso = "Voluntário removido com sucesso!";
            } else {
                $mensagem_erro = "Erro ao remover voluntário.";
            }
        }
    }
}

// Ordenação Automática por Ordem Alfabética
$stmt =$conn->prepare("SELECT * FROM equipe_geral WHERE edicao_id = ? ORDER BY nome ASC");
$stmt->bind_param("i", $edicao_id);$stmt->execute();
$voluntarios =$stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total_voluntarios = count($voluntarios);

// Buscar nomes e telefones cadastrados no histórico para Autocomplete no formulário
$res_historico =$conn->query("
    SELECT DISTINCT nome, telefone FROM (
        SELECT nome, telefone FROM pessoas
        UNION
        SELECT nome, telefone FROM equipe_geral
    ) AS historico_todos
    WHERE nome IS NOT NULL AND nome != ''
    ORDER BY nome ASC
");
$pessoas_historico = $res_historico ? $res_historico->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipe Geral - ÉFATA</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Tom Select CSS -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        
        .navbar-custom { background-color: var(--efata-dark); padding: 10px 20px; border-bottom: 2px solid var(--efata-gold); }
        .user-profile-container { display: flex; align-items: center; gap: 12px; }
        .logo-box { width: 42px; height: 42px; background: #fff; border-radius: 8px; border: 1px solid var(--efata-gold); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .user-info-text { display: flex; flex-direction: column; }
        .user-name { color: #ffffff; font-weight: 700; font-size: 0.95rem; line-height: 1.2; }
        .user-role { color: #94A3B8; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-nav-outline-red { color: #EF4444; border: 1px solid #EF4444; background: transparent; padding: 6px 16px; border-radius: 8px; font-size: 0.875rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .btn-nav-outline-red:hover { background-color: #EF4444; color: #ffffff; }

        .btn-gold { background-color: var(--efata-gold); color: #fff; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #B38E48; color: #fff; }
        .card-custom { background: #fff; border-radius: 16px; border: 1px solid #E2E8F0; }
        .badge-count { background-color: var(--efata-gold); color: #fff; font-size: 1rem; padding: 6px 14px; border-radius: 20px; }

        /* Estilização Tom Select */
        .ts-control { border-radius: 12px !important; padding: 10px 14px !important; border: 1.5px solid #000000 !important; font-size: 0.95rem !important; }
        .ts-dropdown { border-radius: 12px !important; border: 1px solid #e2e8f0 !important; box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important; margin-top: 4px !important; }
        .ts-dropdown .option { padding: 10px 14px !important; font-size: 0.95rem !important; }
        .ts-dropdown .active { background-color: #f1f5f9 !important; color: #0f172a !important; }

        @media print {
            body { 
                background-color: #fff !important; 
                color: #000 !important; 
                font-family: Arial, sans-serif !important; 
            }
            .no-print, .navbar-custom, .alert, .btn, .col-acoes, th:last-child, td:last-child { 
                display: none !important; 
            }
            .container { 
                max-width: 100% !important; 
                width: 100% !important; 
                margin: 0 !important; 
                padding: 0 !important; 
            }
            .card-custom { 
                border: none !important; 
                box-shadow: none !important; 
                padding: 0 !important; 
            }
            
            .table-print { 
                width: 100% !important; 
                border-collapse: collapse !important; 
                margin-top: 10px !important; 
            }
            
            /* Garante legibilidade removendo o fundo escuro do cabeçalho na impressão */
            .table-print th, .table-print td { 
                border: 2px solid #000 !important; 
                color: #000 !important; 
                padding: 6px 10px !important; 
                font-size: 14px !important; 
                background-color: #fff !important;
            }

            .table-print thead tr.table-dark,
            .table-print thead tr.table-dark th {
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                font-weight: bold !important;
            }

            .table-print-header { 
                text-align: center !important; 
                font-weight: bold !important; 
                text-transform: uppercase !important; 
                background-color: #fff !important; 
                font-size: 16px !important; 
            }
            .col-num { 
                width: 45px !important; 
                text-align: center !important; 
                font-weight: bold !important; 
            }
            .tam-camiseta { 
                background: transparent !important; 
                color: #000 !important; 
                font-weight: bold !important; 
                font-size: 14px !important; 
                padding: 0 !important; 
                border-radius: 0 !important; 
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR PADRÃO -->
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top no-print">
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

    <!-- TOPO DE NAVEGAÇÃO -->
    <div class="bg-white border-bottom py-3 no-print mb-4">
        <div class="container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" class="btn btn-sm btn-outline-secondary rounded-circle">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h5 class="fw-bold m-0 text-dark">Equipe Geral (Voluntários)</h5>
            </div>
            <a href="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>" class="btn btn-gold btn-sm px-3 rounded-3">
                <i class="bi bi-diagram-3-fill me-1"></i> Ir para Equipes de Trabalho →
            </a>
        </div>
    </div>

    <main class="container py-2">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print"><?= htmlspecialchars($mensagem_sucesso) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print"><?= htmlspecialchars($mensagem_erro) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- TOPO DE CONTROLE -->
        <div class="card-custom p-4 mb-4 d-flex flex-row align-items-center justify-content-between flex-wrap gap-3 no-print">
            <div class="d-flex align-items-center gap-3">
                <div class="badge-count fw-bold">
                    <i class="bi bi-people-fill me-1"></i> Total: <?= $total_voluntarios ?> Voluntários
                </div>
                <small class="text-muted">Ordenados em ordem alfabética (A-Z)</small>
            </div>
            
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-outline-dark rounded-3">
                    <i class="bi bi-printer-fill me-1"></i> Imprimir Lista
                </button>
                <button class="btn btn-gold rounded-3" data-bs-toggle="modal" data-bs-target="#modalNovoVoluntario">
                    <i class="bi bi-person-plus-fill me-1"></i> + Novo Voluntário
                </button>
            </div>
        </div>

        <!-- TABELA DA EQUIPE GERAL -->
        <div class="card-custom p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-print">
                    <thead>
                        <tr>
                            <th colspan="5" class="table-print-header text-start py-2">
                                EQUIPE GERAL DE VOLUNTÁRIOS
                            </th>
                        </tr>
                        <tr class="table-dark">
                            <th class="col-num"></th>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Tamanho Camiseta</th>
                            <th class="text-end col-acoes" style="width: 150px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($voluntarios)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Nenhum voluntário cadastrado na Equipe Geral.</td></tr>
                        <?php else: ?>
                            <?php $num = 1; foreach ($voluntarios as$v): ?>
                                <tr>
                                    <td class="col-num"><?= $num++ ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($v['nome']) ?></td>
                                    <td><?= htmlspecialchars($v['telefone']) ?></td>
                                    <td><span class="badge bg-secondary tam-camiseta"><?= htmlspecialchars($v['tamanho_camiseta']) ?></span></td>
                                    <td class="text-end col-acoes">
                                        <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalEditarVoluntario<?= $v['id'] ?>" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalExcluirVoluntario<?= $v['id'] ?>" title="Excluir">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>

                                <!-- Modal Editar Voluntário -->
                                <div class="modal fade no-print" id="modalEditarVoluntario<?= $v['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0">
                                            <div class="modal-header bg-dark text-white">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-warning me-2"></i>Editar Voluntário</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="equipe-geral.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST">
                                                <input type="hidden" name="acao" value="editar_voluntario">
                                                <input type="hidden" name="voluntario_id" value="<?= $v['id'] ?>">
                                                <div class="modal-body p-4">
                                                    <div class="mb-3 text-start">
                                                        <label class="form-label fw-semibold">Nome Completo *</label>
                                                        <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($v['nome']) ?>" required>
                                                    </div>
                                                    <div class="mb-3 text-start">
                                                        <label class="form-label fw-semibold">Telefone / WhatsApp *</label>
                                                        <input type="text" name="telefone" class="form-control" value="<?= htmlspecialchars($v['telefone']) ?>" required>
                                                    </div>
                                                    <div class="mb-3 text-start">
                                                        <label class="form-label fw-semibold">Tamanho da Camiseta *</label>
                                                        <select name="tamanho_camiseta" class="form-select" required>
                                                            <?php
                                                            $tamanhos = ['PP', 'P', 'M', 'G', 'GG', 'EXG', 'outro'];
                                                            foreach ($tamanhos as $tam) {$selected = ($v['tamanho_camiseta'] ===$tam) ? 'selected' : '';
                                                                echo "<option value='{$tam}' {$selected}>{$tam}</option>";
                                                            }
                                                            ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-gold">Guardar Alterações</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Modal Excluir Voluntário -->
                                <div class="modal fade no-print" id="modalExcluirVoluntario<?= $v['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog modal-dialog-centered">
                                        <div class="modal-content rounded-4 border-0">
                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Confirmar Exclusão</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <form action="equipe-geral.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST">
                                                <input type="hidden" name="acao" value="excluir_voluntario">
                                                <input type="hidden" name="voluntario_id" value="<?= $v['id'] ?>">
                                                <div class="modal-body p-4 text-center">
                                                    <p class="m-0 fs-5">Tem certeza de que deseja remover <strong><?= htmlspecialchars($v['nome']) ?></strong>?</p>
                                                    <small class="text-muted d-block mt-2">Esta ação não pode ser desfeita.</small>
                                                </div>
                                                <div class="modal-footer bg-light">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                    <button type="submit" class="btn btn-danger">Sim, Remover</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL DE CADASTRO COM TOM SELECT DO HISTÓRICO -->
    <div class="modal fade no-print" id="modalNovoVoluntario" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-warning me-2"></i>Cadastrar Voluntário (Equipe Geral)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipe-geral.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" method="POST">
                    <input type="hidden" name="acao" value="cadastrar_voluntario">
                    <div class="modal-body p-4">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nome Completo *</label>
                            
                            <!-- SELECT INTELIGENTE PASSANDO NOME LIMPO NO VALUE E TELEFONE NO ATRIBUTO -->
                            <select name="nome" id="select_nome_voluntario" placeholder="Digite para pesquisar no histórico..." required>
                                <option value=""></option>
                                <?php foreach ($pessoas_historico as$ph): ?>
                                    <option value="<?= htmlspecialchars($ph['nome']) ?>" data-telefone="<?= htmlspecialchars($ph['telefone'] ?? '') ?>">
                                        <?= htmlspecialchars($ph['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Se a pessoa já serviu antes, selecione o nome para evitar duplicados.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Telefone / WhatsApp *</label>
                            <input type="text" name="telefone" id="input_telefone" class="form-control" required placeholder="Ex: (18) 99999-9999">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tamanho da Camiseta *</label>
                            <select name="tamanho_camiseta" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="PP">PP</option>
                                <option value="P">P</option>
                                <option value="M">M</option>
                                <option value="G">G</option>
                                <option value="GG">GG</option>
                                <option value="EXG">EXG</option>
                                <option value="outro">outro</option>
                            </select>
                        </div>

                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-gold">Salvar Cadastro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const tsVoluntario = new TomSelect("#select_nome_voluntario", {
                create: true, // Permite digitar um novo nome se não existir
                createOnBlur: true,
                maxItems: 1,
                sortField: { field: "text", direction: "asc" },
                render: {
                    // Na lista suspensa: mostra Nome + Telefone (ex: Luma Christofole (18 996678909))
                    option: function(data, escape) {
                        const tel = data.telefone ? ' (' + escape(data.telefone) + ')' : '';
                        return '<div>' + escape(data.text) + tel + '</div>';
                    },
                    // Dentro do campo depois de selecionado: mostra apenas o NOME LIMPO
                    item: function(data, escape) {
                        return '<div>' + escape(data.text) + '</div>';
                    }
                },
                onChange: function(nomeSelecionado) {
                    if (!nomeSelecionado) return;

                    const optionSelecionada = this.options[nomeSelecionado];
                    const campoTel = document.getElementById('input_telefone');

                    if (optionSelecionada && optionSelecionada.telefone && campoTel) {
                        campoTel.value = optionSelecionada.telefone;
                    }
                }
            });
        });
    </script>
</body>
</html>