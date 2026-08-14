<?php
// equipe-geral.php
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$edicao_id = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;

if ($edicao_id === 0) {
    die("Edição não especificada.");
}

$mensagem_sucesso = '';
$mensagem_erro = '';

// Processar formulário de cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'cadastrar_voluntario') {
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
    </style>
</head>
<body>

    <header class="topbar-efata py-3 px-4 d-flex align-items-center justify-content-between sticky-top">
        <div class="d-flex align-items-center gap-3">
            <a href="edicao-detalhes.php?id=<?= $edicao_id ?>" class="btn btn-sm btn-outline-light rounded-circle">
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
            <div class="alert alert-success alert-dismissible fade show"><?= $mensagem_sucesso ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- topo: RF07 Contador e Ação de Cadastrar -->
        <div class="card-custom p-4 mb-4 d-flex flex-row align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <div class="badge-count fw-bold">
                    <i class="bi bi-people-fill me-1"></i> Total: <?= $total_voluntarios ?> Voluntários
                </div>
                <small class="text-muted">Ordenados em ordem alfabética (A-Z)</small>
            </div>
            <button class="btn btn-gold rounded-3" data-bs-toggle="modal" data-bs-target="#modalNovoVoluntario">
                <i class="bi bi-person-plus-fill me-1"></i> + Novo Voluntário
            </button>
        </div>

        <!-- Tabela da Equipe Geral -->
        <div class="card-custom p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Tamanho Camiseta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($voluntarios)): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">Nenhum voluntário cadastrado na Equipe Geral.</td></tr>
                        <?php else: ?>
                            <?php foreach ($voluntarios as $v): ?>
                                <tr>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($v['nome']) ?></td>
                                    <td><?= htmlspecialchars($v['telefone']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($v['tamanho_camiseta']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Modal Cadastro RN03 e RN05 -->
    <div class="modal fade" id="modalNovoVoluntario" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus text-warning me-2"></i>Cadastrar Voluntário (Equipe Geral)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="equipe-geral.php?edicao_id=<?= $edicao_id ?>" method="POST">
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