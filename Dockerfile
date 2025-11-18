FROM php:8.2-apache-bullseye

# Habilitar mod_rewrite e instalar dependências
RUN a2enmod rewrite \
    && apt-get update && apt-get install -y \
        libpq-dev \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*

# --- CORREÇÃO DO LIMITE DE UPLOAD ---
# Cria um arquivo de configuração personalizado para aumentar os limites
RUN echo "upload_max_filesize = 200M" > /usr/local/etc/php/conf.d/custom-upload-limits.ini \
    && echo "post_max_size = 200M" >> /usr/local/etc/php/conf.d/custom-upload-limits.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/custom-upload-limits.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom-upload-limits.ini

# Instalar composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Definir o diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos do composer e instalar dependências
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copiar o restante da aplicação
COPY . .

# Executar scripts do composer, se houver
RUN composer dump-autoload --optimize

# Definir permissões (Importante para o upload funcionar na pasta)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expor a porta e iniciar o servidor
EXPOSE 80
CMD ["apache2-foreground"]
