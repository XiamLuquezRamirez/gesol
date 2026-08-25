#!/usr/bin/env bash
# =============================================================
#  Gesol — script de despliegue a produccion
#  Ejecutar EN EL SERVIDOR, desde la raiz del proyecto:
#     bash deploy.sh
#
#  Requisitos previos (una sola vez):
#   - PHP 8.2, Composer y Node 22 disponibles en el servidor.
#   - .env de produccion ya configurado (ver .env.production.example).
#   - APP_KEY generada (php artisan key:generate) si aun no existe.
# =============================================================
set -euo pipefail

echo "==> 1/8  Poniendo la app en mantenimiento"
php artisan down --render="errors::503" || true

echo "==> 2/8  Actualizando codigo (git)"
git pull --ff-only origin main

echo "==> 3/8  Dependencias PHP (sin dev, optimizadas)"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> 4/8  Dependencias Node y build de assets"
npm ci
npm run build
# Nunca dejar el marcador de Vite dev en produccion.
rm -f public/hot

echo "==> 5/8  Migraciones (sin borrar datos)"
php artisan migrate --force

echo "==> 6/8  Seeders idempotentes de catalogos (roles, tipos, tarifas, areas base)"
# Solo seeders idempotentes; NO 'db:seed' completo para no tocar datos reales.
php artisan db:seed --class=RolesSeeder --force
php artisan db:seed --class=TipoSolicitudSeeder --force
php artisan db:seed --class=TarifaViaticosSeeder --force

echo "==> 7/8  Limpiando y recacheando configuracion"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
# Enlace de almacenamiento publico (idempotente).
php artisan storage:link || true

echo "==> 8/8  Sacando la app de mantenimiento"
php artisan up

echo "==> Despliegue completado."
