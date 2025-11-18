<?php
// Ficheiro: db_config.php
// Objetivo: Conectar ao banco de dados usando DATABASE_URL (para produção)
//           ou credenciais locais (para desenvolvimento) e inicializar o DB (Plug and Play).

$pdo = null;

// =====================================================================
// 1. LÓGICA DE CONEXÃO: PRODUÇÃO (DATABASE_URL) vs. LOCAL
// =====================================================================

if (getenv('DATABASE_URL')) {
    // ------------------------------------------------
    // MODO PRODUÇÃO: Usando a variável de ambiente (Render/Heroku)
    // ------------------------------------------------

    // Exemplo de formato: postgres://user:password@host:port/dbname
    $db_url = getenv('DATABASE_URL');

    // Analisar a URL para extrair os componentes
    $url_components = parse_url($db_url);

    if ($url_components === false || !isset($url_components['path'])) {
        die("Erro: DATABASE_URL inválida ou ausente no ambiente de produção.");
    }

    $host = $url_components['host'];
    $port = $url_components['port'] ?? '5432';
    $dbname = ltrim($url_components['path'], '/'); // Remove a barra inicial do nome do DB
    $user = $url_components['user'];
    $password = $url_components['pass'];

    // String de Conexão (DSN) formatada para PDO (pgsql)
    // IMPORTANTE: sslmode=require é essencial para a Render
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password;sslmode=require";

} else {
    // ------------------------------------------------
    // MODO LOCAL: Usando credenciais definidas manualmente
    // ------------------------------------------------

    // Definições Locais
    $host = 'localhost';
    $port = '5432';
    $dbname = 'db-teste'; // SEU NOME DE BANCO DE DADOS LOCAL
    $user = 'postgres';
    $password = 'Lucas8536@'; // SUA SENHA LOCAL

    // String de Conexão (DSN)
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";
}

// =====================================================================
// 2. CONEXÃO E INICIALIZAÇÃO
// =====================================================================

try {
    // Tenta criar a conexão PDO
    $pdo = new PDO($dsn);
    // Define o modo de erro para exceções, facilitando a depuração
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Chama a lógica de inicialização
    initializeDatabase($pdo);

} catch (PDOException $e) {
    // Se a conexão falhar, o script para.
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}

/**
 * Verifica se o banco está vazio e cria as tabelas e dados iniciais (Estrutura e Seeding P&P).
 * @param PDO $pdo A conexão ativa com o banco de dados.
 */
function initializeDatabase(PDO $pdo) {
    // Verifica a existência da tabela 'produtos' (como indicador de inicialização)
    $check_table_sql = "SELECT to_regclass('public.produtos')";
    $stmt = $pdo->query($check_table_sql);
    $table_exists = $stmt->fetchColumn() !== null;

    if (!$table_exists) {
        if (php_sapi_name() !== 'cli') {
            echo "Banco de dados vazio detectado. Criando tabelas e inserindo dados iniciais...<br>";
        } else {
            echo "Banco de dados vazio detectado. Criando tabelas e inserindo dados iniciais...\n";
        }

        // --------------------------------------------------------------------------------------
        // ⚠️ USO DE HEREDOC (<<<SQL) PARA EVITAR QUEBRAS COM BARRAS INVERTIDAS NO HASH DE SENHA! ⚠️
        // --------------------------------------------------------------------------------------
        $sql_commands = <<<SQL

-- TABELA 1: admin_users
CREATE TABLE public.admin_users (
    id SERIAL PRIMARY KEY,
    username character varying(50) NOT NULL UNIQUE,
    email character varying(255) NOT NULL UNIQUE,
    password_hash character varying(255) NOT NULL,
    user_level character varying(50) DEFAULT 'Gerente' NOT NULL
);

-- TABELA 2: produtos
CREATE TABLE public.produtos (
    id SERIAL PRIMARY KEY,
    nome character varying(255) NOT NULL,
    descricao text,
    preco_atual numeric(10,2) NOT NULL,
    preco_antigo numeric(10,2),
    link_cupom character varying(100),
    imagem_principal character varying(255),
    imagens_galeria text[],
    rating numeric(2,1) DEFAULT 4.7,
    rating_count integer DEFAULT 204,
    sold_count integer DEFAULT 4473,
    tag_oferta_principal character varying(100) DEFAULT 'OFERTA REL MPAGO',
    tag_oferta_secundaria character varying(100) DEFAULT 'BLACK FRIDAY NOVEMBER',
    tag_desconto_1 character varying(100) DEFAULT 'Economize R$660,00',
    tag_desconto_2 character varying(100) DEFAULT 'Economize 82% COM CUPOM',
    shipping_info character varying(100) DEFAULT 'Receba at 15-20 de nov.',
    shipping_cost character varying(50) DEFAULT 'Gr tis',
    nome_vendedor character varying(255) DEFAULT 'EAGLE FORCE BR',
    vendas_count integer DEFAULT 117,
    url_logo_vendedor character varying(255) DEFAULT 'url_default_logo.jpg',
    descricao_completa text
);

-- TABELA 3: chat_messages
CREATE TABLE public.chat_messages (
    id SERIAL PRIMARY KEY,
    session_id character varying(255) NOT NULL,
    sender_type character varying(10) NOT NULL,
    message_content text NOT NULL,
    context_id character varying(255),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);
-- Índice necessário para a sessão do chat
CREATE INDEX idx_chat_session ON public.chat_messages USING btree (session_id);

-- TABELA 4: config_gateway
CREATE TABLE public.config_gateway (
    id SERIAL PRIMARY KEY,
    chave character varying(100) NOT NULL UNIQUE,
    valor text,
    descricao character varying(255),
    data_cadastro timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- TABELA 5: configuracoes_marketing
CREATE TABLE public.configuracoes_marketing (
    id SERIAL PRIMARY KEY,
    chave character varying(100) NOT NULL UNIQUE,
    valor character varying(255) NOT NULL,
    descricao character varying(255)
);

-- TABELA 6: configuracoes_site
CREATE TABLE public.configuracoes_site (
    id integer DEFAULT 1 PRIMARY KEY,
    cor_botao_comprar character varying(7) DEFAULT '#E7001A',
    cor_fundo character varying(7) DEFAULT '#FFFFFF',
    header_cor_fundo character varying(10) DEFAULT '#FFFFFF',
    header_cor_icones character varying(10) DEFAULT '#333333'
);

-- TABELA 7: pedidos
CREATE TABLE public.pedidos (
    id SERIAL PRIMARY KEY,
    user_id integer NOT NULL,
    gateway_txid character varying(255) NOT NULL,
    status character varying(50) DEFAULT 'PENDENTE' NOT NULL,
    customer_name character varying(255) NOT NULL,
    customer_email character varying(255) NOT NULL,
    customer_cpf character varying(20) NOT NULL,
    customer_phone character varying(20) NOT NULL,
    product_name character varying(255) NOT NULL,
    quantity integer NOT NULL,
    total_amount_centavos integer NOT NULL,
    pix_code text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- TABELA 8: videos_criadores
CREATE TABLE public.videos_criadores (
    id SERIAL PRIMARY KEY,
    produto_id integer,
    nome_criador character varying(100) NOT NULL,
    descricao_video text,
    musica_original character varying(150),
    caminho_arquivo character varying(255) NOT NULL,
    likes integer DEFAULT 0,
    comentarios integer DEFAULT 0,
    salvos integer DEFAULT 0,
    compartilhamentos integer DEFAULT 0,
    thumbnail_url character varying(255),
    CONSTRAINT videos_criadores_produto_id_fkey FOREIGN KEY (produto_id) REFERENCES public.produtos(id)
);
-- Índice necessário para vídeos
CREATE INDEX idx_videos_criadores_produto ON public.videos_criadores USING btree (produto_id);


-- ==========================================
-- DADOS INICIAIS (INSERTs)
-- ==========================================

-- Dados na tabela admin_users (Hash de senha para 'admin' - O '$' foi escapado)
INSERT INTO public.admin_users (username, email, password_hash, user_level)
VALUES ('admin', 'admin@loja.com', '\$2y\$10\$dsJxZ8KVk5WthKH5uz1eMuTDwhhcgcQWAYSYRPJAM2TEIK4AGbt8C', 'Administrador');

-- Dados na tabela produtos (Produto de Exemplo com ID 1)
INSERT INTO public.produtos (
    id, nome, preco_atual, preco_antigo, imagem_principal, rating, nome_vendedor, url_logo_vendedor
) VALUES (
    1,
    'Cadeira Gamer Fox Office Fox Racer, RGB e Iluminação LED, até 130kgs, com almofadas, Reclinável - Preta e Branca',
    99.90,
    199.90,
    'url_default_produto.png',
    4.8,
    'EAGLE FORCE BR',
    'url_default_logo.jpeg'
) ON CONFLICT (id) DO NOTHING;

-- Garante que o próximo ID da tabela produtos seja 2 (depois de inserir o 1)
SELECT setval('public.produtos_id_seq', 2, false);

-- Dados na tabela configuracoes_site (Um registro para configurações padrão)
INSERT INTO public.configuracoes_site (id, cor_botao_comprar, cor_fundo)
VALUES (1, '#E7001A', '#FFFFFF') ON CONFLICT (id) DO NOTHING;

-- Dados na tabela videos_criadores (Assumindo que o produto_id 1 existe)
-- CORRIGIDO: 4 colunas (produto_id, nome_criador, caminho_arquivo, thumbnail_url) e 4 valores.
INSERT INTO public.videos_criadores (produto_id, nome_criador, caminho_arquivo, thumbnail_url)
VALUES (1, 'Creator_01', 'uploads/videos/videocreator.mp4', 'uploads/thumbs/thumb_creator.jpg');
SQL;
        // --------------------------------------------------------------------------------------

        // Executa o SQL.
        try {
            $pdo->exec($sql_commands);
            if (php_sapi_name() !== 'cli') {
                echo "Criação de tabelas e inserção de dados iniciais concluída com sucesso!<br>";
            } else {
                echo "Criação de tabelas e inserção de dados iniciais concluída com sucesso!\n";
            }
        } catch (PDOException $e) {
            die("Erro ao inicializar o banco de dados: " . $e->getMessage());
        }
    }

    // =========================================================================
    // BLOCO DE SEEDING DE USUÁRIOS DE TESTE (executado mesmo se tabelas existirem)
    // =========================================================================

    // Note: Este seeding não depende de 'initializeDatabase' ter rodado o SQL acima,
    // pois a função é chamada após a tentativa de conexão, garantindo que 'admin_users' exista.
    $senhaHash = password_hash('123456', PASSWORD_DEFAULT);

    // --- Seeding de Admin (username: 'admin_cli', email: 'admin@cli.com')
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE email = ?");
    $stmt->execute(['admin@cli.com']);
    if ($stmt->rowCount() == 0) {
        $stmtInsert = $pdo->prepare("INSERT INTO admin_users (username, email, password_hash, user_level) VALUES (?, ?, ?, ?);");
        $stmtInsert->execute(['admin_cli', 'admin@cli.com', $senhaHash, 'Administrador']);
        if (php_sapi_name() === 'cli') { echo "   -> Usuário 'admin@cli.com' (123456) criado.\n"; }
    }

    // --- Seeding de Gerente (username: 'gerente_cli', email: 'gerente@cli.com')
    $stmt->execute(['gerente@cli.com']);
    if ($stmt->rowCount() == 0) {
        $stmtInsert = $pdo->prepare("INSERT INTO admin_users (username, email, password_hash, user_level) VALUES (?, ?, ?, ?);");
        $stmtInsert->execute(['gerente_cli', 'gerente@cli.com', $senhaHash, 'Gerente']);
        if (php_sapi_name() === 'cli') { echo "   -> Usuário 'gerente@cli.com' (123456) criado.\n"; }
    }
}