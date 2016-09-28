#!/usr/bin/env sh

set -e

export INTERNAL_COMPOSER="composer.mi.corp.rockfin.com"
curl "http://$INTERNAL_COMPOSER/installer" | sh -s -- composer

echo ; echo
./composer install --no-interaction --no-progress

echo ; echo
./composer show

echo ; echo
./composer show --platform

echo ; echo
if [ -f "/etc/php.ini" ] ; then
    echo 'apc.enable_cli=1' >> "/etc/php.ini"
fi

echo ; echo
./composer exec phpunit
