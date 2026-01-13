# Vite & Gourmand – API REST

API REST développée avec Symfony (PHP 8.x).

## Documentation
La documentation OpenAPI est disponible via NelmioApiDocBundle (Swagger UI).

## Installation
```bash
composer install
php bin/console doctrine:migrations:migrate
php -S 127.0.0.1:8000 -t public