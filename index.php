<?php
// Ficheiro: index.php (PÚBLICO, na raiz)
// Responsável por: Bloqueio de PC, Lógica de View e Estrutura Geral do Site

// PASSO 1: Ligar ao Banco de Dados
// A conexão $pdo deve ser incluída para que as outras páginas funcionem
include 'db_config.php';

// Inicia a sessão para o rastreamento do chat (session_id)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PASSO 2: Capturar a view (página a ser exibida)
$view = $_GET['view'] ?? 'main';

// =====================================================================
// CORREÇÃO: DEFINIÇÃO DE VARIÁVEIS ANTES DE QUALQUER LÓGICA CONDICIONAL
// =====================================================================

// Define qual conteúdo incluir
$show_main_content = ($view === 'main');
$show_pay_content = ($view === 'pay');

// =====================================================================
// LÓGICA DE BLOQUEIO DE PC E REDIRECIONAMENTO
// =====================================================================

// 1. Array de Dispositivos Móveis
$dispositivos_moveis = [
    'iPhone', 'iPod', 'iPad', 'Android', 'BlackBerry',
    'Opera Mini', 'webOS', 'Mobile', 'Windows Phone', 'Tablet',
    'Symbian', 'KFAPWI',
];

// 2. Array de Sites de Redirecionamento Aleatório
$sites_aleatorios = [
    'https://www.google.com',
    'https://www.microsoft.com',
    'https://www.wikipedia.org',
];

$user_agent = $_SERVER['HTTP_USER_AGENT'];
$e_dispositivo_movel = false;

// 3. Verificação do User Agent
foreach ($dispositivos_moveis as $dispositivo) {
    if (stripos($user_agent, $dispositivo) !== false) {
        $e_dispositivo_movel = true;
        break;
    }
}

// 4. Lógica de Redirecionamento
if (!$e_dispositivo_movel) {
    $indice_aleatorio = array_rand($sites_aleatorios);
    $url_redirecionamento = $sites_aleatorios[$indice_aleatorio];

    header('Location: ' . $url_redirecionamento);
    exit();
}

// =====================================================================
// FIM DO BLOQUEIO
// =====================================================================

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $show_pay_content ? 'Resumo do Pedido' : 'Minha Loja'; ?></title>

    <style>
        /* ===================================================== */
        /* --- ESTILOS GERAIS E FOOTER FIXO --- */
        /* ===================================================== */

        :root {
            --footer-height: 70px;
            --cor-texto-principal: #222222;
            --cor-texto-secundario: #555555;
            --cor-vermelha-principal: #FF3B30;
            --header-max-width: 768px;
        }

        /* O FOOTER FIXO */
        .fixed-footer-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            height: var(--footer-height);

            /* Centraliza em telas maiores */
            max-width: var(--header-max-width);
            transform: translateX(-50%);
            left: 50%;

            background-color: #fff;
            border-top: 1px solid #eee;
            display: flex;
            align-items: center;
            padding: 5px 10px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.08);
            z-index: 1000;
        }

        /* Links dos Ícones (Loja e Chat) */
        .fixed-footer-bar .icon-link {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-basis: 45px;
            font-size: 11px;
            color: var(--cor-texto-secundario);
            gap: 4px;
            text-decoration: none;
            text-align: center;
            line-height: 1.2;
            padding: 0 2px;
        }
        .fixed-footer-bar .icon-link svg {
            width: 20px;
            height: 20px;
            color: var(--cor-texto-principal);
        }

        /* Grupo de Botões de Ação */
        .fixed-footer-bar .button-group {
            flex: 1;
            display: flex;
            gap: 16px;
        }

        /* Estilo base dos botões */
        .fixed-footer-bar .button-group button {
            flex: 1;
            border: none;
            border-radius: 5px;
            font-size: 17px;
            font-weight: 550;
            cursor: pointer;
            height: 48px;
            transition: all 0.3s ease;
            margin-right: 14px;
        }

        /* Botão "Comprar com cupom" (Fundo Vermelho) */
        .fixed-footer-bar .btn-buy-coupon {
            background-color: #ff2b54;
            color: #fff;
        }
        /* Botão "Adicionar ao carrinho" (Texto Preto) */
        .fixed-footer-bar .btn-add-cart {
            color: #000; /* Define a cor do texto como preta */
        }

        /* Otimização: Ajusta o body para garantir espaço para o footer */
        body {
            padding-bottom: calc(var(--footer-height) + 10px);
            margin: 0;
            background-color: #F4F4F4;
        }

        /* NOVO: Classe para travar o scroll do body quando o chat está aberto */
        body.scroll-lock {
            overflow: hidden !important;
            height: 100vh;
        }


        /* ===================================================== */
        /* --- CHAT SLIDE-IN CSS --- */
        /* ===================================================== */
        #chat-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100vh;
            z-index: 2000;

            /* Configuração inicial: FORA da tela, à direita */
            transform: translateX(100%);
            transition: transform 0.3s ease-out; /* Efeito de deslize suave */
        }

        #chat-wrapper.open {
            /* Estado ativo: Desliza para dentro */
            transform: translateX(0);
        }

        /* --- CORREÇÃO PARA A VIEW DE CHECKOUT (PAY) --- */
        <?php if ($show_pay_content): ?>
        .fixed-footer-bar {
            display: none !important;
        }
        body {
            padding-bottom: 0 !important;
            background-color: #F8F8F8;
        }
        <?php endif; ?>
    </style>
</head>
<body>

    <?php if ($show_main_content): ?>
        <?php
            // Inclui as seções da página de produto
            include 'header.php';
            include 'main_content.php';
            include 'createcontent.php';
            include 'rating.php';
            include 'profile.php';
            include 'info.php';
        ?>

        <footer class="fixed-footer-bar">

            <a href="#" class="icon-link">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-shop-window" viewBox="0 0 16 16">
                    <path d="M2.97 1.35A1 1 0 0 1 3.73 1h8.54a1 1 0 0 1 .76.35l2.609 3.044A1.5 1.5 0 0 1 16 5.37v.255a2.375 2.375 0 0 1-4.25 1.458A2.37 2.37 0 0 1 9.875 8 2.37 2.37 0 0 1 8 7.083 2.37 2.37 0 0 1 6.125 8a2.37 2.37 0 0 1-1.875-.917A2.375 2.375 0 0 1 0 5.625V5.37a1.5 1.5 0 0 1 .361-.976zm1.78 4.275a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0 1.375 1.375 0 1 0 2.75 0V5.37a.5.5 0 0 0-.12-.325L12.27 2H3.73L1.12 5.045A.5.5 0 0 0 1 5.37v.255a1.375 1.375 0 0 0 2.75 0 .5.5 0 0 1 1 0M1.5 8.5A.5.5 0 0 1 2 9v6h12V9a.5.5 0 0 1 1 0v6h.5a.5.5 0 0 1 0 1H.5a.5.5 0 0 1 0-1H1V9a.5.5 0 0 1 .5-.5m2 .5a.5.5 0 0 1 .5.5V13h8V9.5a.5.5 0 0 1 1 0V13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5a.5.5 0 0 1 .5-.5m2 .5a.5.5 0 0 1 .5.5V13h8V9.5a.5.5 0 0 1 1 0V13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5a.5.5 0 0 1 .5-.5m2 .5a.5.5 0 0 1 .5.5V13h8V9.5a.5.5 0 0 1 1 0V13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5a.5.5 0 0 1 .5-.5m2 .5a.5.5 0 0 1 .5.5V13h8V9.5a.5.5 0 0 1 1 0V13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9.5a.5.5 0 0 1 .5-.5"/>
                </svg>
                <span>Loja</span>
            </a>

            <a href="#" class="icon-link" id="btnOpenChat">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-chat" viewBox="0 0 16 16">
                    <path d="M2.678 11.894a1 1 0 0 1 .287.801 11 11 0 0 1-.398 2c1.395-.323 2.247-.697 2.634-.893a1 1 0 0 1 .71-.074A8 8 0 0 0 8 14c3.996 0 7-2.807 7-6s-3.004-6-7-6-7 2.808-7 6c0 1.468.617 2.83 1.678 3.894m-.493 3.905a22 22 0 0 1-.713.129c-.2.032-.352-.176-.273-.362a10 10 0 0 0 .244-.637l.003-.01c.248-.72.45-1.548.524-2.319C.743 11.37 0 9.76 0 8c0-3.866 3.582-7 8-7s8 3.134 8 7-3.582 7-8 7a9 9 0 0 1-2.347-.306c-.52.263-1.639.742-3.468 1.105"/>
                </svg>
                <span>Chat</span>
            </a>

            <div class="button-group">
                <button class="btn-add-cart" id="btnAddToCart">Adicionar ao carrinho</button>
                <button class="btn-buy-coupon" id="btnBuyWithCoupon">Comprar com cupom</button>
            </div>
        </footer>

        <div id="chat-wrapper">
            <?php include 'chat.php'; ?>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const btnAddToCart = document.getElementById('btnAddToCart');
                const btnBuyWithCoupon = document.getElementById('btnBuyWithCoupon');
                const btnOpenChat = document.getElementById('btnOpenChat');
                const chatWrapper = document.getElementById('chat-wrapper');
                const body = document.body;

                // URL de destino
                const checkoutUrl = 'index.php?view=pay';

                // Função de redirecionamento
                const redirectToCheckout = () => {
                    window.location.href = checkoutUrl;
                };

                // Anexa listeners aos botões de compra
                if (btnAddToCart) {
                    btnAddToCart.addEventListener('click', redirectToCheckout);
                }

                if (btnBuyWithCoupon) {
                    btnBuyWithCoupon.addEventListener('click', redirectToCheckout);
                }

                // --- LÓGICA DO BOTÃO ABRIR CHAT (Slide-in) ---
                if (btnOpenChat && chatWrapper) {
                    btnOpenChat.addEventListener('click', (e) => {
                        e.preventDefault();
                        // 1. Abre o chat
                        chatWrapper.classList.add('open');
                        // 2. Trava o scroll do body (CRÍTICO para corrigir o bug de layout)
                        body.classList.add('scroll-lock');

                        // Opcional: foca o input de texto do chat
                        setTimeout(() => {
                           document.getElementById('chat-input')?.focus();
                        }, 350);
                    });
                }

                // --- LÓGICA DO BOTÃO FECHAR CHAT no chat.php ---
                // Nota: O botão de fechar no chat.php deve ter a função 'onclick' no elemento
                // para remover a classe 'open' E 'scroll-lock'.

                const btnCloseChat = document.querySelector('#chat-wrapper .close-btn');

                if (btnCloseChat) {
                    // Substitui a função de recarregar por uma função de fechamento suave
                    btnCloseChat.onclick = function(e) {
                         // e.preventDefault(); // Comentado, pois você deseja recarregar a página
                         // O fechamento via recarga é feito no chat.php

                         // Se você quisesse um fechamento suave sem recarregar, faria:
                         // chatWrapper.classList.remove('open');
                         // body.classList.remove('scroll-lock');
                    };
                }


            });
        </script>


    <?php elseif ($show_pay_content): ?>
        <?php
            // Inclui a seção de pagamento/checkout
            include 'pay.php';
        ?>
    <?php endif; ?>

    </body>
</html>