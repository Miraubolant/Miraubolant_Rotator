FROM php:8.2-apache

# Activer mod_rewrite
RUN a2enmod rewrite

# Config Apache pour AllowOverride
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copier les fichiers
COPY . /var/www/html/

# Créer les dossiers de données
RUN mkdir -p /var/www/html/data /var/www/html/data/geo_cache /var/www/html/logs

# Permissions
RUN chown -R www-data:www-data /var/www/html/data /var/www/html/logs
RUN chmod -R 755 /var/www/html/data /var/www/html/logs

EXPOSE 80

CMD ["apache2-foreground"]
