<?php
// Ficheiro: rating.php
// Objetivo: Exibir a seção de Avaliações dos Clientes (Rating) buscando o nome do produto no DB e as reviews em 'rating.json'.

// NOTA: A variável $pdo (conexão com o BD) deve estar disponível a partir do arquivo principal (index.php ou main_content.php).

$produto_id_rating = 1; // ID do produto que estamos a exibir

// --- 1. BUSCAR O NOME E DADOS DO PRODUTO NO BANCO DE DADOS ---
$product_name_for_reviews = "Produto Indefinido"; // Fallback padrão
$average_score = 5.0; // Fallback do rating
$total_reviews = 0; // Fallback do total

// A variável $pdo é assumida como a conexão PDO existente.
if (isset($pdo)) {
    try {
        $sql_product = "SELECT nome, rating, rating_count FROM public.produtos WHERE id = :product_id";
        $stmt_product = $pdo->prepare($sql_product);
        $stmt_product->execute([':product_id' => $produto_id_rating]);
        $product_data = $stmt_product->fetch(PDO::FETCH_ASSOC);

        if ($product_data) {
            $product_name_for_reviews = $product_data['nome'];
            $average_score = $product_data['rating'];
            $total_reviews = $product_data['rating_count'];
        }
    } catch (PDOException $e) {
        error_log("Erro ao buscar nome do produto: " . $e->getMessage());
    }
}


// --- 2. CARREGAR DADOS DO JSON (Avaliações Detalhadas) ---
// Mudança aqui para carregar o novo arquivo rating.json
$reviews = [];
if (file_exists('rating.json')) {
    $json_data = file_get_contents('rating.json');
    $reviews = json_decode($json_data, true) ?? [];
}

// O total de reviews detalhadas no JSON
$total_json_reviews = count($reviews);

// --- 3. SELECIONAR 2 AVALIAÇÕES RANDÔMICAS ---
$selected_reviews = [];
if ($total_json_reviews > 0) {
    $keys = array_keys($reviews);
    $random_keys = (count($keys) > 2) ? array_rand($keys, 2) : $keys;

    if (!is_array($random_keys)) {
        $random_keys = [$random_keys];
    }

    foreach ($random_keys as $key) {
        $selected_reviews[] = $reviews[$key];
    }
}


// Função auxiliar para gerar estrelas HTML (total fixo de 5)
function generate_stars($rating) {
    $html = '';
    $rating = min(5, max(0, round($rating)));

    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $rating) {
            $html .= '<span style="color: #FFC000;">★</span>';
        } else {
            $html .= '<span style="color: #DDDDDD;">★</span>';
        }
    }
    return $html;
}
?>

<style>
/* ========================================= */
/* --- CSS RATING/AVALIAÇÕES --- */
/* ========================================= */
.ratings-section {
    padding: 15px;
    background-color: #FFFFFF;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    border-bottom: 10px solid var(--cor-fundo-separador, #F4F4F4);
    box-sizing: border-box;
    border-bottom: 8px solid #f8f8f8;
}

.ratings-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.ratings-header h2 {
    font-size: 16px;
    font-weight: 600;
    color: var(--cor-texto-principal, #222222);
    margin: 0;
}

.ratings-header .view-more {
    font-size: 13px;
    color: #666;
    text-decoration: none;
    font-weight: 500;
}

.average-rating {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    padding: 10px 0;
}

.average-rating .score {
    font-size: 28px;
    font-weight: 700;
    margin-right: 10px;
}

.average-rating .stars span {
    font-size: 22px;
}

.review-card {
    padding: 15px 0;
}

.review-card:last-child {
    border-bottom: none;
}

.review-header {
    display: flex;
    align-items: flex-start; /* Alinhamento ao topo para que o avatar não puxe o texto */
    /* Removemos justify-content: space-between; pois as estrelas não estarão mais na ponta */
    margin-bottom: 8px;
}

.review-header .user-info-wrapper {
    display: flex;
    align-items: flex-start; /* Alinha o avatar e a coluna de texto no topo */
}

.review-header .avatar {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    object-fit: cover;
    margin-right: 8px;
    flex-shrink: 0; /* Impede que o avatar encolha */
}

/* NOVO CONTÊINER PARA NOME E ESTRELAS */
.review-user-details {
    display: flex;
    flex-direction: column; /* Coloca nome e estrelas em coluna */
    line-height: 1.2;
}

.review-header .username {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    margin-bottom: 2px; /* Espaço entre nome e estrelas */
}

.review-header .stars {
    /* Garante que as estrelas no header tenham o tamanho desejado */
    font-size: 14px;
}
.review-header .stars span {
    font-size: 16px;
    line-height: 1;
}

/* --- AJUSTES DE TAMANHO DE FONTE (Mantido) --- */
.review-body {
    font-size: 13px;
    color: #333;
    line-height: 1.4;
}

.review-body .item-info {
    font-size: 11px;
    color: #999;
    margin: 5px 0 10px 0;
}

.review-body .item-info strong {
    font-weight: 600;
    color: #666;
}

/* --- ESTILOS DE EXPANSÃO DE TEXTO (Mantido) --- */
.review-text-content {
    max-height: 3.8em;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
}

.review-text-content.expanded {
    max-height: 1000px;
}

.read-more-btn {
    font-size: 12px;
    color: #007bff;
    cursor: pointer;
    margin-top: 5px;
    display: block;
    font-weight: 500;
}
</style>

<div class="ratings-section" id="avaliacoes">

    <div class="ratings-header">
        <h2>Avaliações dos clientes (<?php echo $total_reviews; ?>)</h2>
        <a href="#" class="view-more">Ver mais ></a>
    </div>

    <div class="average-rating">
        <span class="score"><?php echo number_format($average_score, 1, '.', ''); ?></span>/5
        <div class="stars">
            <?php echo generate_stars($average_score); ?>
        </div>
    </div>

    <?php if ($total_json_reviews > 0): ?>
        <?php $review_index = 0; foreach ($selected_reviews as $review): ?>
            <div class="review-card">
                <div class="review-header">
                    <div class="user-info-wrapper">
                        <img src="<?php echo htmlspecialchars($review['avatar_url']); ?>" alt="Avatar" class="avatar">
                        <div class="review-user-details">
                            <span class="username"><?php echo htmlspecialchars($review['user']); ?></span>
                            <div class="stars">
                                <?php echo generate_stars($review['stars']); ?>
                            </div>
                        </div>
                    </div>
                    </div>

                <div class="review-body">
                    <div class="item-info">
                        Produto: <strong><?php echo htmlspecialchars($product_name_for_reviews); ?></strong> | Item: <strong><?php echo htmlspecialchars($review['item']); ?></strong>
                    </div>
                    <div class="review-text-content" id="review-text-<?php echo $review_index; ?>">
                        <?php echo nl2br(htmlspecialchars($review['text'])); ?>
                    </div>

                    <span class="read-more-btn" id="read-more-<?php echo $review_index; ?>" data-target="review-text-<?php echo $review_index; ?>" style="display: none;">
                        Ver mais
                    </span>
                </div>
            </div>
        <?php $review_index++; endforeach; ?>
    <?php else: ?>
        <p style="color: #999; text-align: center;">Ainda não há avaliações detalhadas para exibir.</p>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const MAX_HEIGHT_PIXELS = 57;

    document.querySelectorAll('.review-text-content').forEach(content => {
        const buttonId = 'read-more-' + content.id.split('-').pop();
        const readMoreButton = document.getElementById(buttonId);

        if (content.scrollHeight > MAX_HEIGHT_PIXELS) {
            if(readMoreButton) {
                readMoreButton.style.display = 'block';

                readMoreButton.addEventListener('click', () => {
                    content.classList.toggle('expanded');

                    if (content.classList.contains('expanded')) {
                        readMoreButton.textContent = 'Ver menos';
                    } else {
                        readMoreButton.textContent = 'Ver mais';
                    }
                });
            }
        }
    });
});
</script>