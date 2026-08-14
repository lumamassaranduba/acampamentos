<?php
// cadastrar-acampamento.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';

// Verifica se o usuário tem permissão
$perfil = strtoupper(trim($_SESSION['usuario_perfil'] ?? ''));
$perfis_gerais = ['COORDENADORA_GERAL', 'COORDENADOR_GERAL', 'GERAL', 'ADMIN'];

if (!in_array($perfil, $perfis_gerais, true)) {
    header("Location: dashboard-geral.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $lema = trim($_POST['lema'] ?? '');

    if (!empty($nome)) {
        // Tenta inserir na tabela de modalidades/acampamentos
        // Ajuste 'lema' se na sua tabela o campo tiver outro nome (ex: publico_alvo)
        $sql = "INSERT INTO modalidades (nome, lema) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("ss", $nome, $lema);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Redireciona de volta para a página inicial
header("Location: ../dashboard-geral.php");
exit;