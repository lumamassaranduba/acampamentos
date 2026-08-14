<?php
// excluir-edicao.php
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

// Captura parâmetros da requisição
$id            = isset($_GET['id']) ? intval($_GET['id']) : 0;
$modalidade_id = isset($_GET['modalidade_id']) ? intval($_GET['modalidade_id']) : 0;

if ($id <= 0 || $modalidade_id <= 0) {
    header("Location: dashboard-geral.php?erro=dados_invalidos");
    exit;
}

// 2. Valida permissões do usuário
$perfil     = $_SESSION['usuario_perfil'] ?? '';
$permitidos = $_SESSION['modalidades_permitidas'] ?? [];

if ($perfil !== 'COORDENADORA_GERAL' && !in_array($modalidade_id, $permitidos)) {
    header("Location: dashboard-geral.php?erro=sem_permissao");
    exit;
}

// 3. Executa a exclusão
$stmt = $conn->prepare("DELETE FROM edicoes WHERE id = ? AND modalidade_id = ?");
if ($stmt) {
    $stmt->bind_param("ii", $id, $modalidade_id);
    if ($stmt->execute()) {
        header("Location: dashboard-acampamento.php?modalidade_id={$modalidade_id}&sucesso=1");
        exit;
    }
}

// Caso ocorra falha
header("Location: dashboard-acampamento.php?modalidade_id={$modalidade_id}&erro=falha_excluir");
exit;