# Image officielle PHP avec Apache
FROM php:8.2-apache

# Installation des extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Activation du module rewrite d'Apache
RUN a2enmod rewrite

# Définition du dossier de travail
WORKDIR /var/www/html

# Copie des fichiers du projet dans le conteneur
COPY . /var/www/html

# Droits sur les fichiers (évite certains problèmes sous Linux)
RUN chown -R www-data:www-data /var/www/html