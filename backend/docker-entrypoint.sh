#!/usr/bin/env sh
set -eu

PORT_VALUE="${PORT:-10000}"

sed -ri "s/^Listen [0-9]+/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
