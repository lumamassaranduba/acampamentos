<?php
// edicao-salvar.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// 1. Verifica se está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id            = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $modalidade_id = isset($_POST['modalidade_id']) ? intval($_POST['modalidade_id']) : 0;
    $nome          = trim($_POST['nome'] ?? '');
    $ano           = isset($_POST['ano']) ? intval($_POST['ano']) : intval(date('Y'));
    $descricao     = trim($_POST['descricao'] ?? '');

    // Validações básicas
    if ($modalidade_id <= 0 || empty($nome)) {
        header("Location: dashboard-acampamento.php?modalidade_id={$modalidade_id}&erro=dados_invalidos");
        exit;
    }

    // 2. Valida permissões do usuário
    $perfil     = $_SESSION['usuario_perfil'] ?? '';
    $permitidos = $_SESSION['modalidades_permitidas'] ?? [];

    if ($perfil !== 'COORDENADORA_GERAL' && !in_array($modalidade_id, $permitidos)) {
        header("Location: dashboard-geral.php?erro=sem_permissao");
        exit;
    }

    if ($id > 0) {
        // ATUALIZAÇÃO (EDITAR EDIÇÃO EXISTENTE)
        $stmt = $conn->prepare("UPDATE edicoes SET nome = ?, ano = ?, descricao = ? WHERE id = ? AND modalidade_id = ?");
        if ($stmt) {
            $stmt->bind_param("sisii", $nome, $ano, $descricao, $id, $modalidade_id);
            if ($stmt->execute()) {
                header("Location: dashboard-acampamento.php?modalidade_id={$modalidade_id}&sucesso=1");
                exit;
            }
        }
    } else {
        // INSERÇÃO (NOVA EDIÇÃO)
        $status_inicial = 'inativa'; // Pode definir como 'inativa' por padrão ao criar
        $stmt = $conn->prepare("INSERT INTO edicoes (modalidade_id, nome, ano, descricao, status) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isiss", $modalidade_id, $nome, $ano, $descricao, $status_inicial);
            if ($stmt->execute()) {
                header("Location: dashboard-acampamento.php?modalidade_id={$modalidade_id}&sucesso=1");
                exit;
            }
        }
    }

    // Caso falhe no banco
    header("Location: dashboard-acampamento.php?modalidade_id={$modalidade_id}&erro=falha_salvar");
    exit;
} else {
    header("Location: dashboard-geral.php");
    exit;
}