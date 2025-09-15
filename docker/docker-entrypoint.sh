#!/bin/sh
set -e

composer install
composer install --working-dir=/var/www/html/public/plugins/egas
cd /var/www/html/public/plugins/egas
yarn install
yarn watch &

exec "$@"
