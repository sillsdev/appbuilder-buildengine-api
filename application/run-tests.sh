#!/usr/bin/env bash

# Run shell tests
scversion="stable" # or "v0.4.7", or "latest"
curl -s -L --output - "https://github.com/koalaman/shellcheck/releases/download/${scversion?}/shellcheck-${scversion?}.linux.x86_64.tar.xz" | tar -xJv
cp "shellcheck-${scversion}/shellcheck" /usr/bin/
shellcheck --version
shellcheck /data/console/views/cron/scripts/upload/default/*.sh || exit 1

# Run database migrations
whenavail db 3306 100 /data/yii migrate --interactive=0
whenavail db 3306 100 /data/yii migrate --interactive=0 --migrationPath=console/migrations-test

# Run codeception tests
cd /data
composer install --prefer-dist --no-interaction
./vendor/bin/codecept run unit
