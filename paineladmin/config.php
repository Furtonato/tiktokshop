<?php
// Ficheiro: admin/config.php - Configuração Principal do Produto e Vídeos

// --- 1. LÓGICA PHP E SIMULAÇÃO DE DADOS ---
// 1. Inicia a sessão
session_start();

// 2. VERIFICAÇÃO DE SEGURANÇA: Se o usuário NÃO estiver logado, redireciona para o login.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit; // Crucial: Termina a execução do script imediatamente
}

// Variáveis de sessão já definidas pelo login.php
$admin_username = $_SESSION['admin_username'] ?? 'Usuário Desconhecido';
$admin_level = $_SESSION['admin_level'] ?? 'Gerente';

// O restante do código da página começa aqui, após a proteção.
include '../db_config.php';

$upload_dir_relative = 'uploads/videos/';
$upload_dir_absolute = '../' . $upload_dir_relative;

// Garante que o diretório exista
if (!is_dir($upload_dir_absolute)) {
    mkdir($upload_dir_absolute, 0777, true);
}


// --- LÓGICA DE SALVAMENTO DE CONFIGURAÇÕES PRINCIPAIS (PRODUTO) ---
$mensagem_produto = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_completo'])) {

    $preco_atual = filter_input(INPUT_POST, 'preco_atual', FILTER_VALIDATE_FLOAT);
    $preco_antigo = filter_input(INPUT_POST, 'preco_antigo', FILTER_VALIDATE_FLOAT);
    $nome = trim($_POST['nome'] ?? '');
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_FLOAT);
    $rating_count = filter_input(INPUT_POST, 'rating_count', FILTER_VALIDATE_INT);
    $sold_count = filter_input(INPUT_POST, 'sold_count', FILTER_VALIDATE_INT);
    $imagem_principal = trim($_POST['imagem_principal'] ?? '');
    $galeria_urls_raw = $_POST['imagens_galeria'] ?? '';
    $nome_vendedor = trim($_POST['nome_vendedor'] ?? '');
    $url_logo_vendedor = trim($_POST['url_logo_vendedor'] ?? '');
    $descricao_completa = trim($_POST['descricao_completa'] ?? '');

    $galeria_urls_array = explode("\n", $galeria_urls_raw);
    $galeria_urls_clean = array_filter(array_map('trim', $galeria_urls_array));

    // Formata array PHP para formato TEXT[] do PostgreSQL
    $imagens_galeria_pg_array = '{' . implode(',', array_map(function($url) {
        return '"' . str_replace('"', '\"', $url) . '"';
    }, $galeria_urls_clean)) . '}';

    if ($preco_atual === false || $preco_antigo === false || $rating === false || $rating_count === false || $sold_count === false) {
        $mensagem_produto = "Erro: Por favor, insira valores numéricos válidos para Preços, Rating, Avaliações e Vendidos.";
    } else {
        $sql = "UPDATE public.produtos SET
                    nome = :nome,
                    preco_atual = :preco_atual,
                    preco_antigo = :preco_antigo,
                    rating = :rating,
                    rating_count = :rating_count,
                    sold_count = :sold_count,
                    imagem_principal = :imagem_principal,
                    imagens_galeria = :imagens_galeria,
                    nome_vendedor = :nome_vendedor,
                    url_logo_vendedor = :url_logo_vendedor,
                    descricao_completa = :descricao_completa
                WHERE id = 1";

        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([
                ':nome' => $nome,
                ':preco_atual' => $preco_atual,
                ':preco_antigo' => $preco_antigo,
                ':rating' => $rating,
                ':rating_count' => $rating_count,
                ':sold_count' => $sold_count,
                ':imagem_principal' => $imagem_principal,
                ':imagens_galeria' => $imagens_galeria_pg_array,
                ':nome_vendedor' => $nome_vendedor,
                ':url_logo_vendedor' => $url_logo_vendedor,
                ':descricao_completa' => $descricao_completa
            ]);
            $mensagem_produto = "Configuração do produto e da loja salva com sucesso! 🎉";
        } catch (PDOException $e) {
            $mensagem_produto = "Erro ao salvar dados do produto: " . $e->getMessage();
        }
    }
}
// --- FIM LÓGICA DE SALVAMENTO PRINCIPAL ---


// --- LÓGICA DE UPLOAD, EXCLUSÃO e EDIÇÃO de VÍDEO (MANTIDA) ---
$mensagem_video_upload = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_video_novo'])) {
    $nome_criador = trim($_POST['nome_criador'] ?? '');
    $descricao = trim($_POST['descricao_video'] ?? '');
    $produto_id_video = 1;

    $likes_inicial = filter_input(INPUT_POST, 'likes_inicial', FILTER_VALIDATE_INT);
    $comentarios_inicial = filter_input(INPUT_POST, 'comentarios_inicial', FILTER_VALIDATE_INT);
    $salvos_inicial = filter_input(INPUT_POST, 'salvos_inicial', FILTER_VALIDATE_INT);
    $compartilhamentos_inicial = filter_input(INPUT_POST, 'compartilhamentos_inicial', FILTER_VALIDATE_INT);

    $likes_inicial = ($likes_inicial === false || $likes_inicial === null) ? 0 : $likes_inicial;
    $comentarios_inicial = ($comentarios_inicial === false || $comentarios_inicial === null) ? 0 : $comentarios_inicial;
    $salvos_inicial = ($salvos_inicial === false || $salvos_inicial === null) ? 0 : $salvos_inicial;
    $compartilhamentos_inicial = ($compartilhamentos_inicial === false || $compartilhamentos_inicial === null) ? 0 : $compartilhamentos_inicial;


    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {

        $file_tmp_name = $_FILES['video_file']['tmp_name'];
        $file_name = basename($_FILES['video_file']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'mp4') {
            $mensagem_video_upload = "Erro: Apenas arquivos .mp4 são permitidos.";
        } else {
            $novo_nome = uniqid('video_') . '.' . $file_ext;
            $caminho_completo = $upload_dir_absolute . $novo_nome;
            $caminho_db = $upload_dir_relative . $novo_nome;

            if (move_uploaded_file($file_tmp_name, $caminho_completo)) {
                $sql = "INSERT INTO public.videos_criadores
                         (produto_id, nome_criador, descricao_video, caminho_arquivo, likes, comentarios, salvos, compartilhamentos)
                         VALUES (:produto_id, :nome_criador, :descricao, :caminho_arquivo, :likes, :comentarios, :salvos, :compartilhamentos)";

                $stmt = $pdo->prepare($sql);
                try {
                    $stmt->execute([
                        ':produto_id' => $produto_id_video,
                        ':nome_criador' => $nome_criador,
                        ':descricao' => $descricao,
                        ':caminho_arquivo' => $caminho_db,
                        ':likes' => $likes_inicial,
                        ':comentarios' => $comentarios_inicial,
                        ':salvos' => $salvos_inicial,
                        ':compartilhamentos' => $compartilhamentos_inicial
                    ]);
                    $mensagem_video_upload = "Vídeo salvo e upload concluído com sucesso!";
                } catch (PDOException $e) {
                    $mensagem_video_upload = "Erro ao salvar no DB: " . $e->getMessage();
                    @unlink($caminho_completo);
                }
            } else {
                $mensagem_video_upload = "Erro ao mover o arquivo de vídeo.";
            }
        }
    } else {
        if (empty($mensagem_video_upload) && isset($_POST['salvar_video_novo'])) {
             $mensagem_video_upload = "Erro no upload do arquivo. Verifique o tamanho do arquivo.";
        }
    }
}
// --- FIM LÓGICA DE UPLOAD/EXCLUSÃO/EDIÇÃO (MANTIDA) ---


// --- LÓGICA DE EXCLUSÃO DE VÍDEO (MANTIDA) ---
$mensagem_video_delete = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['deletar_video'])) {
    $video_id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);

    if ($video_id) {
        $stmt_fetch = $pdo->prepare("SELECT caminho_arquivo FROM public.videos_criadores WHERE id = ?");
        $stmt_fetch->execute([$video_id]);
        $video_data = $stmt_fetch->fetch(PDO::FETCH_ASSOC);

        if ($video_data) {
            $caminho_servidor = '../' . $video_data['caminho_arquivo'];

            $stmt_delete_db = $pdo->prepare("DELETE FROM public.videos_criadores WHERE id = ?");
            $stmt_delete_db->execute([$video_id]);

            if (file_exists($caminho_servidor)) {
                @unlink($caminho_servidor);
                $mensagem_video_delete = "Vídeo e registro excluídos com sucesso!";
            } else {
                $mensagem_video_delete = "Registro excluído do DB, mas arquivo não encontrado no servidor.";
            }
        } else {
             $mensagem_video_delete = "Erro: Vídeo não encontrado no DB.";
        }
    } else {
        $mensagem_video_delete = "ID de vídeo inválido.";
    }
}
// --- FIM LÓGICA DE EXCLUSÃO ---


// --- LÓGICA DE EDIÇÃO DE VÍDEO (Salvar) - MANTIDA ---
$mensagem_video_edit = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_edicao_video'])) {
    $video_id = filter_input(INPUT_POST, 'edit_video_id', FILTER_VALIDATE_INT);
    $nome_criador = trim($_POST['edit_nome_criador'] ?? '');
    $descricao = trim($_POST['edit_descricao_video'] ?? '');

    $likes = filter_input(INPUT_POST, 'edit_likes', FILTER_VALIDATE_INT);
    $comentarios = filter_input(INPUT_POST, 'edit_comentarios', FILTER_VALIDATE_INT);
    $salvos = filter_input(INPUT_POST, 'edit_salvos', FILTER_VALIDATE_INT);
    $compartilhamentos = filter_input(INPUT_POST, 'edit_compartilhamentos', FILTER_VALIDATE_INT);

    $likes = ($likes === false) ? 0 : $likes;
    $comentarios = ($comentarios === false) ? 0 : $comentarios;
    $salvos = ($salvos === false) ? 0 : $salvos;
    $compartilhamentos = ($compartilhamentos === false) ? 0 : $compartilhamentos;


    if ($video_id && !empty($nome_criador)) {
        $sql = "UPDATE public.videos_criadores
                  SET nome_criador = :nome_criador,
                      descricao_video = :descricao,
                      likes = :likes,
                      comentarios = :comentarios,
                      salvos = :salvos,
                      compartilhamentos = :compartilhamentos
                  WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        try {
            $stmt->execute([
                ':nome_criador' => $nome_criador,
                ':descricao' => $descricao,
                ':likes' => $likes,
                ':comentarios' => $comentarios,
                ':salvos' => $salvos,
                ':compartilhamentos' => $compartilhamentos,
                ':id' => $video_id
            ]);
            $mensagem_video_edit = "Vídeo ID " . $video_id . " editado com sucesso! 🎉";
            header("Location: config.php?edit_id=" . $video_id . "&msg_edit=success");
            exit;
        } catch (PDOException $e) {
            $mensagem_video_edit = "Erro ao editar no DB: " . $e->getMessage();
        }
    } else {
        $mensagem_video_edit = "ID de vídeo ou nome de criador inválido para edição.";
    }
}
// --- FIM LÓGICA DE EDIÇÃO (Salvar) ---


// --- LÓGICA DE SELEÇÃO DE VÍDEO PARA EDIÇÃO (Fetch) - MANTIDA ---
$video_para_edicao = null;
$edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
$edit_msg_status = filter_input(INPUT_GET, 'msg_edit', FILTER_SANITIZE_SPECIAL_CHARS);

if ($edit_id) {
    $stmt_edit = $pdo->prepare("SELECT id, nome_criador, descricao_video, likes, comentarios, salvos, compartilhamentos FROM public.videos_criadores WHERE id = ?");
    $stmt_edit->execute([$edit_id]);
    $video_para_edicao = $stmt_edit->fetch(PDO::FETCH_ASSOC);

    if ($edit_msg_status === 'success' && $video_para_edicao) {
        $mensagem_video_edit = "Vídeo ID " . $video_para_edicao['id'] . " editado com sucesso! 🎉";
    }
}
// --- FIM LÓGICA DE SELEÇÃO ---


// --- LÓGICA DE EXIBIÇÃO (FETCH) ---

// 1. Configurações principais (Produto)
$produto_stmt = $pdo->query("SELECT nome, preco_atual, preco_antigo, imagem_principal, imagens_galeria, rating, rating_count, sold_count, nome_vendedor, url_logo_vendedor, descricao_completa FROM public.produtos WHERE id = 1");
$produto = $produto_stmt->fetch(PDO::FETCH_ASSOC) ?? [];

$galeria_exibicao = '';
if (!empty($produto['imagens_galeria'])) {
    $galeria_string = trim($produto['imagens_galeria'], '{}');
    $galeria_string = str_replace('"', '', $galeria_string);
    $galeria_exibicao = str_replace(',', "\n", $galeria_string);
}
$descricao_completa_exibicao = $produto['descricao_completa'] ?? '';


// 2. Vídeos existentes para exibição
$videos_existentes_stmt = $pdo->query("SELECT id, nome_criador, caminho_arquivo FROM public.videos_criadores WHERE produto_id = 1 ORDER BY id DESC");
$videos_existentes = $videos_existentes_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Configurar Produto</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <style>
        /* CSS Básico (Mantido) */
        :root {
            --primary-color: #FE2C55;
            --secondary-color: #69c9d4;
            --text-color: #f1f1f1;
            --light-text-color: #a1a1a1;
            --sidebar-color: #000000;
            --background-color: #121212;
            --glass-background: rgba(255, 255, 255, 0.08);
            --border-color: rgba(255, 255, 255, 0.1);
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --success-color: #28a745;
            --error-color: #dc3545;
        }
        body { background-color: var(--background-color); color: var(--text-color); font-family: 'Poppins', sans-serif; }
        .main-content { margin-left: 250px; padding: 2rem; }
        #particles-js { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        .content-header { margin-bottom: 2rem; background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); backdrop-filter: blur(5px); }
        .content-header h1 { font-size: 1.8rem; font-weight: 600; color: var(--primary-color); margin: 0 0 0.25rem 0; }
        .content-header p { font-size: 1rem; color: var(--light-text-color); margin: 0; }
        form { background: var(--glass-background); border: 1px solid var(--border-color); padding: 2rem; margin-bottom: 2rem; border-radius: var(--border-radius); box-shadow: var(--box-shadow); }
        h2 { color: var(--primary-color); margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; font-size: 1.5rem; }
        label { display: block; margin-bottom: 5px; font-weight: 500; color: var(--light-text-color); font-size: 0.9rem; }
        input[type="text"], input[type="number"], input[type="file"], textarea { width: 100%; padding: 10px; box-sizing: border-box; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.2); color: var(--text-color); margin-bottom: 1rem; font-family: 'Poppins', sans-serif; }
        textarea { height: 150px; resize: vertical; }
        button[type="submit"] { background-color: var(--primary-color); color: var(--sidebar-color); font-weight: 600; font-size: 0.9rem; padding: 12px 20px; border: none; border-radius: var(--border-radius); cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; margin-top: 1rem; }
        button[type="submit"]:hover { background-color: var(--secondary-color); color: var(--text-color); box-shadow: 0 4px 10px rgba(254, 44, 85, 0.3); }
        .mensagem { padding: 1rem; margin-bottom: 2rem; border-radius: var(--border-radius); font-weight: 500; }
        .mensagem.success { background-color: rgba(40, 167, 69, 0.2); color: var(--text-color); border: 1px solid var(--success-color); }
        .mensagem.error { background-color: rgba(220, 53, 69, 0.2); color: var(--text-color); border: 1px solid var(--error-color); }
        .grid-2-col { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }

        /* Estilos da Lista de Vídeos e Botões */
        .video-list { list-style: none; padding: 0; }
        .video-item { display: flex; justify-content: space-between; align-items: center; background: rgba(255, 255, 255, 0.05); padding: 10px 15px; border-radius: 6px; margin-bottom: 10px; }
        .video-item-info { flex-grow: 1; padding-right: 20px; }
        .video-item-info strong { display: block; font-size: 1rem; color: var(--text-color); }
        .video-item-info small { color: var(--light-text-color); font-size: 0.8rem; word-break: break-all; }

        .video-item-actions { display: flex; align-items: center; }
        .edit-btn {
            background-color: var(--secondary-color); color: var(--sidebar-color); text-decoration: none;
            padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85rem; font-weight: 500;
            margin-right: 10px; display: inline-block;
        }

        .delete-btn {
            background-color: var(--error-color); color: white; border: none; padding: 8px 12px;
            border-radius: 4px; cursor: pointer; font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 1rem; }
            .grid-2-col { grid-template-columns: 1fr; gap: 1rem; }
        }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>
    <div id="particles-js"></div>
    <main class="main-content">
        <div class="content-header">
            <h1>Configuração do Produto Principal</h1>
            <p>Gerencie nome, preços, imagens e estatísticas do produto ID 1.</p>
        </div>

        <?php if ($mensagem_produto): ?>
            <div class="mensagem <?php echo strpos($mensagem_produto, 'sucesso') !== false ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($mensagem_produto); ?></div>
        <?php endif; ?>

        <form action="config.php" method="POST" id="config-produto">
            <h2>Dados Essenciais (ID 1)</h2>

            <div class="grid-2-col">
                <div>
                    <h3>Configuração da Loja/Vendedor</h3>
                    <label for="nome_vendedor">Nome da Loja/Vendedor:</label>
                    <input type="text" id="nome_vendedor" name="nome_vendedor" value="<?php echo htmlspecialchars($produto['nome_vendedor'] ?? ''); ?>" placeholder="Ex: Nome Oficial da Loja">

                    <label for="url_logo_vendedor">URL da Foto de Perfil (Logo):</label>
                    <input type="text" id="url_logo_vendedor" name="url_logo_vendedor" value="<?php echo htmlspecialchars($produto['url_logo_vendedor'] ?? ''); ?>" placeholder="Ex: https://dominio.com/logo.jpg">
                </div>

                <div>
                    <h3>Nome e Preços do Produto</h3>
                    <label for="nome">Nome do Produto:</label>
                    <input type="text" id="nome" name="nome" value="<?php echo htmlspecialchars($produto['nome'] ?? ''); ?>" placeholder="Ex: Polo Masculina Estilo Italiano">

                    <label for="preco_atual">Preço Atual (R$):</label>
                    <input type="number" step="0.01" id="preco_atual" name="preco_atual" value="<?php echo htmlspecialchars($produto['preco_atual'] ?? ''); ?>" placeholder="Ex: 52.65">

                    <label for="preco_antigo">Preço Antigo / Riscado (R$):</label>
                    <input type="number" step="0.01" id="preco_antigo" name="preco_antigo" value="<?php echo htmlspecialchars($produto['preco_antigo'] ?? ''); ?>" placeholder="Ex: 100.00">
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <h2>Descrição Completa do Produto</h2>
                <label for="descricao_completa">Descrição Detalhada e Especificações:</label>
                <textarea id="descricao_completa" name="descricao_completa" rows="10" placeholder="Insira aqui o texto longo que aparecerá na área 'Sobre este Produto'."><?php echo htmlspecialchars($descricao_completa_exibicao); ?></textarea>
            </div>

            <div style="margin-top: 2rem;">
                <h2>URLs das Imagens</h2>
                <div class="grid-2-col">
                    <div>
                        <label for="imagem_principal">URL da **Imagem Principal**:</label>
                        <input type="text" id="imagem_principal" name="imagem_principal" value="<?php echo htmlspecialchars($produto['imagem_principal'] ?? ''); ?>" placeholder="Ex: https://dominio.com/foto_principal.jpg">
                    </div>
                    <div>
                        <label for="imagens_galeria">URLs da **Galeria** (Uma URL por linha):</label>
                        <textarea id="imagens_galeria" name="imagens_galeria" placeholder="Insira as URLs das imagens secundárias, uma em cada linha."><?php echo htmlspecialchars($galeria_exibicao); ?></textarea>
                    </div>
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <h2>Estatísticas do Produto</h2>
                <div class="grid-2-col">
                    <div>
                        <label for="rating">Avaliação (Rating 0.0 - 5.0):</label>
                        <input type="number" step="0.1" id="rating" name="rating" value="<?php echo htmlspecialchars($produto['rating'] ?? '4.7'); ?>" placeholder="Ex: 4.7">
                    </div>
                    <div>
                        <label for="rating_count">Contagem de Avaliações:</label>
                        <input type="number" step="1" id="rating_count" name="rating_count" value="<?php echo htmlspecialchars($produto['rating_count'] ?? '31'); ?>" placeholder="Ex: 31">
                    </div>
                </div>
                <label for="sold_count">Quantidade de Vendidos:</label>
                <input type="number" step="1" id="sold_count" name="sold_count" value="<?php echo htmlspecialchars($produto['sold_count'] ?? '2873'); ?>" placeholder="Ex: 2873">
            </div>

            <button type="submit" name="salvar_completo">Salvar Configuração Completa</button>
        </form>

        <hr style="border-color: var(--border-color); margin: 3rem 0;">

        <div style="margin-top: 3rem;">
            <h2>Gerenciamento de Vídeos Criadores de Conteúdo (ID 1)</h2>

            <?php if ($mensagem_video_upload): ?>
                <div class="mensagem <?php echo strpos($mensagem_video_upload, 'Erro') !== false ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($mensagem_video_upload); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensagem_video_delete): ?>
                <div class="mensagem <?php echo strpos($mensagem_video_delete, 'Erro') !== false ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($mensagem_video_delete); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensagem_video_edit && strpos($mensagem_video_edit, 'Erro') !== false): ?>
                <div class="mensagem error">
                    <?php echo htmlspecialchars($mensagem_video_edit); ?>
                </div>
            <?php endif; ?>


            <?php if ($video_para_edicao): ?>
                <form action="config.php" method="POST" style="margin-top: 2rem; border: 2px solid var(--secondary-color);">
                    <h3>3. Editar Vídeo ID: <?php echo htmlspecialchars($video_para_edicao['id']); ?></h3>

                    <?php if ($edit_msg_status === 'success' && $video_para_edicao): ?>
                        <div class="mensagem success">
                            <?php echo htmlspecialchars($mensagem_video_edit); ?>
                        </div>
                    <?php endif; ?>

                    <input type="hidden" name="edit_video_id" value="<?php echo htmlspecialchars($video_para_edicao['id']); ?>">

                    <label for="edit_nome_criador">Nome do Criador (Usuário):</label>
                    <input type="text" id="edit_nome_criador" name="edit_nome_criador" value="<?php echo htmlspecialchars($video_para_edicao['nome_criador'] ?? ''); ?>" placeholder="Ex: @Lilitikshop" required>

                    <label for="edit_descricao_video">Descrição e Tags (para rodapé do vídeo):</label>
                    <textarea id="edit_descricao_video" name="edit_descricao_video" placeholder="Ex: Cadeira gamer #jogos #cadeiragamer #live"><?php echo htmlspecialchars($video_para_edicao['descricao_video'] ?? ''); ?></textarea>

                    <h3 style="margin-top: 1.5rem;">Contadores de Engajamento</h3>
                    <div class="grid-2-col">
                        <div>
                            <label for="edit_likes">Curtidas (Likes):</label>
                            <input type="number" step="1" id="edit_likes" name="edit_likes" value="<?php echo htmlspecialchars($video_para_edicao['likes'] ?? '0'); ?>" placeholder="Ex: 109" required>
                        </div>
                        <div>
                            <label for="edit_comentarios">Comentários:</label>
                            <input type="number" step="1" id="edit_comentarios" name="edit_comentarios" value="<?php echo htmlspecialchars($video_para_edicao['comentarios'] ?? '0'); ?>" placeholder="Ex: 7" required>
                        </div>
                        <div>
                            <label for="edit_salvos">Salvos (Bookmarks):</label>
                            <input type="number" step="1" id="edit_salvos" name="edit_salvos" value="<?php echo htmlspecialchars($video_para_edicao['salvos'] ?? '0'); ?>" placeholder="Ex: 15" required>
                        </div>
                        <div>
                            <label for="edit_compartilhamentos">Compartilhamentos (Shares):</label>
                            <input type="number" step="1" id="edit_compartilhamentos" name="edit_compartilhamentos" value="<?php echo htmlspecialchars($video_para_edicao['compartilhamentos'] ?? '0'); ?>" placeholder="Ex: 9" required>
                        </div>
                    </div>


                    <button type="submit" name="salvar_edicao_video">Salvar Edição</button>
                    <a href="config.php" style="margin-left: 10px; color: var(--light-text-color); text-decoration: none;">Cancelar Edição</a>
                </form>
            <?php endif; ?>


            <form action="config.php" method="POST" enctype="multipart/form-data" class="upload-video-form">
                <h3>1. Upload de Novo Vídeo (.mp4)</h3>

                <label for="video_file">Arquivo de Vídeo (.mp4):</label>
                <input type="file" id="video_file" name="video_file" accept="video/mp4" required>

                <label for="nome_criador">Nome do Criador (Usuário):</label>
                <input type="text" id="nome_criador" name="nome_criador" placeholder="Ex: @Lilitikshop" required>

                <label for="descricao_video">Descrição e Tags (para rodapé do vídeo):</label>
                <textarea id="descricao_video" name="descricao_video" placeholder="Ex: Cadeira gamer #jogos #cadeiragamer #live"></textarea>

                <h3 style="margin-top: 1.5rem;">Contadores Iniciais (Opcional)</h3>
                <div class="grid-2-col">
                    <div>
                        <label for="likes_inicial">Curtidas (Likes):</label>
                        <input type="number" step="1" id="likes_inicial" name="likes_inicial" value="0" placeholder="0">
                    </div>
                    <div>
                        <label for="comentarios_inicial">Comentários:</label>
                        <input type="number" step="1" id="comentarios_inicial" name="comentarios_inicial" value="0" placeholder="0">
                    </div>
                    <div>
                        <label for="salvos_inicial">Salvos (Bookmarks):</label>
                        <input type="number" step="1" id="salvos_inicial" name="salvos_inicial" value="0" placeholder="0">
                    </div>
                    <div>
                        <label for="compartilhamentos_inicial">Compartilhamentos (Shares):</label>
                        <input type="number" step="1" id="compartilhamentos_inicial" name="compartilhamentos_inicial" value="0" placeholder="0">
                    </div>
                </div>

                <button type="submit" name="salvar_video_novo">Fazer Upload e Salvar</button>
            </form>

            <div style="margin-top: 2rem;">
                <h3>2. Vídeos Ativos (<?php echo count($videos_existentes); ?>)</h3>

                <?php if (!empty($videos_existentes)): ?>
                    <ul class="video-list">
                        <?php foreach ($videos_existentes as $video): ?>
                            <li class="video-item">
                                <div class="video-item-info">
                                    <strong><?php echo htmlspecialchars($video['nome_criador']); ?></strong>
                                    <small><?php echo htmlspecialchars($video['caminho_arquivo']); ?></small>
                                </div>
                                <div class="video-item-actions">
                                    <a href="config.php?edit_id=<?php echo $video['id']; ?>" class="edit-btn">Editar</a>
                                    <form action="config.php" method="POST" onsubmit="return confirm('Tem certeza que deseja excluir este vídeo?');" style="display: inline-block;">
                                        <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                                        <button type="submit" name="deletar_video" class="delete-btn">Excluir</button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: var(--light-text-color);">Nenhum vídeo encontrado para o Produto ID 1.</p>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script>
        // JS Específico da Página (Particles)
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#69c9d4"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});
    </script>
</body>
</html>