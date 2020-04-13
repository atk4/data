#!/bin/bash -x
cd $(dirname $0)/..
#composer install --no-suggest --prefer-dist --optimize-autoloader
mkdir -p build/logs
vendor/bin/phpunit --configuration phpunit.xml --coverage-text --exclude-group dns
vendor/bin/phpunit --configuration phpunit-mysql.xml --exclude-group dns
vendor/bin/phpunit --configuration phpunit-pgsql.xml --exclude-group dns
