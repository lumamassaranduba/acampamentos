<?php
// acampamento-salvar.php
session_start();
require_once 'config/database.php';

// Trava de Segurança: Apenas COORDENADORA_GERAL pode inserir novos acampamentos
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'COORDENADORA_GERAL') {
    header("Location: dashboard-geral.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome        = trim($_POST['nome'] ?? '');
    $descricao   = trim($_POST['descricao'] ?? '');
    $caminho_logo = '';

    // 1. Validação básica de nome
    if (empty($nome)) {
        header("Location: dashboard-geral.php?erro=nome_vazio");
        exit;
    }

    // 2. Processamento do Upload da Imagem/Logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file_tmp  = $_FILES['logo']['tmp_name'];
        $file_name = $_FILES['logo']['name'];
        $ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Extensões de imagem permitidas
        $extensoes_permitidas = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

        if (in_array($ext, $extensoes_permitidas)) {
            // Garante que o diretório de upload existe
            $diretorio = 'uploads/logos/';
            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0755, true);
            }

            // Normaliza o nome do acampamento para busca por arquivo
            $nomeLimpo = mb_strtolower(trim($nome), 'UTF-8');
            $nomeLimpo = preg_replace('/[áàãâä]/u', 'a', $nomeLimpo);
            $nomeLimpo = preg_replace('/[éèêë]/u', 'e', $nomeLimpo);
            $nomeLimpo = preg_replace('/[íìîï]/u', 'i', $nomeLimpo);
            $nomeLimpo = preg_replace('/[óòõôö]/u', 'o', $nomeLimpo);
            $nomeLimpo = preg_replace('/[úùûü]/u', 'u', $nomeLimpo);
            $nomeLimpo = preg_replace('/[^a-z0-9]/', '', $nomeLimpo);

            // Salva como o nome limpo do acampamento para o dashboard encontrar automaticamente
            $novo_nome    = $nomeLimpo . '.' . $ext;
            $caminho_logo = $diretorio . $novo_nome;

            // Move a imagem para a pasta final
            if (!move_uploaded_file($file_tmp, $caminho_logo)) {
                $caminho_logo = '';
            }
        }
    }

    // 3. Verificação dinâmica da estrutura da tabela 'modalidades'
    $colunas_existentes = [];
    $res_colunas = $conn->query("SHOW COLUMNS FROM modalidades");
    if ($res_colunas) {
        while ($col = $res_colunas->fetch_assoc()) {
            $colunas_existentes[] = $col['Field'];
        }
    }

    // Identifica qual coluna guarda a imagem/logo no banco (se existir)
    $coluna_imagem = null;
    foreach (['logo', 'imagem', 'foto'] as $campo) {
        if (in_array($campo, $colunas_existentes)) {
            $coluna_imagem = $campo;
            break;
        }
    }

    // 4. Inserção Segura no Banco de Dados
    if ($coluna_imagem && !empty($caminho_logo)) {
        // Se a coluna existir no banco e uma imagem foi enviada
        $sql = "INSERT INTO modalidades (nome, descricao, {$coluna_imagem}) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $nome, $descricao, $caminho_logo);
    } else {
        // Caso não exista coluna para logo no banco, insere apenas nome e descricao
        $sql = "INSERT INTO modalidades (nome, descricao) VALUES (?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $nome, $descricao);
    }

    if ($stmt->execute()) {
        header("Location: dashboard-geral.php?sucesso=cadastrado");
    } else {
        header("Location: dashboard-geral.php?erro=banco");
    }
    exit;
} else {
    // Acesso direto sem POST
    header("Location: dashboard-geral.php");
    exit;
}
?>