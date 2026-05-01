FROM php:8.4-fpm

# System deps + Node.js 20 LTS
RUN apt-get update && apt-get install -y \
    nginx \
    libxml2-dev \
    libpq-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    zip unzip git curl supervisor gettext-base ca-certificates gnupg \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update && apt-get install -y nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd soap pdo pdo_pgsql pcntl opcache zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app
COPY . .

# Remove autenti path dependency from lock file (local path, not available on Railway)
RUN php -r "\$l=json_decode(file_get_contents('composer.lock'),true);\$l['packages']=array_values(array_filter(\$l['packages'],fn(\$p)=>\$p['name']!=='autenti/autenti-php-sdk'));\$l['packages-dev']=array_values(array_filter(\$l['packages-dev']??[],fn(\$p)=>\$p['name']!=='autenti/autenti-php-sdk'));file_put_contents('composer.lock',json_encode(\$l,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));"

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Install Node.js dependencies for IMAP scripts
RUN cd scripts && npm install --omit=dev

# Storage permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Nginx config template — ${PORT} zastępowane przez envsubst przy starcie
# Zmienne nginx ($host, $uri itp.) są pisane bez backslasha (single quotes = literał)
RUN echo 'server { \n\
    listen ${PORT}; \n\
    server_name _; \n\
    root /var/www/html/public; \n\
    index index.php; \n\
    location /app/ { \n\
        proxy_pass http://127.0.0.1:8088; \n\
        proxy_http_version 1.1; \n\
        proxy_set_header Upgrade $http_upgrade; \n\
        proxy_set_header Connection "upgrade"; \n\
        proxy_set_header Host $host; \n\
        proxy_set_header X-Real-IP $remote_addr; \n\
        proxy_read_timeout 300s; \n\
    } \n\
    location /apps/ { \n\
        proxy_pass http://127.0.0.1:8088; \n\
        proxy_http_version 1.1; \n\
        proxy_set_header Upgrade $http_upgrade; \n\
        proxy_set_header Connection "upgrade"; \n\
        proxy_set_header Host $host; \n\
        proxy_set_header X-Real-IP $remote_addr; \n\
        proxy_read_timeout 300s; \n\
    } \n\
    location / { \n\
        if ($request_method = OPTIONS) { \n\
            add_header Access-Control-Allow-Origin * always; \n\
            add_header Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS" always; \n\
            add_header Access-Control-Allow-Headers "Authorization, Content-Type, Accept, X-Requested-With" always; \n\
            add_header Content-Length 0; \n\
            add_header Content-Type text/plain; \n\
            return 204; \n\
        } \n\
        try_files $uri $uri/ /index.php?$query_string; \n\
    } \n\
    location ~ \.php$ { \n\
        fastcgi_pass 127.0.0.1:9000; \n\
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; \n\
        include fastcgi_params; \n\
        fastcgi_read_timeout 300; \n\
    } \n\
    location ~ /\.(?!well-known).* { deny all; } \n\
    client_max_body_size 50m; \n\
}' > /etc/nginx/default.template

# Supervisor config (uruchamia nginx + php-fpm razem)
RUN echo '[supervisord] \n\
nodaemon=true \n\
logfile=/dev/null \n\
logfile_maxbytes=0 \n\
\n\
[program:php-fpm] \n\
command=php-fpm \n\
autostart=true \n\
autorestart=true \n\
stdout_logfile=/dev/stdout \n\
stdout_logfile_maxbytes=0 \n\
stderr_logfile=/dev/stderr \n\
stderr_logfile_maxbytes=0 \n\
\n\
[program:nginx] \n\
command=nginx -g "daemon off;" \n\
autostart=true \n\
autorestart=true \n\
stdout_logfile=/dev/stdout \n\
stdout_logfile_maxbytes=0 \n\
stderr_logfile=/dev/stderr \n\
stderr_logfile_maxbytes=0 \n\
\n\
[program:reverb] \n\
command=php /var/www/html/artisan reverb:start --port=8088 --no-interaction \n\
autostart=true \n\
autorestart=true \n\
stdout_logfile=/dev/stdout \n\
stdout_logfile_maxbytes=0 \n\
stderr_logfile=/dev/stderr \n\
stderr_logfile_maxbytes=0 \n\
\n\
[program:queue] \n\
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --timeout=300 --no-interaction \n\
autostart=true \n\
autorestart=true \n\
stdout_logfile=/dev/stdout \n\
stdout_logfile_maxbytes=0 \n\
stderr_logfile=/dev/stderr \n\
stderr_logfile_maxbytes=0 \n\
' > /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

# Startup: podstaw PORT w nginx config, migracje + cache + uruchom serwisy
CMD export PORT="${PORT:-80}" && \
    envsubst '${PORT}' < /etc/nginx/default.template > /etc/nginx/sites-enabled/default && \
    php artisan migrate --force && \
    if [ -n "$MAIL_SETUP_CONFIGS" ]; then php artisan mail:setup-configs --force; fi && \
    php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache && \
    supervisord -c /etc/supervisor/conf.d/supervisord.conf
