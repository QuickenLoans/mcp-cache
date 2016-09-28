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
./composer exec phpunit
