<?php
// contas.php
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

$edicao_id     = isset($_GET['edicao_id']) ? intval($_GET['edicao_id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;
$aba_ativa     = $_GET['aba'] ?? 'resumo';

// Verificar estado da edição (Read-Only se inativa)
$stmt_ed = $conn->prepare("SELECT status, nome, ano FROM edicoes WHERE id = ?");
$stmt_ed->bind_param("i", $edicao_id);
$stmt_ed->execute();
$edicao = $stmt_ed->get_result()->fetch_assoc();
$is_inativa = ($edicao && strtolower($edicao['status']) === 'inativa');

// 1. GARANTE A CRIAÇÃO DAS TABELAS NECESSÁRIAS
$conn->query("
CREATE TABLE IF NOT EXISTS `contas_fornecedores` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `modalidade_id` INT NOT NULL,
  `edicao_id` INT NOT NULL,
  `categoria_servico` VARCHAR(100) NOT NULL,
  `nome_fornecedor` VARCHAR(150) NOT NULL,
  `telefone` VARCHAR(50) DEFAULT NULL,
  `preco` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `observacao` TEXT DEFAULT NULL,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$conn->query("
CREATE TABLE IF NOT EXISTS `contas_compras` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `modalidade_id` INT NOT NULL,
  `edicao_id` INT NOT NULL,
  `categoria` ENUM('cozinha', 'materiais') NOT NULL,
  `produto` VARCHAR(150) NOT NULL,
  `quantidade` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unidade` VARCHAR(20) DEFAULT 'un',
  `valor_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `valor_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `criado_em` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// 2. CADASTRO DE NOVO FORNECEDOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_cadastrar_fornecedor'])) {
    if ($is_inativa) {
        $mensagem_erro = "Edições inativas não podem ser alteradas!";
    } else {
        $categoria       = trim($_POST['categoria_servico'] ?? '');
        $nome_fornecedor = trim($_POST['nome_fornecedor'] ?? '');
        $telefone        = trim($_POST['telefone'] ?? '');
        $preco           = floatval(str_replace(['.', ','], ['', '.'], $_POST['preco'] ?? '0'));
        $observacao      = trim($_POST['observacao'] ?? '');

        if (!empty($nome_fornecedor) && !empty($categoria)) {
            $stmt = $conn->prepare("INSERT INTO contas_fornecedores (modalidade_id, edicao_id, categoria_servico, nome_fornecedor, telefone, preco, observacao) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssds", $modalidade_id, $edicao_id, $categoria, $nome_fornecedor, $telefone, $preco, $observacao);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Fornecedor cadastrado com sucesso!";
            } else {
                $mensagem_erro = "Erro ao cadastrar fornecedor.";
            }
        } else {
            $mensagem_erro = "Preencha a categoria e o nome do fornecedor.";
        }
    }
}

// 3. CADASTRO DE ITEM NA LISTA DE COMPRAS (COZINHA OU MATERIAIS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_cadastrar_compra'])) {
    if ($is_inativa) {
        $mensagem_erro = "Edições inativas não podem ser alteradas!";
    } else {
        $categoria      = $_POST['categoria'] ?? 'cozinha';
        $produto        = trim($_POST['produto'] ?? '');
        $quantidade     = floatval(str_replace(',', '.', $_POST['quantidade'] ?? '1'));
        $unidade        = trim($_POST['unidade'] ?? 'un');
        $valor_unitario = floatval(str_replace(['.', ','], ['', '.'], $_POST['valor_unitario'] ?? '0'));
        $valor_total    = $quantidade * $valor_unitario;

        if (!empty($produto) && $quantidade > 0) {
            $stmt = $conn->prepare("INSERT INTO contas_compras (modalidade_id, edicao_id, categoria, produto, quantidade, unidade, valor_unitario, valor_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissdsdd", $modalidade_id, $edicao_id, $categoria, $produto, $quantidade, $unidade, $valor_unitario, $valor_total);
            if ($stmt->execute()) {
                $mensagem_sucesso = "Item adicionado à lista de compras!";
            } else {
                $mensagem_erro = "Erro ao adicionar item de compra.";
            }
        } else {
            $mensagem_erro = "Preencha o nome do produto e a quantidade.";
        }
    }
}

// 4. EXCLUSÃO DE FORNECEDOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_fornecedor'])) {
    if (!$is_inativa) {
        $id_del = intval($_POST['fornecedor_id']);
        $stmt_del = $conn->prepare("DELETE FROM contas_fornecedores WHERE id = ? AND edicao_id = ?");
        $stmt_del->bind_param("ii", $id_del, $edicao_id);
        $stmt_del->execute();
        $mensagem_sucesso = "Fornecedor removido!";
    }
}

// 5. EXCLUSÃO DE ITEM DE COMPRA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao_excluir_compra'])) {
    if (!$is_inativa) {
        $id_del = intval($_POST['compra_id']);
        $stmt_del = $conn->prepare("DELETE FROM contas_compras WHERE id = ? AND edicao_id = ?");
        $stmt_del->bind_param("ii", $id_del, $edicao_id);
        $stmt_del->execute();
        $mensagem_sucesso = "Item removido da lista!";
    }
}

// 6. BUSCA DADOS E CÁLCULOS DE GASTOS
$stmt_mod = $conn->prepare("SELECT nome FROM modalidades WHERE id = ?");
$stmt_mod->bind_param("i", $modalidade_id);
$stmt_mod->execute();
$modalidade = $stmt_mod->get_result()->fetch_assoc();

// Fornecedores
$stmt_forn = $conn->prepare("SELECT * FROM contas_fornecedores WHERE edicao_id = ? ORDER BY categoria_servico ASC, nome_fornecedor ASC");
$stmt_forn->bind_param("i", $edicao_id);
$stmt_forn->execute();
$fornecedores = $stmt_forn->get_result()->fetch_all(MYSQLI_ASSOC);

// Compras Cozinha
$stmt_cozinha = $conn->prepare("SELECT * FROM contas_compras WHERE edicao_id = ? AND categoria = 'cozinha' ORDER BY produto ASC");
$stmt_cozinha->bind_param("i", $edicao_id);
$stmt_cozinha->execute();
$compras_cozinha = $stmt_cozinha->get_result()->fetch_all(MYSQLI_ASSOC);

// Compras Materiais
$stmt_mat = $conn->prepare("SELECT * FROM contas_compras WHERE edicao_id = ? AND categoria = 'materiais' ORDER BY produto ASC");
$stmt_mat->bind_param("i", $edicao_id);
$stmt_mat->execute();
$compras_materiais = $stmt_mat->get_result()->fetch_all(MYSQLI_ASSOC);

// TOTAIS
$total_cozinha      = array_sum(array_column($compras_cozinha, 'valor_total'));
$total_materiais    = array_sum(array_column($compras_materiais, 'valor_total'));
$gasto_total_geral  = $total_cozinha + $total_materiais;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas e Gastos - <?= htmlspecialchars($modalidade['nome'] ?? '') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --bg-main: #f8fafc;
            --navy-dark: #0A192F;
            --border-color: #e2e8f0;
        }

        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: var(--bg-main); 
            color: #0f172a; 
        }

        .main-container {
            max-width: 1450px;
            margin: 0 auto;
            padding: 2rem;
        }

        .card-total-destaque {
            background: linear-gradient(135deg, #0A192F 0%, #1E293B 100%);
            color: #ffffff;
            border-radius: 20px;
            padding: 1.8rem;
            box-shadow: 0 10px 25px -5px rgba(10, 25, 47, 0.2);
        }

        .card-subtotal {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.25rem;
        }

        .nav-pills .nav-link {
            border-radius: 12px;
            padding: 0.7rem 1.4rem;
            color: #64748b;
            font-weight: 600;
            background: #ffffff;
            border: 1px solid var(--border-color);
        }

        .nav-pills .nav-link.active {
            background-color: var(--navy-dark);
            color: #ffffff;
            border-color: var(--navy-dark);
        }

        .table-custom {
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .table-custom th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 700;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .table-custom td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .main-container { padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>

<div class="main-container">

    <!-- ALERTAS -->
    <?php if ($mensagem_sucesso): ?>
        <div class="alert alert-success rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center no-print">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div><?= $mensagem_sucesso ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensagem_erro): ?>
        <div class="alert alert-danger rounded-4 mb-4 border-0 shadow-sm alert-dismissible fade show d-flex align-items-center no-print">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div><?= $mensagem_erro ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- CABEÇALHO SUPERIOR -->
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <div class="d-flex align-items-center gap-3">
            <a href="edicao-detalhes.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>" class="btn btn-outline-secondary rounded-3">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
            <div>
                <h3 class="fw-bold m-0">Gestão de Contas e Gastos</h3>
                <p class="text-muted small m-0"><?= htmlspecialchars($modalidade['nome'] ?? '') ?> - <?= htmlspecialchars($edicao['nome'] ?? '') ?></p>
            </div>
        </div>

        <button onclick="window.print()" class="btn btn-outline-dark rounded-3 d-flex align-items-center gap-2">
            <i class="bi bi-printer"></i> Imprimir Relatório
        </button>
    </div>

    <!-- CARDS DE TOTALIZADORES FINANCEIROS -->
    <div class="row g-3 mb-4">
        <!-- Gasto Total Geral (Cozinha + Materiais) -->
        <div class="col-lg-4">
            <div class="card-total-destaque d-flex flex-column justify-content-between h-100">
                <div>
                    <span class="text-white-50 text-uppercase fw-semibold small">Gasto Total do Acampamento</span>
                    <h1 class="fw-bold m-0 mt-2 text-warning">R$ <?= number_format($gasto_total_geral, 2, ',', '.') ?></h1>
                </div>
                <div class="small text-white-50 mt-3 border-top border-secondary pt-2">
                    <i class="bi bi-calculator me-1"></i> Soma de Compras da Cozinha + Materiais
                </div>
            </div>
        </div>

        <!-- Subtotais (Cozinha e Materiais) -->
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card-subtotal h-100">
                        <span class="text-muted small fw-semibold d-block text-uppercase">Compras Cozinha</span>
                        <h3 class="fw-bold text-dark mt-1 mb-2">R$ <?= number_format($total_cozinha, 2, ',', '.') ?></h3>
                        <span class="badge bg-light text-secondary border"><?= count($compras_cozinha) ?> item(ns)</span>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card-subtotal h-100">
                        <span class="text-muted small fw-semibold d-block text-uppercase">Compras Materiais</span>
                        <h3 class="fw-bold text-dark mt-1 mb-2">R$ <?= number_format($total_materiais, 2, ',', '.') ?></h3>
                        <span class="badge bg-light text-secondary border"><?= count($compras_materiais) ?> item(ns)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ABAS NAVEGÁVEIS -->
    <ul class="nav nav-pills gap-2 mb-4 no-print" id="pills-tab">
        <li class="nav-item">
            <button class="nav-link <?= $aba_ativa === 'resumo' || $aba_ativa === 'fornecedores' ? 'active' : '' ?>" id="pills-fornecedores-tab" data-bs-toggle="pill" data-bs-target="#pills-fornecedores" type="button">
                <i class="bi bi-truck me-2"></i>Fornecedores (Camisetas, Gráfica, etc.)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $aba_ativa === 'cozinha' ? 'active' : '' ?>" id="pills-cozinha-tab" data-bs-toggle="pill" data-bs-target="#pills-cozinha" type="button">
                <i class="bi bi-egg-fried me-2"></i>Lista de Compras: Cozinha
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link <?= $aba_ativa === 'materiais' ? 'active' : '' ?>" id="pills-materiais-tab" data-bs-toggle="pill" data-bs-target="#pills-materiais" type="button">
                <i class="bi bi-box-seam me-2"></i>Lista de Compras: Materiais
            </button>
        </li>
    </ul>

    <!-- CONTEÚDO DAS ABAS -->
    <div class="tab-content" id="pills-tabContent">

        <!-- 1. ABA FORNECEDORES -->
        <div class="tab-pane fade <?= $aba_ativa === 'resumo' || $aba_ativa === 'fornecedores' ? 'show active' : '' ?>" id="pills-fornecedores">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0">Lista de Fornecedores Contratados</h5>
                <?php if (!$is_inativa): ?>
                    <button class="btn btn-dark rounded-3 btn-sm px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovoFornecedor">
                        <i class="bi bi-plus-lg"></i> Adicionar Fornecedor
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-custom">
                <table class="table m-0">
                    <thead>
                        <tr>
                            <th>Categoria / Serviço</th>
                            <th>Nome do Fornecedor</th>
                            <th>Telefone / Contato</th>
                            <th>Valor / Orçamento</th>
                            <th>Observações</th>
                            <?php if (!$is_inativa): ?>
                                <th class="text-end no-print">Ação</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($fornecedores)): ?>
                            <?php foreach ($fornecedores as $f): ?>
                                <tr>
                                    <td><span class="badge bg-light text-dark border fw-semibold"><?= htmlspecialchars($f['categoria_servico']) ?></span></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($f['nome_fornecedor']) ?></td>
                                    <td><?= !empty($f['telefone']) ? htmlspecialchars($f['telefone']) : '<span class="text-muted">-</span>' ?></td>
                                    <td class="fw-bold text-secondary">R$ <?= number_format($f['preco'], 2, ',', '.') ?></td>
                                    <td class="small text-secondary"><?= !empty($f['observacao']) ? htmlspecialchars($f['observacao']) : '<span class="text-muted">-</span>' ?></td>
                                    <?php if (!$is_inativa): ?>
                                        <td class="text-end no-print">
                                            <form action="" method="POST" onsubmit="return confirm('Excluir este fornecedor?');" class="d-inline">
                                                <input type="hidden" name="acao_excluir_fornecedor" value="1">
                                                <input type="hidden" name="fornecedor_id" value="<?= $f['id'] ?>">
                                                <button type="submit" class="btn btn-link text-danger p-0 border-0">
                                                    <i class="bi bi-trash fs-6"></i>
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">Nenhum fornecedor cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. ABA COMPRAS COZINHA -->
        <div class="tab-pane fade <?= $aba_ativa === 'cozinha' ? 'show active' : '' ?>" id="pills-cozinha">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0">Compras para Cozinha e Alimentação</h5>
                <?php if (!$is_inativa): ?>
                    <button class="btn btn-dark rounded-3 btn-sm px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovaCompraCozinha">
                        <i class="bi bi-plus-lg"></i> Adicionar Item de Cozinha
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-custom">
                <table class="table m-0">
                    <thead>
                        <tr>
                            <th>Produto / Item</th>
                            <th>Quantidade</th>
                            <th>Valor Unitário</th>
                            <th>Valor Total</th>
                            <?php if (!$is_inativa): ?>
                                <th class="text-end no-print">Ação</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($compras_cozinha)): ?>
                            <?php foreach ($compras_cozinha as $c): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($c['produto']) ?></td>
                                    <td><?= number_format($c['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($c['unidade']) ?></td>
                                    <td>R$ <?= number_format($c['valor_unitario'], 2, ',', '.') ?></td>
                                    <td class="fw-bold text-primary">R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                                    <?php if (!$is_inativa): ?>
                                        <td class="text-end no-print">
                                            <form action="" method="POST" onsubmit="return confirm('Excluir este item?');" class="d-inline">
                                                <input type="hidden" name="acao_excluir_compra" value="1">
                                                <input type="hidden" name="compra_id" value="<?= $c['id'] ?>">
                                                <button type="submit" class="btn btn-link text-danger p-0 border-0">
                                                    <i class="bi bi-trash fs-6"></i>
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Nenhum item cadastrado para a cozinha.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. ABA COMPRAS MATERIAIS -->
        <div class="tab-pane fade <?= $aba_ativa === 'materiais' ? 'show active' : '' ?>" id="pills-materiais">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold m-0">Compras de Materiais Geral / Infraestrutura</h5>
                <?php if (!$is_inativa): ?>
                    <button class="btn btn-dark rounded-3 btn-sm px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#modalNovaCompraMateriais">
                        <i class="bi bi-plus-lg"></i> Adicionar Material
                    </button>
                <?php endif; ?>
            </div>

            <div class="table-custom">
                <table class="table m-0">
                    <thead>
                        <tr>
                            <th>Produto / Material</th>
                            <th>Quantidade</th>
                            <th>Valor Unitário</th>
                            <th>Valor Total</th>
                            <?php if (!$is_inativa): ?>
                                <th class="text-end no-print">Ação</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($compras_materiais)): ?>
                            <?php foreach ($compras_materiais as $m): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($m['produto']) ?></td>
                                    <td><?= number_format($m['quantidade'], 2, ',', '.') ?> <?= htmlspecialchars($m['unidade']) ?></td>
                                    <td>R$ <?= number_format($m['valor_unitario'], 2, ',', '.') ?></td>
                                    <td class="fw-bold text-primary">R$ <?= number_format($m['valor_total'], 2, ',', '.') ?></td>
                                    <?php if (!$is_inativa): ?>
                                        <td class="text-end no-print">
                                            <form action="" method="POST" onsubmit="return confirm('Excluir este material?');" class="d-inline">
                                                <input type="hidden" name="acao_excluir_compra" value="1">
                                                <input type="hidden" name="compra_id" value="<?= $m['id'] ?>">
                                                <button type="submit" class="btn btn-link text-danger p-0 border-0">
                                                    <i class="bi bi-trash fs-6"></i>
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">Nenhum material cadastrado.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- MODAIS DE CADASTRO -->
<div class="modal fade" id="modalNovoFornecedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck text-warning me-2"></i> Novo Fornecedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="contas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&aba=fornecedores" method="POST">
                <input type="hidden" name="acao_cadastrar_fornecedor" value="1">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Categoria / Serviço *</label>
                        <input type="text" name="categoria_servico" class="form-control" placeholder="Ex: Camisetas, Gráfica, Som, Transporte" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nome do Fornecedor / Empresa *</label>
                        <input type="text" name="nome_fornecedor" class="form-control" placeholder="Ex: Malharia Silva, Gráfica Central" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Telefone / Contato</label>
                            <input type="text" name="telefone" class="form-control" placeholder="(18) 99999-9999">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Preço / Valor Total (R$)</label>
                            <input type="text" name="preco" class="form-control" placeholder="0,00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Observações</label>
                        <textarea name="observacao" class="form-control" rows="2" placeholder="Ex: 150 camisetas modelo dryfit azul..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark rounded-3 px-4">Salvar Fornecedor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovaCompraCozinha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-egg-fried text-warning me-2"></i> Adicionar Item de Cozinha</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="contas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&aba=cozinha" method="POST">
                <input type="hidden" name="acao_cadastrar_compra" value="1">
                <input type="hidden" name="categoria" value="cozinha">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nome do Produto *</label>
                        <input type="text" name="produto" class="form-control" placeholder="Ex: Arroz 5kg, Carne moída, Leite" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Quantidade *</label>
                            <input type="text" name="quantidade" class="form-control" placeholder="Ex: 10" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Unidade de Medida</label>
                            <input type="text" name="unidade" class="form-control" placeholder="Ex: kg, un, pct, caixa" value="un">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Valor Unitário (R$)</label>
                        <input type="text" name="valor_unitario" class="form-control" placeholder="0,00">
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark rounded-3 px-4">Adicionar Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNovaCompraMateriais" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white p-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-seam text-warning me-2"></i> Adicionar Material</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="contas.php?edicao_id=<?= $edicao_id ?>&modalidade_id=<?= $modalidade_id ?>&aba=materiais" method="POST">
                <input type="hidden" name="acao_cadastrar_compra" value="1">
                <input type="hidden" name="categoria" value="materiais">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label small fw-medium">Nome do Material / Produto *</label>
                        <input type="text" name="produto" class="form-control" placeholder="Ex: Cabo de aço 8mm, Lona preta, Bexigas" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-medium">Quantidade *</label>
                            <input type="text" name="quantidade" class="form-control" placeholder="Ex: 5" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-medium">Unidade de Medida</label>
                            <input type="text" name="unidade" class="form-control" placeholder="Ex: metros, un, rolo" value="un">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-medium">Valor Unitário (R$)</label>
                        <input type="text" name="valor_unitario" class="form-control" placeholder="0,00">
                    </div>
                </div>
                <div class="modal-footer bg-light p-3">
                    <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-dark rounded-3 px-4">Adicionar Material</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>