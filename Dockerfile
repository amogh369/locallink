FROM php:8.2-apache

RUN apt-get update -qq \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql \
    && apt-get purge -y --auto-remove libpq-dev \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

RUN a2enmod rewrite headers

WORKDIR /var/www/html
COPY . /var/www/html/

RUN printf '<Directory /var/www/html>\nOptions -Indexes +FollowSymLinks\nAllowOverride All\nRequire all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/ll.conf && a2enconf ll

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

RUN cat > /startup.sh << 'SCRIPT'
#!/bin/sh
P="/var/www/html/_env.php"
printf '<?php\n' > "$P"
printf '$_ENV["DATABASE_URL"]="%s";\n' "$DATABASE_URL" >> "$P"
echo "=== _env.php written ==="
echo "=== URL prefix: $(echo "$DATABASE_URL" | cut -c1-30) ==="
exec apache2-foreground
SCRIPT

RUN chmod +x /startup.sh

EXPOSE 80
CMD ["/bin/sh", "/startup.sh"]
