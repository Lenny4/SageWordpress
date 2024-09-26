#!/bin/sh
set -e

composer install
composer install --working-dir=/var/www/html/public/plugins/sage
cd /var/www/html/public/plugins/sage
yarn install
yarn watch &

exec "$@"
