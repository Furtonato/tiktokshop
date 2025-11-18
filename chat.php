<?php
// Ficheiro: chat.php - Interface do Chatbot e Histórico de Mensagens (V18: Correção 100svh iOS)

// NOTA: O 'session_id' é crucial para identificar a conversa.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Assumimos que a conexão $pdo já está disponível no escopo de inclusão (index.php)
if (!isset($pdo)) {
    // Fallback de segurança se $pdo não estiver definido
    include 'db_config.php';
}


// O ID da sessão atual para rastrear as mensagens deste usuário
$session_id = session_id();
$product_id = $_GET['product_id'] ?? '1';

// --- 1. BUSCA DE DADOS REAIS DO PRODUTO E VENDEDOR (DB) ---
$product_data = [
    'nome' => "Produto Desconhecido",
    'preco_atual' => 0.00,
    'preco_antigo' => null,
    'imagem_principal' => "url_default_produto.jpg",
    'nome_vendedor' => "OficialWebshop",
    'url_logo_vendedor' => "https://via.placeholder.com/30/000000/FFFFFF?text=L", // Placeholder Logo
];

try {
    $stmt_product = $pdo->prepare("
        SELECT nome, preco_atual, preco_antigo, imagem_principal, nome_vendedor, url_logo_vendedor
        FROM public.produtos
        WHERE id = :product_id
    ");
    $stmt_product->execute([':product_id' => $product_id]);
    $db_data = $stmt_product->fetch(PDO::FETCH_ASSOC);

    if ($db_data) {
        $product_data = array_merge($product_data, $db_data);
    }
} catch (PDOException $e) {
    error_log("Erro ao buscar dados do produto/vendedor no chat: " . $e->getMessage());
}

// Formatação dos dados (Trata preco_antigo nulo ou 0)
$product_price_formatted = 'R$ ' . number_format($product_data['preco_atual'], 2, ',', '.');
$preco_antigo_valido = is_numeric($product_data['preco_antigo']) && $product_data['preco_antigo'] > $product_data['preco_atual'];
$product_old_price_formatted = $preco_antigo_valido ? ('R$ ' . number_format($product_data['preco_antigo'], 2, ',', '.')) : null;

$product_name_html = htmlspecialchars($product_data['nome']);
$product_thumb_url = htmlspecialchars($product_data['imagem_principal']);
$vendedor_name = htmlspecialchars($product_data['nome_vendedor']);
$vendedor_logo = htmlspecialchars($product_data['url_logo_vendedor']);


// --- 2. LÓGICA DE PERSISTÊNCIA NA SESSÃO ---
$faq_options = [
    'Você tem esse produto em estoque?',
    'Estou tentando comprar',
    'Já paguei',
    'Como faço para usar?',
    'O que está incluído no produto?',
];

$initial_bot_message = "Olá, tudo bem? A {$vendedor_name} agradece o seu contato! Logo mais atenderemos você! Enquanto isso, como podemos ajudar você hoje?";

if (!isset($_SESSION['chat_history']) || empty($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [
        ['sender_type' => 'bot', 'message_content' => $initial_bot_message]
    ];
}

$chat_history = $_SESSION['chat_history'];

// --- FIM DA LÓGICA PHP ---
?>

<div id="chat-container">

    <header class="chat-header">
        <div class="chat-header-icons">
            <a href="index.php" class="chat-icon-btn close-btn" aria-label="Fechar Chat">
                &lt; </a>

            <div class="shop-info">
                <img src="<?php echo $vendedor_logo; ?>" alt="Logo Oficial" class="shop-logo">
                <div class="shop-details">
                    <span class="shop-name"><?php echo $vendedor_name; ?></span>
                    <span class="shop-status">Normalmente responde em algumas horas</span>
                </div>
            </div>
        </div>
        <div class="chat-header-icons right-icons">
            <button class="chat-icon-btn bell-icon" aria-label="Notificações">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bell" viewBox="0 0 16 16">
                    <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4 4 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4 4 0 0 0-3.203-3.92zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5 5 0 0 1 13 6c0 .88.32 4.2 1.22 6"/>
                </svg>
            </button>
            <button class="chat-icon-btn three-dots" aria-label="Mais Opções">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-three-dots" viewBox="0 0 16 16">
                    <path d="M3 9.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3m5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3"/>
                </svg>
            </button>
        </div>
    </header>

    <div class="chat-messages-area" id="chat-messages-area">

        <?php foreach ($chat_history as $message): ?>
            <?php
            $is_bot = ($message['sender_type'] === 'bot');
            $content_to_render = $message['message_content'];
            $is_html_card = (strpos($content_to_render, '<div style=') !== false);
            ?>
            <div class="message-row <?php echo $is_bot ? 'bot-message' : 'user-message'; ?>">
                <?php if ($is_bot): ?>
                    <img src="<?php echo $vendedor_logo; ?>" alt="Bot Avatar" class="message-avatar bot-avatar">
                <?php else: ?>
                    <div class="message-avatar user-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                            <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"></path>
                        </svg>
                    </div>
                <?php endif; ?>

                <div class="message-bubble">
                    <?php
                    if ($is_html_card) {
                        echo $content_to_render;
                    } else {
                        // Usamos nl2br e htmlspecialchars para formatar corretamente o texto e evitar XSS
                        echo nl2br(htmlspecialchars($content_to_render));
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php
        $last_message = end($chat_history);
        if (isset($last_message['message_content']) && $last_message['message_content'] === $initial_bot_message):
        ?>
            <div class="faq-section" id="faq-section">
                <div class="faq-question">Como posso ajudar você hoje?</div>

                <div class="faq-options-container">
                    <?php foreach ($faq_options as $option): ?>
                        <button class="faq-option-btn" data-question="<?php echo htmlspecialchars($option); ?>">
                            <?php echo htmlspecialchars($option); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="chatbot-label">Enviado por chatbot</div>
            </div>
        <?php endif; ?>

    </div>

    <div id="product-collapse-bar" class="product-collapse-bar initial-hidden">
        <div class="product-details-bar" id="product-details-bar">
            <button class="toggle-product-btn" id="toggleProductBtn" aria-label="Mostrar/Esconder Produto">
                <span class="arrow-icon">&lt;</span>
            </button>

            <img src="<?php echo $product_thumb_url; ?>" alt="Imagem do Produto" class="product-thumb">
            <div class="product-info">
                <span class="product-name"><?php echo $product_name_html; ?></span>
                <span class="product-price">
                    <?php echo $product_price_formatted; ?>
                    <?php if ($product_old_price_formatted): ?>
                        <del style="color:#888; font-weight:normal; margin-left:5px;"><?php echo $product_old_price_formatted; ?></del>
                    <?php endif; ?>
                </span>
            </div>
            <button class="send-button-product" id="btnSendProduct">Enviar</button>
        </div>
    </div>

    <footer class="chat-footer">
        <div class="input-area">
            <input type="text" id="chat-input" placeholder="Enviar mensagem...">
            <button id="send-btn" aria-label="Enviar Mensagem">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-send" viewBox="0 0 16 16">
                    <path d="M15.854.146a.5.5 0 0 1 .11.54l-5.819 14.547a.75.75 0 0 1-1.329.124l-3.178-4.995L.643 7.184a.75.75 0 0 1 .124-1.33L15.314.037a.5.5 0 0 1 .54.11ZM6.636 10.07l2.761 4.338L14.13 2.576zm6.787-8.201L1.591 6.602l4.339 2.76z"/>
                </svg>
            </button>
        </div>
    </footer>
</div>


<style>
/* ========================================= */
/* --- CSS: CHAT LAYOUT (COMPLETO) --- */
/* ========================================= */
#chat-container {
    position: fixed; top: 0; right: 0; width: 100%;
    /* 🌟 MUDANÇA 2: Trocado 100vh por 100svh (Small Viewport Height) */
    /* Isso força o container a ter o tamanho da área VISÍVEL, acima da barra do navegador iOS */
    height: 100svh;
    max-width: none; max-height: none;
    background-color: #f7f7f7; z-index: 1000; box-shadow: none; display: flex; flex-direction: column;
    font-family: Arial, sans-serif; border-radius: 0; overflow: hidden;
}

/* --- HEADER --- */
.chat-header { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; background-color: #FFFFFF; border-bottom: 1px solid #ddd; flex-shrink: 0; border-radius: 25px; }
.chat-icon-btn {
    background: none; border: none; font-size: 1.2em; cursor: pointer; color: #000;
    /* 🌟 MUDANÇA 3: Garantir que o link <a> não tenha sublinhado */
    text-decoration: none;
}
.chat-icon-btn.close-btn { font-size: 1.5em; font-weight: bold; }
.shop-info { display: flex; align-items: center; margin-left: 42px; margin-top: -24px;}
.shop-logo { width: 30px; height: 30px; border-radius: 50%; margin-right: 8px; object-fit: cover; }
.shop-details { display: flex; flex-direction: column; text-align: left; }
.shop-name { font-weight: bold; font-size: 14px; }
.shop-status { font-size: 11px; color: #888; }
.chat-icon-btn svg { width: 24px; height: 24px; }


/* --- ÁREA DE MENSAGENS (Avatares) --- */
.chat-messages-area {
    flex-grow: 1; padding: 15px 10px; overflow-y: auto; background-color: #f7f7f7; display: flex; flex-direction: column;
    /* Mantemos o padding de 120px para a barra do produto e footer */
    padding-bottom: 120px;
}
.message-row { display: flex; margin-bottom: 10px; align-items: flex-start; }
.message-row.bot-message { justify-content: flex-start; padding-left: 5px; }
.message-row.user-message { justify-content: flex-end; padding-right: 5px; }

/* Avatares */
.message-avatar { width: 25px; height: 25px; border-radius: 50%; object-fit: cover; flex-shrink: 0; }
.message-avatar svg {
    /* Define a cor do ícone, se necessário */
    color: #ffffffff; /* Cor do TikTok */
    /* Garante que o ícone esteja centralizado */
    display: block;
}
.bot-avatar { order: -1; margin-right: 8px; }
.user-avatar { order: 1; margin-left: 8px; background-color: #fe2c56de; display: flex; justify-content: center; align-items: center; color: #fff; font-size: 14px; }

.message-bubble { padding: 8px 12px; border-radius: 18px; max-width: 80%; font-size: 14px; line-height: 1.4; word-wrap: break-word; }
.bot-message .message-bubble { background-color: #E2E2E2; color: #000; border-top-left-radius: 5px; }
.user-message .message-bubble { background-color: #FE2C55; color: #FFFFFF; border-top-right-radius: 5px; }


/* --- SEÇÃO FAQ / OPÇÕES RÁPIDAS (ESTILIZADA) --- */
.faq-section { padding: 10px; background-color: #FFFFFF; border-radius: 10px; margin-top: 15px; align-self: flex-start; max-width: 80%; }
.faq-question { font-weight: bold; margin-bottom: 10px; font-size: 14px; }

/* Contêiner de Agrupamento para Botões (CRÍTICO para estilo em linha) */
.faq-options-container {
    display: flex;
    flex-wrap: wrap;
    gap: 8px; /* Espaço entre os botões */
    margin-top: 5px;
}
.faq-option-btn, .faq-option-btn-reinserted {
    background-color: #f0f0f0;
    color: #000;
    border: 1px solid #E2E2E2;
    border-radius: 15px;
    padding: 8px 12px;
    font-size: 13px;
    cursor: pointer;
    flex-grow: 0;
    flex-shrink: 0;
    text-align: center;
    display: inline-block;
    width: auto;
}

.chatbot-label { font-size: 10px; color: #888; text-align: right; margin-top: 5px; }


/* --- FOOTER E INPUT (CAMADA Z: 1002) --- */
.chat-footer {
    flex-shrink: 0; background-color: #FFFFFF; position: absolute; bottom: 0; width: 100%;
    z-index: 1002; /* Garante que fique acima da barra de produto */
    border-top: 1px solid #ddd;
    /* Mantemos o padding da safe-area para o "home bar" do iPhone */
    padding-bottom: env(safe-area-inset-bottom);
    border-radius: 18px;
}
.input-area { display: flex; padding: 8px 10px; gap: 5px; align-items: center; }
#chat-input { flex-grow: 1; padding: 10px 15px; border: 1px solid #ccc; border-radius: 20px; font-size: 14px; }
#send-btn { background: none; border: none; cursor: pointer; padding: 5px; }
#send-btn svg { width: 24px; height: 24px; color: #20b8f0; }


/* --- ESTILOS DO CARTÃO COLAPSÁVEL (SLIDE LATERAL, CAMADA Z: 1001) --- */
.product-collapse-bar {
    --product-bar-width: 340px; /* Largura para acomodar o texto */
    --product-bar-height: 60px;
    --visible-height: 25px; /* Altura da seta + borda visível */
    position: absolute;
    right: 0;
    top: 62%;
    width: var(--product-bar-width);
    height: 60px;
    transform: translate(0, -50%); /* Padrão: Aberto */
    background-color: #FFFFFF;
    transition: transform 0.3s ease-in-out;
    z-index: 1001;
    box-shadow: -2px 0 5px rgba(0,0,0,0.1);
    border-radius: 8px 0 0 8px;
}
/* Estado Fechado/Colapsado */
.product-collapse-bar.initial-hidden {
    /* Move o cartão para a direita, deixando apenas 20px visíveis (a seta) */
    transform: translate(calc(var(--product-bar-width) - 20px), -50%);
}

/* Botão de Toggle (Seta) */
.toggle-product-btn {
    z-index: 1003; /* CRÍTICO: Acima de TUDO para ser clicável */
    background-color: #FFF;
    border: 1px solid #ccc;
    border-right: none;
    cursor: pointer;
    position: absolute;
    left: -20px; /* Posiciona o botão na Borda esquerda visível */
    top: 50%;
    transform: translateY(-50%);
    padding: 0 5px;
    height: 40px;
    width: 25px;
    border-radius: 8px 0 0 8px;
    box-shadow: -2px 0 5px rgba(0,0,0,0.1);
}
.toggle-product-btn .arrow-icon {
    display: inline-block; transition: transform 0.3s ease-in-out;
    transform: rotate(0deg); /* Colapsado: Seta para a esquerda */
    font-size: 18px;
    color: #000;
}
.product-collapse-bar.initial-hidden .arrow-icon {
    transform: rotate(180deg); /* Colapsado: Seta para a direita */
}

/* Detalhes do Produto - Layout interno */
.product-details-bar {
    display: flex; align-items: flex-start; /* CRÍTICO: Alinha o conteúdo ao topo para permitir múltiplas linhas */
    padding: 8px 10px;
    gap: 10px;
    height: 100%;
    width: calc(100% - 25px);
    padding-left: 15px;
}
.product-thumb { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; }
.product-info {
    flex-grow: 1; text-align: left; display: flex; flex-direction: column; font-size: 12px;
    line-height: 1.2;
    max-height: 100%;
}
.product-name {
    font-weight: 500; color: #333; white-space: normal; font-size: 9px; /* Aumentado o tamanho para caber mais texto */
    overflow: hidden;
}
.product-price { font-weight: bold; color: #E63946; }
.send-button-product { background-color: #E63946; color: white; border: none; padding: 8px 15px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 14px; }

</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const chatArea = document.getElementById('chat-messages-area');
    const chatInput = document.getElementById('chat-input');
    const sendButton = document.getElementById('send-btn');
    const faqButtons = document.querySelectorAll('.faq-option-btn');
    const btnSendProduct = document.getElementById('btnSendProduct');

    // --- Variáveis de Dados e DOM ---
    const productName = "<?php echo $product_name_html; ?>";
    const vendedorLogo = "<?php echo $vendedor_logo; ?>";
    const collapseBar = document.getElementById('product-collapse-bar');
    const toggleBtn = document.getElementById('toggleProductBtn');

    // HTML do cartão do produto para ser enviado como mensagem de usuário
    const PRODUCT_INFO_HTML = '<div style="padding: 10px; background: #fff; border-radius: 8px; border: 1px solid #eee; max-width: 200px; display: flex; flex-direction: column; align-items: center; text-align: center;"><div style="font-weight: bold; margin-bottom: 5px; color: #333;">' + productName + '</div><img src="' + (document.querySelector('.product-thumb')?.src || '') + '" style="width: 80px; height: 80px; margin-top: 5px; border-radius: 4px; object-fit: cover;"><div style="font-weight: bold; color: #E63946; margin-top: 5px;">' + (document.querySelector('.product-price')?.textContent || '') + '</div><small style="color: #888;">Produto consultado.</small></div>';

    // Respostas pré-prontas para o chatbot
    const BOT_RESPONSES = {
        'Você tem esse produto em estoque?': "Sim, o **${productName}** está em estoque! Temos mais de 500 unidades prontas para envio imediato. Aproveite!",
        'Estou tentando comprar': "Para resolver qualquer problema na compra, pedimos que limpe o cache do navegador e tente novamente. Se o erro persistir, envie-nos um print da tela.",
        'Já paguei': "Ótimo! Para pedidos via PIX, a confirmação é imediata. Se foi via Boleto, pode levar até 3 dias úteis. Assim que aprovado, enviaremos o código de rastreio.",
        'Como faço para usar?': "Este produto é de uso intuitivo. No entanto, para mais detalhes de montagem ou uso, pedimos que consulte o manual digital disponível na sua caixa de e-mail.",
        'O que está incluído no produto?': "O produto inclui: A ${productName}, manual de instruções, e um kit de montagem completo com ferramentas necessárias. Nenhuma peça extra é necessária.",
    };

    const INITIAL_BOT_MESSAGE = "Olá, tudo bem? A **<?php echo $vendedor_name; ?>** agradece o seu contato! Logo mais atenderemos você! Enquanto isso, como podemos ajudar você hoje?";

    // 🌟 CORREÇÃO AQUI: O nome da variável foi corrigido de BOT_FALLGACK_MESSAGE
    const BOT_FALLBACK_MESSAGE = "Desculpe, não entendi. Por favor, selecione uma das opções abaixo para que eu possa te ajudar, ou tente ser mais específico. 👇";

    const scrollToBottom = () => {
        if (chatArea) {
            chatArea.scrollTop = chatArea.scrollHeight;
        }
    };

    scrollToBottom();

    // Função para adicionar a seção FAQ (Corrigida para usar a estrutura de agrupamento)
    const reinsertFAQ = (delay = 0) => {

          const faqOptions = [
              'Você tem esse produto em estoque?',
              'Estou tentando comprar',
              'Já paguei',
              'Como faço para usar?',
              'O que está incluído no produto?',
          ];

          // Gerar o HTML dos botões dentro do contêiner de agrupamento
          let optionsHtml = '';
          faqOptions.forEach(option => {
              optionsHtml += `<button class="faq-option-btn-reinserted" data-question="${option}">${option}</button>`;
          });

          const faqSectionHtml = `
              <div class="faq-section faq-reinserted" id="faq-reinserted">
                  <div class="faq-question">Como posso ajudar você hoje?</div>

                  <div class="faq-options-container">
                      ${optionsHtml}
                  </div>

                  <div class="chatbot-label">Enviado por chatbot</div>
              </div>
          `;


        setTimeout(() => {
            chatArea.insertAdjacentHTML('beforeend', faqSectionHtml);
            scrollToBottom();

            // Reanexa os listeners aos novos botões FAQ
            document.querySelectorAll('.faq-option-btn-reinserted').forEach(button => {
                button.addEventListener('click', (e) => {
                    const question = e.currentTarget.getAttribute('data-question');
                    processUserMessage(question);
                    document.getElementById('faq-reinserted')?.remove();
                });
            });
        }, delay);
    };


    // Função principal para adicionar mensagem ao DOM e persistir na sessão
    const addMessageToDOM = (senderType, content) => {
        const isBot = senderType === 'bot';

        // Renderização do Avatar
        const avatarHTML = isBot
            ? `<img src="${vendedorLogo}" alt="Bot Avatar" class="message-avatar bot-avatar">`
            : `<div class="message-avatar user-avatar">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-fill" viewBox="0 0 16 16">
                        <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6"></path>
                    </svg>
                </div>`;

        const messageRow = document.createElement('div');
        messageRow.className = `message-row ${isBot ? 'bot-message' : 'user-message'}`;

        const bubbleContent = document.createElement('div');
        bubbleContent.className = 'message-bubble';

        // Lógica de Renderização: Se contiver HTML, insere como HTML. Senão, como texto.
        if (content.includes('<div style=')) {
            bubbleContent.innerHTML = content;
        } else {
            // Se for texto, escapamos e convertemos quebras de linha
            const tempDiv = document.createElement('div');
            tempDiv.textContent = content;
            bubbleContent.innerHTML = tempDiv.innerHTML.replace(/\n/g, '<br>');
        }

        // Monta a estrutura da linha
        if (isBot) {
            messageRow.innerHTML = avatarHTML;
            messageRow.appendChild(bubbleContent);
        } else {
            messageRow.appendChild(bubbleContent);
            messageRow.innerHTML += avatarHTML;
        }

        chatArea.appendChild(messageRow);
        scrollToBottom();

        // 4. PERSISTÊNCIA NA SESSÃO (AJAX Helper)
        fetch('save_chat_session.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sender_type: senderType, message_content: content })
        }).catch(err => console.error("Falha ao salvar a mensagem na sessão:", err));
    };

    const processUserMessage = (content, isHTML = false) => {
        if (content.trim() === "" && !isHTML) return;

        // 1. Adiciona a mensagem do usuário
        addMessageToDOM('user', content);

        // Remove FAQ se estiver na tela
        document.querySelector('.faq-section')?.remove();
        document.getElementById('faq-reinserted')?.remove(); // Remove FAQ re-inserido

        // 2. Tenta encontrar uma resposta pré-pronta do bot
        const botQuestion = isHTML ? 'O que está incluído no produto?' : content.trim();
        const botResponseTemplate = BOT_RESPONSES[botQuestion];

        setTimeout(() => {
            if (botResponseTemplate) {
                // 3a. Resposta encontrada
                const botResponse = botResponseTemplate.replace(/\$\{productName\}/g, productName);
                addMessageToDOM('bot', botResponse);
            } else {
                // 3b. Fallback: Responde com a mensagem de fallback e reexibe as opções
                // Agora isto irá funcionar:
                addMessageToDOM('bot', BOT_FALLBACK_MESSAGE);
                reinsertFAQ(300); // Reexibe as opções com um pequeno delay
            }
        }, 500);
    };

    // --- Listener de Colapso do Cartão do Produto ---
    if (toggleBtn && collapseBar) {
        // Estado inicial: Colapsado
        collapseBar.classList.add('initial-hidden');

        toggleBtn.addEventListener('click', () => {
            // Alterna a classe 'initial-hidden' (o CSS cuida da transição)
            collapseBar.classList.toggle('initial-hidden');
        });
    }

    // a) Envio por Input de Texto
    if (sendButton) {
        sendButton.addEventListener('click', () => {
            processUserMessage(chatInput.value);
            chatInput.value = "";
        });
    }

    // b) Envio pela tecla Enter
    if (chatInput) {
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                processUserMessage(chatInput.value);
                chatInput.value = "";
            }
        });
    }

    // c) Envio pelos botões FAQ
    faqButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            const question = e.currentTarget.getAttribute('data-question');
            processUserMessage(question);
        });
    });

    // --- Envio do Produto no Chat (Botão do Footer) ---
    if (btnSendProduct) {
        btnSendProduct.addEventListener('click', () => {
            // Passamos o HTML do produto e o booleano 'true' para isHTML
            processUserMessage(PRODUCT_INFO_HTML, true);
        });
    }
});
</script>