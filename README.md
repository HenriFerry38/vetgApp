# Vite & Gourmand – API REST

API REST développée avec Symfony (PHP 8.x).

## Documentation
La documentation OpenAPI est disponible via NelmioApiDocBundle (Swagger UI).

## Installation
```bash
git clone 'github.com/HenriFerry38/vetgApp.git'
cp  .env .env.local
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php -S 127.0.0.1:8000 -t public

# Lancement du server symfony
bash:

symfony server:start