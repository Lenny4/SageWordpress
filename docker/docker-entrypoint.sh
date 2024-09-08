#!/bin/sh
set -e

composer install
composer install --working-dir=/var/www/html/public/plugins/sage
npm --prefix /var/www/html/public/plugins/sage install /var/www/html/public/plugins/sage
grunt watch --gruntfile /var/www/html/public/plugins/sage/Gruntfile.js &

exec "$@"
