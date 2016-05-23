#!/usr/bin/env bash

# Run database migrations
#/data/yii migrate --interactive=0
#/data/yii migrate --interactive=0 --migrationPath=console/migrations-test

# Run codeception tests
#cd /data
#echo "Installing dev dependencies..."
#composer install --prefer-dist --no-interaction
#./vendor/bin/codecept run unit

#!/usr/bin/env bash

# Run database migrations
whenavail db 3306 100 /data/yii migrate --interactive=0
whenavail db 3306 100 /data/yii migrate --interactive=0 --migrationPath=console/migrations-test

# Run codeception tests
cd /data
composer install --prefer-dist --no-interaction
./vendor/bin/codecept.phar run unit
