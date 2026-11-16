FROM php:8.2-apache

COPY . /var/www/html/

# La carpeta datos/ la escribe Apache, pero nadie puede descargarla desde el navegador
# (dentro está la partida con los enlaces secretos de los países).
RUN mkdir -p /var/www/html/datos \
 && chown -R www-data:www-data /var/www/html \
 && printf '<Directory /var/www/html/datos>\n    Require all denied\n</Directory>\n' \
    > /etc/apache2/conf-enabled/proteger-datos.conf
