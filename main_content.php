<?php
// Ficheiro: main_content.php (COMPLETO: CARROSSEL, PREÇO, PARCELAMENTO, TÍTULO/RATING, FRETE, PROTEÇÃO, OFERTAS)
// Este ficheiro espera que a $pdo já exista (vinda da index.php).

// --- LÓGICA DE BUSCAR DADOS ---
$produto_id = 1;

try {
    // 1. Busca colunas relevantes para o carrossel, preço e o novo bloco.
    $sql = "SELECT
                nome, imagem_principal, imagens_galeria,
                preco_atual, preco_antigo, rating, rating_count, sold_count
            FROM public.produtos WHERE id = ?";
    $produto_stmt = $pdo->prepare($sql);
    $produto_stmt->execute([$produto_id]);
    $produto = $produto_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        throw new Exception("Produto com ID {$produto_id} não encontrado.");
    }

    // CORREÇÃO: Usamos o nome vindo do DB como título
    $titulo_produto = htmlspecialchars($produto['nome'] ?? 'Polo Masculina...');

    // Preços
    $preco_atual = floatval($produto['preco_atual'] ?? 0);
    $preco_antigo = floatval($produto['preco_antigo'] ?? 0);

    // Dados de Rating e Vendas (com fallback, caso o DB retorne nulo)
    $rating = $produto['rating'] ?? '4.7';
    $rating_count = $produto['rating_count'] ?? '31';
    $sold_count = $produto['sold_count'] ?? '2873';

    // Tags (TEXTOS FIXOS)
    $tag_campanha = "Black Friday";

    // Calcula o percentual de desconto
    $desconto_percentual = 0;
    if ($preco_antigo > $preco_atual) {
        $desconto_percentual = round((($preco_antigo - $preco_atual) / $preco_antigo) * 100);
    }

    // 2. Processamento das imagens
    $imagens = [];
    if (!empty($produto['imagem_principal'])) {
        $imagens[] = $produto['imagem_principal'];
    }
    if (!empty($produto['imagens_galeria'])) {
        $galeria_string = trim($produto['imagens_galeria'], '{}');
        $galeria_string = str_replace('"', '', $galeria_string);

        if (!empty($galeria_string)) {
            $galeria_array = explode(',', $galeria_string);
            $imagens = array_merge($imagens, $galeria_array);
        }
    }

} catch (Exception $e) {
    // Dados de fallback para evitar quebrar o layout
    $preco_atual = 52.65; $preco_antigo = 100.00; $desconto_percentual = 47;
    $rating = '4.7'; $rating_count = '31'; $sold_count = '2873';
    $tag_campanha = "Black Friday";
    $titulo_produto = "Produto de Exemplo com Título Longo...";
    $imagens = ['placeholder.jpg'];
    $nome_produto = 'Erro ao carregar produto';
}

if (empty($imagens)) {
    $imagens[] = 'placeholder.jpg';
}

$total_imagens = count($imagens);

// --- CÁLCULO DINÂMICO DO PARCELAMENTO ---
$num_parcelas = 10;
$valor_parcela = $preco_atual / $num_parcelas;
$texto_parcelamento = "{$num_parcelas}x R$ " . number_format($valor_parcela, 2, ',', '.');

// --- CÁLCULO DINÂMICO DE DATAS (1 SEMANA A PARTIR DE HOJE) ---
$data_hoje = new DateTime('now');
$data_min = clone $data_hoje;
$data_max = clone $data_hoje;

$data_min->modify('+5 weekdays');
$data_max->modify('+10 weekdays');
$data_entrega_formatada = $data_min->format('j') . ' – ' . $data_max->format('j \d\e M');

// --- TEXTOS FIXOS DO LAYOUT ---
$texto_prefixo_preco = "";
$texto_novos_clientes = "Novos clientes";
$texto_apenas = "apenas";
$texto_cupom_desconto = "Desconto de 10%, máximo de R$ 25";
$texto_frete_gratis = "Frete grátis";
$texto_taxa_envio = "Taxa de envio: R$ 8,40";

// --- TEXTOS FIXOS DO BLOCO DE PROTEÇÃO E OFERTAS ---
$texto_protecao = "Proteção do cliente";
$vantagens = [
    "Devolução gratuita",
    "Reembolso automático por danos",
    "Pagamento seguro",
    "Cupom por atraso na coleta"
];
$titulo_ofertas = "Ofertas";
$titulo_cupom_envio = "Cupom de envio";
$descricao_cupom = "Desconto de R$ 20 no frete em pedidos acima de R$ 29";
$texto_resgatar = "Resgatar";
?>

<style>
/* ========================================= */
/* --- CSS GERAL E CARROSSEL --- */
/* ========================================= */
:root {
    --header-max-width: 768px;
    --cor-vermelha-principal: #fe3c47;
    --cor-fundo-separador: #FFFFFF;
    --cor-texto-principal: #222222;
    --cor-texto-secundario: #555555;
    --cor-tag-campanha-fundo: #000000;
    --cor-frete-verde: #00BFA5;

    /* Cores ajustadas */
    --cor-fundo-cupom-pink: #FFCCCC;
    --cor-fundo-black-friday: #E6E0FF;
    --cor-escudo-protecao: #8B4513;
    --cor-check-protecao: #DAA520;
}

body {
    margin: 0;
}

.main-content-wrapper {
    width: 100%;
    margin: 0 auto;
    max-width: var(--header-max-width);
    background-color: #FFFFFF;
    position: relative;
    cursor: grab;
}
.main-content-wrapper:active {
    cursor: grabbing;
}

/* --- CARROSSEL (Mantido) --- */
.product-carousel {
    width: 100%;
    aspect-ratio: 1 / 1;
    position: relative;
    overflow: hidden;
}

.carousel-track {
    display: flex;
    width: <?php echo $total_imagens * 100; ?>%;
    height: 100%;
    will-change: transform;
}
.carousel-track.dragging {
    transition: none !important;
}
.carousel-slide {
    width: calc(100% / <?php echo $total_imagens; ?>);
    height: 100%;
    flex-shrink: 0;
    user-select: none;
}
.carousel-slide img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
}
.pagination-badge {
    position: absolute;
    bottom: 15px;
    right: 15px;
    background-color: rgba(0, 0, 0, 0.4);
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    z-index: 5;
}

/* ========================================= */
/* --- CSS BLOCO DE PREÇO --- */
/* ========================================= */
.price-bar-red {
    background: linear-gradient(90deg, #fe5335 0%, var(--cor-vermelha-principal) 100%);
    color: #FFFFFF;
    padding: 10px 15px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    position: relative;
    overflow: hidden;
    margin-bottom: 0px;
}
.price-bar-red::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    box-shadow: 0 4px 8px rgba(255, 59, 48, 0.4);
    z-index: 1;
}
/* --- ESTILO DO TIMER (BEM PEQUENO) --- */
.timer-container {
    position: absolute;
    top: 5px;
    right: 15px;
    display: flex;
    align-items: center;
    background-color: rgba(0, 0, 0, 0.2);
    padding: 2px 6px;
    border-radius: 4px;
    z-index: 3;
    font-size: 10px;
    line-height: 1;
    color: #FFC107;
    gap: 4px;
}

.timer-container svg {
    width: 10px;
    height: 10px;
    fill: #FFC107;
    margin-right: 4px;
}
.timer-container .timer-time {
    font-weight: bold;
}
/* FIM DO TIMER CSS */

.price-details-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 5px;
    position: relative;
    z-index: 2;
}
.customer-tag {
    position: relative;
    top: 10px;
    font-size: 11px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
    color: #FFFFFF;
}
.price-details-bottom {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    position: relative;
    z-index: 2;
}
.price-left {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: 2px;
}
.discount-tag {
    background-color: #FFFFFF;
    color: var(--cor-vermelha-principal);
    font-size: 14px;
    font-weight: bold;
    padding: 2px 6px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 0;
}
.discount-tag .discount-percent {
    font-size: 16px;
}
.current-price-group {
    display: flex;
    align-items: baseline;
    font-weight: 700;
}
.current-price-group .prefix {
    font-size: 10px;
    margin-right: 3px;
}
.current-price-group .price-value {
    font-size: 24px;
    line-height: 1;
}
.old-price {
    font-size: 14px;
    text-decoration: line-through;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1;
    margin-left: 2px;
}
.apenas-text {
    font-size: 12px;
    font-weight: 300;
    color: rgba(255, 255, 255, 0.9);
}
.customer-tag svg {
    color: #FFFFFF;
    width: 14px;
    height: 14px;
}
.current-price-group svg {
    color: #FFFFFF;
    width: 14px;
    height: 14px;
    margin-left: 7px;
}

/* ========================================= */
/* --- CSS BLOCO DE INFO AGRUPADO --- */
/* ========================================= */
.info-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    font-size: 14px;
    border-bottom: none;
    cursor: pointer;
}
.installment-bar {
    color: var(--cor-texto-principal);
    font-weight: 500;
    margin-left: 5px;
}
.coupon-bar {
    background-color: #ffe2e7;
    color: #cb043b;
    font-weight: 600;
    padding: 8px 15px;
    margin: 0;
    font-size: 11px;
    line-height: 1;
    margin-left: 18px;
    margin-right: 143px;
    border-radius: 5px;
    height: 12px;
}
.info-icon-left {
    display: flex;
    align-items: center;
    gap: 8px;
}
.installment-bar .info-icon-left svg {
    color: #333333;
    width: 16px;
    height: 16px;
}
.coupon-bar .info-icon-left svg {
    color: #cb043b;
    width: 16px;
    height: 16px;
    margin-left: -10px;
}
.info-bar .chevron-right {
    font-size: 12px;
    color: #AAAAAA;
    margin-left: 10px;
}
.coupon-bar .chevron-right {
    color: #cb043b;
}
.separator-div {
    height: 10px;
    background-color: var(--cor-fundo-separador);
}

/* ========================================= */
/* --- CSS BLOCO TÍTULO E AVALIAÇÃO --- */
/* ========================================= */
.title-rating-block {
    border-bottom: 1px solid #EEEEEE;
    background-color: white;
    padding: 12px 4px;
    margin: 0 15px;
}

.title-line {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 10px;
}
.title-line h1 {
    font-size: 16px;
    color: var(--cor-texto-principal);
    line-height: 1.4;
    font-weight: 600;
    margin: 0;
}
.campaign-tag {
    background-color: var(--cor-fundo-black-friday);
    color: var(--cor-texto-principal);
    font-size: 11px;
    font-weight: bold;
    padding: 2px 5px;
    border-radius: 4px;
    display: inline-block;
    margin-right: 8px;
    vertical-align: middle;
}
.bookmark-icon {
    flex-shrink: 0;
    padding-left: 10px;
}
.bookmark-icon svg {
    color: var(--cor-texto-secundario);
    width: 24px;
    height: 19px;
    cursor: pointer;
}
.rating-line {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 12px;
    color: var(--cor-texto-secundario);
}
.rating-line .star {
    color: #FFC107;
    font-size: 14px;
}
.rating-line .rating-count {
    font-weight: 500;
}
.rating-line .sold-count {
    padding-left: 8px;
    border-left: 1px solid #ccc;
}

/* ========================================= */
/* --- CSS BLOCO DE ENVIO/FRETE --- */
/* ========================================= */
.shipping-block {
    padding: 15px;
    background-color: #FFFFFF;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    margin-left: 6px;
}
.shipping-left {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.shipping-left svg {
    color: var(--cor-texto-secundario);
    width: 18px;
    height: 18px;
    flex-shrink: 0;
    margin-top: 2px;
}
.shipping-main-line {
    font-size: 14px;
    color: var(--cor-texto-principal);
    line-height: 1.2;
    margin-bottom: 5px;
}
.shipping-main-line .free-shipping-tag {
    color: var(--cor-frete-verde);
    font-weight: 600;
    margin-right: 5px;
    background-color: rgba(0, 191, 165, 0.1);
    padding: 1px 3px;
    border-radius: 2px;
}
.shipping-details-line {
    font-size: 12px;
    color: var(--cor-texto-secundario);
    line-height: 1.4;
}
.shipping-details-line .shipping-cost-old {
    text-decoration: line-through;
}
.shipping-block .chevron-right {
    color: #AAAAAA;
}

/* ========================================= */
/* --- CSS BLOCO PROTEÇÃO DO CLIENTE --- */
/* ========================================= */
.customer-protection-block {
    padding: 15px;
    background-color: #fff7ec;
    border-bottom: 8px solid #f8f8f8;
    height: 76px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}
.protection-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.protection-header-title {
    display: flex;
    align-items: center;
    font-weight: 600;
    font-size: 14px;
    color: var(--cor-escudo-protecao);
}
.protection-header-title svg {
    width: 18px;
    height: 18px;
    stroke: var(--cor-escudo-protecao);
    fill: none;
    margin-right: 8px;
}
.protection-header .chevron-right {
    font-size: 12px;
    color: #AAAAAA;
}
.protection-details-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 15px;
    font-size: 12px;
    color: var(--cor-texto-principal);
}
.protection-item {
    display: flex;
    align-items: center;
}
.protection-item .check-mark {
    color: var(--cor-check-protecao);
    font-weight: bold;
    margin-right: 5px;
}

/* ========================================= */
/* --- CSS BLOCO DE OFERTAS/CUPONS --- */
/* ========================================= */

.offers-section {
    padding: 15px;
    background-color: var(--cor-fundo-separador);
    border-bottom: 10px solid var(--cor-fundo-separador);
    border-bottom: 8px solid #f8f8f8;
    background-color: white;

}
.offers-section h2 {
    font-size: 18px;
    font-weight: 600;
    color: var(--cor-texto-principal);
    margin: 0 0 10px 0;
}

.shipping-coupon-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background-color: #E0FFFF;
    border-radius: 10px;
    padding: 15px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    height: 37px;
}

.coupon-details {
    flex-grow: 1;
    color: var(--cor-texto-principal);
    padding-right: 15px;
}
.coupon-details h3 {
    font-size: 14px;
    font-weight: 600;
    margin: 0 0 5px 0;
}
.coupon-details p {
    font-size: 12px;
    color: var(--cor-texto-secundario);
    margin: 0;
}

.btn-resgatar {
    background-color: var(--cor-frete-verde);
    color: #FFFFFF;
    font-size: 14px;
    font-weight: 600;
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    white-space: nowrap;
}

/* ========================================= */
/* --- CSS DO POP-UP (MODAL) - AVANÇADO --- */
/* ========================================= */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 2000;

    /* ESCONDIDO POR PADRÃO com opacidade e scale para a transição */
    opacity: 0;
    pointer-events: none; /* Garante que não bloqueia cliques quando invisível */
    transition: opacity 0.3s ease;
}

/* Estado VISÍVEL do Overlay */
.modal-overlay.modal-show {
    opacity: 1;
    pointer-events: auto;
}

.modal-content {
    background-color: #FFFFFF;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
    max-width: 80%;

    /* TRANSFORMAÇÃO INICIAL */
    transform: scale(0.8);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); /* Efeito "pulo" */
    width: 223px;
}

/* Estado VISÍVEL do Conteúdo */
.modal-overlay.modal-show .modal-content {
    transform: scale(1);
}

.modal-content h3 {
    color: var(--cor-frete-verde);
    font-size: 18px;
    margin-top: 10px;
}
.modal-content p {
    color: var(--cor-texto-secundario);
}
.modal-content button {
    margin-top: 20px;
    background-color: var(--cor-frete-verde);
    color: #FFFFFF;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    font-weight: 600;
    cursor: pointer;
}

/* Estilo do Ícone de Sucesso (Cupom) */
.modal-success-icon {
    width: 40px;
    height: 40px;
    margin-bottom: 15px;
    /* Cor do stroke do SVG (Ticket com check) */
    stroke: var(--cor-frete-verde);
    stroke-width: 1.5;
}
</style>

<main class="main-content-wrapper" id="visao-geral">

    <div class="product-carousel">
        <div class="carousel-track" id="carouselTrack">

            <?php
            $current_image_index = 1;
            foreach ($imagens as $url):
            ?>
                <div class="carousel-slide">
                    <img
                        src="<?php echo htmlspecialchars(trim($url)); ?>"
                        alt="Imagem <?php echo $current_image_index; ?> de <?php echo $nome_produto; ?>"
                    >
                </div>
            <?php
                $current_image_index++;
            endforeach;
            ?>

        </div>

        <span class="pagination-badge" id="paginationBadge">
            1/<?php echo $total_imagens; ?>
        </span>
    </div>

    <div class="price-bar-red">

        <div class="timer-container" id="offerTimer">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M8 16c3.314 0 6-2 6-5.5 0-1.5-.5-4-2.5-6 .25 1.5-1.25 2-1.25 2C11 4 9 .5 6 0c.357 2 .5 4-2 6-1.25 1-2 2.729-2 4.5C2 14 4.686 16 8 16m0-1c-1.657 0-3-1-3-2.75 0-.75.25-2 1.25-3C6.125 10 7 10.5 7 10.5c-.375-1.25.5-3.25 2-3.5-.179 1-.25 2 1 3 .625.5 1 1.364 1 2.25C11 14 9.657 15 8 15"/>
            </svg>
            <span>Oferta Relâmpago</span>
            <span class="timer-time">05:00</span>
        </div>
        <div class="price-details-top">

            <div class="price-left">
                <div class="discount-tag">
                    <span class="discount-percent">-<?php echo htmlspecialchars($desconto_percentual); ?>%</span>
                </div>

                <div class="current-price-group">
                    <span class="prefix"><?php echo htmlspecialchars($texto_prefixo_preco); ?></span>
                    <span class="price-value"><?php echo number_format($preco_atual, 2, ',', '.'); ?></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M4 4.85v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9z"/>
                        <path d="M1.5 3A1.5 1.5 0 0 0 0 4.5V6a.5.5 0 0 0 .5.5 1.5 1.5 0 1 1 0 3 .5.5 0 0 0-.5.5v1.5A1.5 1.5 0 0 0 1.5 13h13a1.5 1.5 0 0 0 1.5-1.5V10a.5.5 0 0 0-.5-.5 1.5 1.5 0 0 1 0-3A.5.5 0 0 0 16 6V4.5A1.5 1.5 0 0 0 14.5 3zM1 4.5a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v1.05a2.5 2.5 0 0 0 0 4.9v1.05a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-1.05a2.5 2.5 0 0 0 0-4.9z"/>
                    </svg>
                </div>
            </div>

            <div class="customer-tag">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 16c3.314 0 6-2 6-5.5 0-1.5-.5-4-2.5-6 .25 1.5-1.25 2-1.25 2C11 4 9 .5 6 0c.357 2 .5 4-2 6-1.25 1-2 2.729-2 4.5C2 14 4.686 16 8 16m0-1c-1.657 0-3-1-3-2.75 0-.75.25-2 1.25-3C6.125 10 7 10.5 7 10.5c-.375-1.25.5-3.25 2-3.5-.179 1-.25 2 1 3 .625.5 1 1.364 1 2.25C11 14 9.657 15 8 15"/>
                </svg>
                <span><?php echo htmlspecialchars($texto_novos_clientes); ?></span>
            </div>

        </div>

        <div class="price-details-bottom">
            <span class="old-price">
                R$ <?php echo number_format($preco_antigo, 2, ',', '.'); ?>
            </span>
            <span class="apenas-text">
                <?php echo htmlspecialchars($texto_apenas); ?>
            </span>
        </div>

    </div>
    <div class="info-bar installment-bar">
        <div class="info-icon-left">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v1h14V4a1 1 0 0 0-1-1zm13 4H1v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1z"/>
                <path d="M2 10a1 1 0 0 1 1-1h1a1 1 0 0 1 1 1v1a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1z"/>
            </svg>
            <span><?php echo htmlspecialchars($texto_parcelamento); ?></span>
        </div>
        <span class="chevron-right">></span>
    </div>

    <div class="info-bar coupon-bar">
        <div class="info-icon-left">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M4 4.85v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9z"/>
                <path d="M1.5 3A1.5 1.5 0 0 0 0 4.5V6a.5.5 0 0 0 .5.5 1.5 1.5 0 1 1 0 3 .5.5 0 0 0-.5.5v1.5A1.5 1.5 0 0 0 1.5 13h13a1.5 1.5 0 0 0 1.5-1.5V10a.5.5 0 0 0-.5-.5 1.5 1.5 0 0 1 0-3A.5.5 0 0 0 16 6V4.5A1.5 1.5 0 0 0 14.5 3zM1 4.5a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v1.05a2.5 2.5 0 0 0 0 4.9v1.05a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-1.05a2.5 2.5 0 0 0 0-4.9z"/>
            </svg>
            <span><?php echo htmlspecialchars($texto_cupom_desconto); ?></span>
        </div>
    </div>


    <div class="title-rating-block">
        <div class="title-line">
            <h1>
                <span class="campaign-tag"><?php echo htmlspecialchars($tag_campanha); ?></span>
                <?php echo $titulo_produto; ?>
            </h1>
            <span class="bookmark-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.482a.5.5 0 0 1 .554 0L13 15.566V2a1 1 0 0 0-1-1z"/>
                </svg>
            </span>
        </div>
        <div class="rating-line">
            <span class="star">★</span>
            <span><?php echo htmlspecialchars($rating); ?></span>
            <span class="rating-count">(<?php echo htmlspecialchars($rating_count); ?>)</span>
            <span class="sold-count"><?php echo htmlspecialchars($sold_count); ?> vendidos</span>
        </div>
    </div>

    <div class="shipping-block">
        <div class="shipping-left">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-truck" viewBox="0 0 16 16">
                <path d="M0 3.5A1.5 1.5 0 0 1 1.5 2h9A1.5 1.5 0 0 1 12 3.5V5h1.02a1.5 1.5 0 0 1 1.17.563l1.481 1.85a1.5 1.5 0 0 1 .329.938V10.5a1.5 1.5 0 0 1-1.5 1.5H14a2 2 0 1 1-4 0H5a2 2 0 1 1-3.998-.085A1.5 1.5 0 0 1 0 10.5zm1.294 7.456A2 2 0 0 1 4.732 11h5.536a2 2 0 0 1 .732-.732V3.5a.5.5 0 0 0-.5-.5h-9a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .294.456M12 10a2 2 0 0 1 1.732 1h.768a.5.5 0 0 0 .5-.5V8.35a.5.5 0 0 0-.11-.312l-1.48-1.85A.5.5 0 0 0 13.02 6H12zm-9 1a1 1 0 1 0 0 2 1 1 0 0 0 0-2m9 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2"/>
            </svg>
            <div>
                <div class="shipping-main-line">
                    <span class="free-shipping-tag"><?php echo htmlspecialchars($texto_frete_gratis); ?></span>
                    <span>Receba até <?php echo $data_entrega_formatada; ?></span>
                </div>
                <div class="shipping-details-line">
                    <span class="shipping-cost-old"><?php echo htmlspecialchars($texto_taxa_envio); ?></span>
                </div>
            </div>
        </div>
        <span class="chevron-right">></span>
    </div>

    <div class="customer-protection-block">
        <div class="protection-header">
            <div class="protection-header-title">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <span><?php echo htmlspecialchars($texto_protecao); ?></span>
            </div>
            <span class="chevron-right">></span>
        </div>

        <div class="protection-details-grid">
            <?php foreach ($vantagens as $vantagem): ?>
                <div class="protection-item">
                    <span class="check-mark">✓</span>
                    <span><?php echo htmlspecialchars($vantagem); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="offers-section">
        <h2><?php echo htmlspecialchars($titulo_ofertas); ?></h2>

        <div class="shipping-coupon-card">
            <div class="coupon-details">
                <h3><?php echo htmlspecialchars($titulo_cupom_envio); ?></h3>
                <p><?php echo htmlspecialchars($descricao_cupom); ?></p>
            </div>
            <button class="btn-resgatar" id="btnResgatarCupom">
                <?php echo htmlspecialchars($texto_resgatar); ?>
            </button>
        </div>
    </div>
    <div class="modal-overlay" id="successModal">
        <div class="modal-content">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="modal-success-icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" />
            </svg>
            <h3>Cupom Resgatado</h3>
            <p>O seu desconto de R$ 20 no frete foi aplicado à sua conta.</p>
            <button id="btnCloseModal">Entendi</button>
        </div>
    </div>


    </main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- ELEMENTOS GERAIS ---
    const carousel = document.querySelector('.product-carousel');
    const track = document.getElementById('carouselTrack');
    const badge = document.getElementById('paginationBadge');
    const totalSlides = <?php echo $total_imagens; ?>;

    // Timer elements
    const timerElement = document.querySelector('.timer-time');

    // --- ELEMENTOS DO MODAL DE SUCESSO (CUPOM) ---
    const btnResgatar = document.getElementById('btnResgatarCupom'); // Botão na Main Content
    const successModal = document.getElementById('successModal');
    const btnCloseModal = document.getElementById('btnCloseModal'); // Botão "Entendi"

    // --- ELEMENTOS DO PERFIL (UNIFICAÇÃO) ---
    const resgateButtonProfile = document.getElementById('resgateButton'); // Botão do Perfil
    const openStoreModalBtn = document.getElementById('openStoreModalBtn'); // Botão "Visitar"

    // --- VARIÁVEIS DE ESTADO ---
    let slideWidth = carousel ? carousel.clientWidth : 0;
    let currentSlide = 0;
    let isDragging = false;
    let startPos = 0;
    let currentTranslate = 0;
    let prevTranslate = 0;
    let animationID;
    let autoPlayInterval;
    let countdownTimerId;
    let resgateProfileListenerAttached = false; // Flag para o listener do perfil

    // --- FUNÇÕES DE LÓGICA DO CARROSSEL (MANTIDAS) ---

    function moveToSlide(index) {
        if (!track || !carousel) return;

        if (index >= totalSlides) { index = 0; }
        else if (index < 0) { index = totalSlides - 1; }

        if (!isDragging) { track.style.transition = 'transform 0.3s ease-in-out'; }

        currentSlide = index;
        currentTranslate = currentSlide * -slideWidth;
        setTransform(track, currentTranslate);
        updateBadge();
    }

    function setTransform(element, translate) {
        element.style.transform = `translateX(${translate}px)`;
    }

    function updateBadge() {
        if (badge) badge.textContent = `${currentSlide + 1}/${totalSlides}`;
    }

    // --- AUTOPLAY (MANTIDO) ---

    function startAutoPlay() {
        if (!carousel) return;
        clearInterval(autoPlayInterval);
        autoPlayInterval = setInterval(() => {
            moveToSlide(currentSlide + 1);
        }, 3000);
    }

    function stopAutoPlay() {
        clearInterval(autoPlayInterval);
    }

    // --- LÓGICA DO TIMER (MANTIDA) ---

    function startCountdown() {
        if (!timerElement) return;
        let duration = 5 * 60;
        clearInterval(countdownTimerId);
        const timerContainer = document.getElementById('offerTimer');

        function updateTimer() {
            if (duration < 0) {
                clearInterval(countdownTimerId);
                if (timerContainer) {
                     timerContainer.classList.add('expired');
                     timerContainer.innerHTML = `<span>Oferta Encerrada</span>`;
                }
                return;
            }

            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            const display = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            timerElement.textContent = display;
            duration--;
        }

        updateTimer();
        countdownTimerId = setInterval(updateTimer, 1000);
    }

    // --- LÓGICA DO POP-UP (CUPOM) UNIFICADA E CORRIGIDA ---

    /**
     * @function hideModal
     * @description Oculta o modal de sucesso, garantindo o fechamento robusto.
     */
    const hideModal = () => {
        if (!successModal) return;

        successModal.classList.remove('modal-show'); // Usa a classe CSS original

        // CORREÇÃO: Remove a opacidade e força display: none após a transição
        successModal.style.opacity = '0';
        setTimeout(() => {
            successModal.style.display = 'none';
        }, 300);

        if (carousel) {
             startAutoPlay();
        }
    };

    /**
     * @function showModal
     * @description Exibe o modal de sucesso, garantindo a abertura robusta.
     */
    const showModal = () => {
        if (!successModal) return;

        if (carousel) {
            stopAutoPlay();
        }

        // CORREÇÃO: Força display: flex, aplica a opacidade e a classe de transição
        successModal.style.display = 'flex';
        successModal.style.opacity = '1';
        successModal.classList.add('modal-show');
    };

    /**
     * @function attachResgateProfileListener
     * @description Anexa o listener ao botão de resgate do perfil, se existir.
     */
    const attachResgateProfileListener = () => {
        const resgateButtonProfileElement = document.getElementById('resgateButton');

        // ANEXA O LISTENER DE CLIQUE, CHAMANDO showModal
        if (resgateButtonProfileElement && !resgateProfileListenerAttached) {
            resgateButtonProfileElement.addEventListener('click', showModal);
            resgateProfileListenerAttached = true;
        }
    };

    // --- EVENT LISTENERS UNIFICADOS DO MODAL ---

    // 1. Ouvinte para o botão de resgate da MAIN CONTENT (CORRIGIDO)
    if (btnResgatar) {
        btnResgatar.addEventListener('click', showModal);
    }

    // 2. Fechar o modal ao clicar no botão "Entendi" (CORRIGIDO)
    if (btnCloseModal) {
        btnCloseModal.addEventListener('click', (e) => {
            e.stopPropagation();
            hideModal();
        });
    }

    // 3. Fechar o modal ao clicar fora (no overlay) (CORRIGIDO)
    if (successModal) {
        successModal.addEventListener('click', (e) => {
            if (e.target.id === 'successModal') { // Usa o ID do overlay
                hideModal();
            }
        });
    }

    // 4. CONECTA O LISTENER DO BOTÃO DE RESGATE DO PERFIL
    // Se o modal do perfil existir, anexamos o listener na abertura dele.
    if (openStoreModalBtn) {
        openStoreModalBtn.addEventListener('click', () => {
             // Damos um pequeno tempo para o modal do perfil carregar o botão #resgateButton
             setTimeout(attachResgateProfileListener, 50);
        });
    }

    // --- INTERATIVIDADE (ARRASTAR/SWIPE - MANTIDO) ---

    if (carousel) {
        carousel.addEventListener('mousedown', dragStart);
        carousel.addEventListener('touchstart', dragStart);
        carousel.addEventListener('mousemove', drag);
        carousel.addEventListener('touchmove', drag);
        carousel.addEventListener('mouseup', dragEnd);
        carousel.addEventListener('touchend', dragEnd);
        carousel.addEventListener('mouseleave', dragEnd);

        window.addEventListener('resize', () => {
            slideWidth = carousel.clientWidth;
            moveToSlide(currentSlide);
        });
    }

    function dragStart(event) {
        if (!carousel || (successModal && successModal.classList.contains('modal-show'))) return;

        event.preventDefault();
        event.stopPropagation();

        stopAutoPlay();

        if (track) track.style.transition = 'none';
        if (track) track.classList.add('dragging');

        isDragging = true;
        startPos = event.type.includes('mouse') ? event.pageX : event.touches[0].clientX;
        prevTranslate = currentTranslate;
        animationID = requestAnimationFrame(animation);
    }

    function drag(event) {
        if (!isDragging) return;

        const currentPosition = event.type.includes('mouse') ? event.pageX : event.touches[0].clientX;
        const distance = currentPosition - startPos;
        currentTranslate = prevTranslate + distance;
    }

    function animation() {
        if (track) setTransform(track, currentTranslate);
        if (isDragging) requestAnimationFrame(animation);
    }

    function dragEnd(event) {
        if (!isDragging) return;

        cancelAnimationFrame(animationID);
        isDragging = false;
        if (track) track.classList.remove('dragging');

        const movedBy = currentTranslate - prevTranslate;
        const threshold = slideWidth * 0.2;

        if (movedBy < -threshold) {
            moveToSlide(currentSlide + 1);
        } else if (movedBy > threshold) {
            moveToSlide(currentSlide - 1);
        } else {
            moveToSlide(currentSlide);
        }

        startAutoPlay();
    }

    // --- INICIALIZAÇÃO GERAL ---
    if (carousel) {
        startAutoPlay();
    }
    if (timerElement) {
        startCountdown();
    }
    // Tenta anexar o listener do perfil na inicialização como fallback
    attachResgateProfileListener();
});
</script>