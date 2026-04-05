FROM php:8.2-apache

# install-php-extensions is the fastest way to install PHP extensions in Docker
# It handles all dependencies automatically, much faster than apt-get
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql

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
echo "=== URL: $(echo "$DATABASE_URL" | cut -c1-40) ==="
exec apache2-foreground
SCRIPT

RUN chmod +x /startup.sh

EXPOSE 80
CMD ["/bin/sh", "/startup.sh"]
