<?php
// equipe-geral.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// Processar formulários de Ação (Cadastrar, Editar, Excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    
    // 1. Cadastrar Voluntário
    if ($_POST['acao'] === 'cadastrar_voluntario') {
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $tamanho_camiseta = $_POST['tamanho_camiseta'] ?? '';

        if (!empty($nome) && !empty($telefone) && !empty($tamanho_camiseta)) {
            $stmt = $conn->prepare("INSERT INTO equipe_geral (edicao_id, nome, telefone, tamanho_camiseta) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $edicao_id, $nome, $telefone, $tamanho_camiseta);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Voluntário cadastrado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao cadastrar voluntário.";
            }
        } else {
            $mensagem_erro = "Preencha todos os campos obrigatórios.";
        }
    }

    // 2. Editar Voluntário
    if ($_POST['acao'] === 'editar_voluntario') {
        $id = intval($_POST['voluntario_id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $telefone = trim($_POST['telefone'] ?? '');
        $tamanho_camiseta = $_POST['tamanho_camiseta'] ?? '';

        if ($id > 0 && !empty($nome) && !empty($telefone) && !empty($tamanho_camiseta)) {
            $stmt = $conn->prepare("UPDATE equipe_geral SET nome = ?, telefone = ?, tamanho_camiseta = ? WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("sssii", $nome, $telefone, $tamanho_camiseta, $id, $edicao_id);
            if ($stmt->execute()) {
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
            $stmt = $conn->prepare("DELETE FROM equipe_geral WHERE id = ? AND edicao_id = ?");
            $stmt->bind_param("ii", $id, $edicao_id);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Voluntário removido com sucesso!";
            } else {
                $mensagem_erro = "Erro ao remover voluntário.";
            }
        }
    }
}

// RF06: Ordenação Automática por Ordem Alfabética
$stmt = $conn->prepare("SELECT * FROM equipe_geral WHERE edicao_id = ? ORDER BY nome ASC");
$stmt->bind_param("i", $edicao_id);
$stmt->execute();
$voluntarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// RF07: Contador Dinâmico
$total_voluntarios = count($voluntarios);
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
    <style>
        :root { --efata-dark: #0F172A; --efata-gold: #C5A059; --efata-bg: #F8FAFC; }
        body { font-family: 'Outfit', sans-serif; background-color: var(--efata-bg); }
        .topbar-efata { background-color: var(--efata-dark); border-bottom: 2px solid var(--efata-gold); color: #fff; }
        .btn-gold { background-color: var(--efata-gold); color: #fff; font-weight: 600; border: none; }
        .btn-gold:hover { background-color: #B38E48; color: #fff; }
        .card-custom { background: #fff; border-radius: 16px; border: 1px solid #E2E8F0; }
        .badge-count { background-color: var(--efata-gold); color: #fff; font-size: 1rem; padding: 6px 14px; border-radius: 20px; }

        /* ESTILOS DE IMPRESSÃO */
        @media print {
            body { background-color: #fff !important; color: #000 !important; font-family: Arial, sans-serif !important; }
            .no-print, .topbar-efata, .alert, .btn, .col-acoes, th:last-child, td:last-child { display: none !important; }
            .container { max-width: 100% !important; width: 100% !important; margin: 0 !important; padding: 0 !important; }
            .card-custom { border: none !important; box-shadow: none !important; padding: 0 !important; }
            
            /* Tabela em estilo impresso rígido */
            .table-print {
                width: 100% !important;
                border-collapse: collapse !important;
                margin-top: 10px !important;
            }
            .table-print th, .table-print td {
                border: 2px solid #000 !important;
                color: #000 !important;
                padding: 6px 10px !important;
                font-size: 14px !important;
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
            /* Garantir que o tamanho da camiseta fique exatamente preto na impressão */
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

    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="equipe-hub.php?edicao_id=<?= $edicao_id ?><?= $modalidade_id ? '&modalidade_id='.$modalidade_id : '' ?>" class="btn btn-sm btn-outline-light rounded-circle">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h5 class="fw-bold m-0 text-white">Equipe Geral (Voluntários)</h5>
        </div>
        <a href="equipes-trabalho.php?edicao_id=<?= $edicao_id ?>" class="btn btn-gold btn-sm px-3 rounded-3">
            <i class="bi bi-diagram-3-fill me-1"></i> Ir para Equipes de Trabalho →
        </a>
    </header>

    <main class="container py-4">

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="alert alert-success alert-dismissible fade show no-print"><?= htmlspecialchars($mensagem_sucesso) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (!empty($mensagem_erro)): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print"><?= htmlspecialchars($mensagem_erro) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- topo: RF07 Contador, Imprimir e Ação de Cadastrar -->
        <div class="card-custom p-4 mb-4 d-flex flex-row align-items-center justify-content-between flex-wrap gap-3 no-print">
            <div class="d-flex align-items-center gap-3">
                <div class="badge-count fw-bold">
                    <i class="bi bi-people-fill me-1"></i> Total: <?= $total_voluntarios ?> Voluntários
                </div>
                <small class="text-muted">Ordenados em ordem alfabética (A-Z)</small>
            </div>
            
            <div class="d-flex gap-2">
                <!-- Botão Imprimir -->
                <button onclick="window.print()" class="btn btn-outline-dark rounded-3">
                    <i class="bi bi-printer-fill me-1"></i> Imprimir Lista
                </button>
                <button class="btn btn-gold rounded-3" data-bs-toggle="modal" data-bs-target="#modalNovoVoluntario">
                    <i class="bi bi-person-plus-fill me-1"></i> + Novo Voluntário
                </button>
            </div>
        </div>

        <!-- Tabela da Equipe Geral -->
        <div class="card-custom p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle table-print">
                    <thead>
                        <!-- Cabeçalho Principal -->
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
                            <?php $num = 1; foreach ($voluntarios as $v): ?>
                                <tr>
                                    <td class="col-num"><?= $num++ ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($v['nome']) ?></td>
                                    <td><?= htmlspecialchars($v['telefone']) ?></td>
                                    <td><span class="badge bg-secondary tam-camiseta"><?= htmlspecialchars($v['tamanho_camiseta']) ?></span></td>
                                    <td class="text-end col-acoes">
                                        <!-- Botão Editar -->
                                        <button class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#modalEditarVoluntario<?= $v['id'] ?>" title="Editar">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <!-- Botão Excluir -->
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
                                                            foreach ($tamanhos as $tam) {
                                                                $selected = ($v['tamanho_camiseta'] === $tam) ? 'selected' : '';
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
                                                    <p class="m-0 fs-5">Tem a certeza de que deseja remover <strong><?= htmlspecialchars($v['nome']) ?></strong>?</p>
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

    <!-- Modal Cadastro -->
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
                            <input type="text" name="nome" class="form-control" required placeholder="Ex: João da Silva">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Telefone / WhatsApp *</label>
                            <input type="text" name="telefone" class="form-control" required placeholder="Ex: (18) 99999-9999">
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
                        <button type="submit" class="btn btn-gold">Salvar Cadastrado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>