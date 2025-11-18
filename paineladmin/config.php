<?php
// Ficheiro: admin/config.php - Configuração Principal do Produto e Vídeos
// ATUALIZADO: Upload de Imagens (Logo, Principal e Galeria) via Botão

// --- 1. LÓGICA PHP E SIMULAÇÃO DE DADOS ---
// 1. Inicia a sessão
session_start();

// 2. VERIFICAÇÃO DE SEGURANÇA
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

include '../db_config.php';

// Configuração de Diretórios de Upload
$video_upload_dir_relative = 'uploads/videos/';
$video_upload_dir_absolute = '../' . $video_upload_dir_relative;

$img_upload_dir_relative = 'uploads/imagens/';
$img_upload_dir_absolute = '../' . $img_upload_dir_relative;

// Garante que os diretórios existam
if (!is_dir($video_upload_dir_absolute)) {
    mkdir($video_upload_dir_absolute, 0777, true);
}
if (!is_dir($img_upload_dir_absolute)) {
    mkdir($img_upload_dir_absolute, 0777, true);
}


// --- LÓGICA DE SALVAMENTO DE CONFIGURAÇÕES PRINCIPAIS (PRODUTO) ---
$mensagem_produto = '';

// Busca dados atuais PRIMEIRO para caso o usuário não faça upload de tudo, mantermos o antigo.
$stmt_current = $pdo->query("SELECT * FROM public.produtos WHERE id = 1");
$current_prod = $stmt_current->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_completo'])) {

    // 1. Campos de Texto/Números
    $preco_atual = filter_input(INPUT_POST, 'preco_atual', FILTER_VALIDATE_FLOAT);
    $preco_antigo = filter_input(INPUT_POST, 'preco_antigo', FILTER_VALIDATE_FLOAT);
    $nome = trim($_POST['nome'] ?? '');
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_FLOAT);
    $rating_count = filter_input(INPUT_POST, 'rating_count', FILTER_VALIDATE_INT);
    $sold_count = filter_input(INPUT_POST, 'sold_count', FILTER_VALIDATE_INT);
    $nome_vendedor = trim($_POST['nome_vendedor'] ?? '');
    $descricao_completa = trim($_POST['descricao_completa'] ?? '');

    // 2. Processamento de UPLOAD DE IMAGENS

    // A. LOGO DO VENDEDOR
    $url_logo_vendedor = $current_prod['url_logo_vendedor']; // Padrão: mantém o atual
    if (isset($_FILES['logo_vendedor_file']) && $_FILES['logo_vendedor_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo_vendedor_file']['name'], PATHINFO_EXTENSION));
        $new_name = uniqid('logo_') . '.' . $ext;
        if (move_uploaded_file($_FILES['logo_vendedor_file']['tmp_name'], $img_upload_dir_absolute . $new_name)) {
            $url_logo_vendedor = $img_upload_dir_relative . $new_name;
        }
    } elseif (!empty($_POST['url_logo_vendedor_text'])) {
        // Fallback caso o usuário ainda queira colar URL manualmente (campo hidden ou opcional)
        $url_logo_vendedor = trim($_POST['url_logo_vendedor_text']);
    }

    // B. IMAGEM PRINCIPAL
    $imagem_principal = $current_prod['imagem_principal']; // Padrão: mantém o atual
    if (isset($_FILES['imagem_principal_file']) && $_FILES['imagem_principal_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['imagem_principal_file']['name'], PATHINFO_EXTENSION));
        $new_name = uniqid('main_') . '.' . $ext;
        if (move_uploaded_file($_FILES['imagem_principal_file']['tmp_name'], $img_upload_dir_absolute . $new_name)) {
            $imagem_principal = $img_upload_dir_relative . $new_name;
        }
    } elseif (!empty($_POST['imagem_principal_text'])) {
        $imagem_principal = trim($_POST['imagem_principal_text']);
    }

    // C. GALERIA DE IMAGENS (Múltiplos Arquivos)
    // Se novos arquivos forem enviados, eles SUBSTITUEM a galeria atual.
    // Se nada for enviado, mantém a atual.
    $imagens_galeria_pg_array = $current_prod['imagens_galeria'];

    if (isset($_FILES['imagens_galeria_files']) && count($_FILES['imagens_galeria_files']['name']) > 0 && $_FILES['imagens_galeria_files']['error'][0] === UPLOAD_ERR_OK) {
        $uploaded_paths = [];
        $total_files = count($_FILES['imagens_galeria_files']['name']);

        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['imagens_galeria_files']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($_FILES['imagens_galeria_files']['name'][$i], PATHINFO_EXTENSION));
                $new_name = uniqid('gallery_') . '_' . $i . '.' . $ext;
                if (move_uploaded_file($_FILES['imagens_galeria_files']['tmp_name'][$i], $img_upload_dir_absolute . $new_name)) {
                    $uploaded_paths[] = $img_upload_dir_relative . $new_name;
                }
            }
        }

        if (!empty($uploaded_paths)) {
            // Cria o formato de array do PostgreSQL: {"caminho1","caminho2"}
            $imagens_galeria_pg_array = '{' . implode(',', array_map(function($url) {
                return '"' . str_replace('"', '\"', $url) . '"';
            }, $uploaded_paths)) . '}';
        }
    }


    // 3. Atualização no Banco de Dados
    if ($preco_atual === false || $preco_antigo === false) {
        $mensagem_produto = "Erro: Valores numéricos inválidos.";
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
            $mensagem_produto = "Configuração salva e imagens enviadas com sucesso! 🎉";
            
            // Recarrega os dados para exibir atualizado
            $stmt_current = $pdo->query("SELECT * FROM public.produtos WHERE id = 1");
            $current_prod = $stmt_current->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            $mensagem_produto = "Erro ao salvar dados: " . $e->getMessage();
        }
    }
}
// --- FIM LÓGICA DE SALVAMENTO PRINCIPAL ---


// --- LÓGICA DE UPLOAD DE VÍDEO (MANTIDA IGUAL) ---
$mensagem_video_upload = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['salvar_video_novo'])) {
    // ... (Lógica mantida, resumida para economizar espaço, mas funcionalidade original permanece)
    $nome_criador = trim($_POST['nome_criador'] ?? '');
    $descricao = trim($_POST['descricao_video'] ?? '');
    $produto_id_video = 1;
    $likes_inicial = filter_input(INPUT_POST, 'likes_inicial', FILTER_VALIDATE_INT) ?: 0;
    $comentarios_inicial = filter_input(INPUT_POST, 'comentarios_inicial', FILTER_VALIDATE_INT) ?: 0;
    $salvos_inicial = filter_input(INPUT_POST, 'salvos_inicial', FILTER_VALIDATE_INT) ?: 0;
    $compartilhamentos_inicial = filter_input(INPUT_POST, 'compartilhamentos_inicial', FILTER_VALIDATE_INT) ?: 0;

    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['video_file']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_ext !== 'mp4') {
            $mensagem_video_upload = "Erro: Apenas arquivos .mp4 são permitidos.";
        } else {
            $novo_nome = uniqid('video_') . '.' . $file_ext;
            if (move_uploaded_file($_FILES['video_file']['tmp_name'], $video_upload_dir_absolute . $novo_nome)) {
                $caminho_db = $video_upload_dir_relative . $novo_nome;
                $sql = "INSERT INTO public.videos_criadores (produto_id, nome_criador, descricao_video, caminho_arquivo, likes, comentarios, salvos, compartilhamentos) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$produto_id_video, $nome_criador, $descricao, $caminho_db, $likes_inicial, $comentarios_inicial, $salvos_inicial, $compartilhamentos_inicial]);
                $mensagem_video_upload = "Vídeo salvo com sucesso!";
            } else {
                $mensagem_video_upload = "Erro ao mover o arquivo de vídeo.";
            }
        }
    }
}

// --- LÓGICA DE DELETAR VÍDEO (MANTIDA) ---
$mensagem_video_delete = '';
if (isset($_POST['deletar_video'])) {
    $video_id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);
    if ($video_id) {
        $stmt = $pdo->prepare("SELECT caminho_arquivo FROM public.videos_criadores WHERE id = ?");
        $stmt->execute([$video_id]);
        $v = $stmt->fetch();
        if ($v) {
            $pdo->prepare("DELETE FROM public.videos_criadores WHERE id = ?")->execute([$video_id]);
            if (file_exists('../' . $v['caminho_arquivo'])) @unlink('../' . $v['caminho_arquivo']);
            $mensagem_video_delete = "Vídeo excluído.";
        }
    }
}

// --- EXIBIÇÃO (FETCH) ---
$produto = $current_prod; // Usa os dados já carregados

// Processa galeria para exibição (apenas visualização textual ou links)
$galeria_links = [];
if (!empty($produto['imagens_galeria'])) {
    $raw = trim($produto['imagens_galeria'], '{}');
    if (!empty($raw)) {
        // Remove aspas duplas extras que o PostgreSQL pode adicionar
        $raw = str_replace('"', '', $raw);
        $galeria_links = explode(',', $raw);
    }
}

$videos_existentes = $pdo->query("SELECT * FROM public.videos_criadores WHERE produto_id = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Configurar Produto</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/particles.js/2.0.0/particles.min.js"></script>
    <style>
        /* Estilos Mantidos */
        :root { --primary-color: #FE2C55; --secondary-color: #69c9d4; --text-color: #f1f1f1; --light-text-color: #a1a1a1; --background-color: #121212; --glass-background: rgba(255, 255, 255, 0.08); --border-color: rgba(255, 255, 255, 0.1); --border-radius: 8px; }
        body { background-color: var(--background-color); color: var(--text-color); font-family: 'Poppins', sans-serif; margin: 0; }
        .main-content { margin-left: 250px; padding: 2rem; }
        #particles-js { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: -1; }
        .content-header { background: var(--glass-background); padding: 1.5rem 2rem; border-radius: var(--border-radius); border: 1px solid var(--border-color); margin-bottom: 2rem; }
        .content-header h1 { color: var(--primary-color); margin: 0; }
        
        /* Formulários */
        form { background: var(--glass-background); border: 1px solid var(--border-color); padding: 2rem; margin-bottom: 2rem; border-radius: var(--border-radius); }
        input[type="text"], input[type="number"], input[type="file"], textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: var(--border-radius); background-color: rgba(0, 0, 0, 0.2); color: var(--text-color); margin-bottom: 1rem; font-family: 'Poppins', sans-serif; }
        button[type="submit"] { background-color: var(--primary-color); color: #000; padding: 12px 20px; border: none; border-radius: var(--border-radius); cursor: pointer; font-weight: 600; text-transform: uppercase; }
        button[type="submit"]:hover { background-color: var(--secondary-color); }
        
        .grid-2-col { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        .mensagem { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius); }
        .success { background: rgba(40,167,69,0.2); border: 1px solid #28a745; }
        .error { background: rgba(220,53,69,0.2); border: 1px solid #dc3545; }

        /* Preview de Imagem */
        .img-preview { max-width: 100px; max-height: 100px; border-radius: 4px; margin-bottom: 10px; display: block; border: 1px solid var(--border-color); }
        .current-file-label { font-size: 0.85rem; color: var(--secondary-color); margin-bottom: 5px; display: block; }
        
        /* Vídeos */
        .video-item { background: rgba(255,255,255,0.05); padding: 10px; margin-bottom: 5px; display: flex; justify-content: space-between; align-items: center; border-radius: 4px; }
        .delete-btn { background: #dc3545; color: #fff; padding: 5px 10px; border-radius: 4px; border: none; cursor: pointer; }
    </style>
</head>
<body>

    <?php include 'admin_sidebar.php'; ?>
    <div id="particles-js"></div>
    
    <main class="main-content">
        <div class="content-header">
            <h1>Configuração do Produto</h1>
            <p>Faça upload das imagens e vídeos diretamente do seu computador.</p>
        </div>

        <?php if ($mensagem_produto): ?>
            <div class="mensagem <?php echo strpos($mensagem_produto, 'Erro') !== false ? 'error' : 'success'; ?>">
                <?php echo htmlspecialchars($mensagem_produto); ?>
            </div>
        <?php endif; ?>

        <form action="config.php" method="POST" enctype="multipart/form-data">
            <h2>Dados e Imagens (ID 1)</h2>

            <div class="grid-2-col">
                <div>
                    <h3>1. Loja e Logo</h3>
                    <label for="nome_vendedor">Nome da Loja:</label>
                    <input type="text" name="nome_vendedor" value="<?php echo htmlspecialchars($produto['nome_vendedor'] ?? ''); ?>">

                    <label>Logo da Loja (Upload):</label>
                    <?php if (!empty($produto['url_logo_vendedor'])): ?>
                        <span class="current-file-label">Atual: <?php echo htmlspecialchars($produto['url_logo_vendedor']); ?></span>
                        <img src="../<?php echo htmlspecialchars($produto['url_logo_vendedor']); ?>" class="img-preview" alt="Logo Atual">
                    <?php endif; ?>
                    <input type="file" name="logo_vendedor_file" accept="image/*">
                </div>

                <div>
                    <h3>2. Produto Principal</h3>
                    <label for="nome">Nome do Produto:</label>
                    <input type="text" name="nome" value="<?php echo htmlspecialchars($produto['nome'] ?? ''); ?>">

                    <label>Imagem Principal (Upload):</label>
                    <?php if (!empty($produto['imagem_principal'])): ?>
                        <span class="current-file-label">Atual: <?php echo htmlspecialchars($produto['imagem_principal']); ?></span>
                        <img src="../<?php echo htmlspecialchars($produto['imagem_principal']); ?>" class="img-preview" alt="Principal Atual">
                    <?php endif; ?>
                    <input type="file" name="imagem_principal_file" accept="image/*">
                </div>
            </div>

            <div class="grid-2-col" style="margin-top: 1rem;">
                <div>
                    <label>Preço Atual (R$):</label>
                    <input type="number" step="0.01" name="preco_atual" value="<?php echo htmlspecialchars($produto['preco_atual'] ?? ''); ?>">
                </div>
                <div>
                    <label>Preço Antigo (R$):</label>
                    <input type="number" step="0.01" name="preco_antigo" value="<?php echo htmlspecialchars($produto['preco_antigo'] ?? ''); ?>">
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <h3>3. Galeria de Fotos</h3>
                <p style="font-size: 0.9rem; color: var(--light-text-color);">Selecione todas as fotos que deseja exibir. O upload substituirá a galeria atual.</p>
                
                <?php if (!empty($galeria_links)): ?>
                    <div style="display: flex; gap: 10px; margin-bottom: 10px; overflow-x: auto;">
                        <?php foreach($galeria_links as $link): ?>
                            <img src="../<?php echo htmlspecialchars(trim($link)); ?>" style="height: 60px; border-radius: 4px; border: 1px solid #444;">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <label for="imagens_galeria_files">Selecionar Imagens (Segure Ctrl para selecionar várias):</label>
                <input type="file" name="imagens_galeria_files[]" multiple accept="image/*">
            </div>

            <div style="margin-top: 2rem;">
                <h3>4. Descrição e Estatísticas</h3>
                <label>Descrição Completa:</label>
                <textarea name="descricao_completa" rows="5"><?php echo htmlspecialchars($produto['descricao_completa'] ?? ''); ?></textarea>
                
                <div class="grid-2-col">
                    <div>
                        <label>Rating (0-5):</label>
                        <input type="number" step="0.1" name="rating" value="<?php echo htmlspecialchars($produto['rating'] ?? ''); ?>">
                    </div>
                    <div>
                        <label>Vendidos:</label>
                        <input type="number" name="sold_count" value="<?php echo htmlspecialchars($produto['sold_count'] ?? ''); ?>">
                    </div>
                </div>
                <input type="hidden" name="rating_count" value="<?php echo htmlspecialchars($produto['rating_count'] ?? 0); ?>">
            </div>

            <button type="submit" name="salvar_completo">Salvar Tudo e Fazer Upload</button>
        </form>

        <hr style="border-color: var(--border-color); margin: 3rem 0;">

        <div style="margin-top: 3rem;">
            <h2>Gerenciar Vídeos</h2>
            <?php if ($mensagem_video_upload): ?>
                <div class="mensagem <?php echo strpos($mensagem_video_upload, 'Erro') !== false ? 'error' : 'success'; ?>"><?php echo $mensagem_video_upload; ?></div>
            <?php endif; ?>
            <?php if ($mensagem_video_delete): ?>
                <div class="mensagem success"><?php echo $mensagem_video_delete; ?></div>
            <?php endif; ?>

            <form action="config.php" method="POST" enctype="multipart/form-data">
                <h3>Upload de Novo Vídeo</h3>
                <label>Arquivo MP4:</label>
                <input type="file" name="video_file" accept="video/mp4" required>
                <label>Criador (@usuario):</label>
                <input type="text" name="nome_criador" placeholder="@exemplo" required>
                <label>Descrição:</label>
                <textarea name="descricao_video" rows="2"></textarea>
                <button type="submit" name="salvar_video_novo">Enviar Vídeo</button>
            </form>

            <h3>Vídeos Ativos</h3>
            <?php foreach ($videos_existentes as $video): ?>
                <div class="video-item">
                    <span><?php echo htmlspecialchars($video['nome_criador']); ?> (<?php echo htmlspecialchars($video['caminho_arquivo']); ?>)</span>
                    <form action="config.php" method="POST" onsubmit="return confirm('Excluir?');" style="background: none; border: none; padding: 0; margin: 0; display: inline;">
                        <input type="hidden" name="video_id" value="<?php echo $video['id']; ?>">
                        <button type="submit" name="deletar_video" class="delete-btn">Excluir</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

    </main>

    <script>
        particlesJS('particles-js', {"particles":{"number":{"value":60,"density":{"enable":true,"value_area":800}},"color":{"value":"#69c9d4"},"shape":{"type":"circle"},"opacity":{"value":0.4,"random":false},"size":{"value":3,"random":true},"line_linked":{"enable":true,"distance":150,"color":"#ffffff","opacity":0.1,"width":1},"move":{"enable":true,"speed":1.5,"direction":"none","random":false,"straight":false,"out_mode":"out","bounce":false}},"interactivity":{"detect_on":"canvas","events":{"onhover":{"enable":true,"mode":"repulse"},"onclick":{"enable":true,"mode":"push"},"resize":true}},"retina_detect":true});
    </script>
</body>
</html>
