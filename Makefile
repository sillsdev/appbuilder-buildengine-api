start: app

test: clean testSh composer rmTestDb upTestDb yiimigratetestDb rmTestDb
	docker compose run --rm cli bash -c 'MYSQL_HOST=testDb MYSQL_DATABASE=test ./vendor/bin/codecept --debug run unit'

testSh:
	shellcheck application/console/views/cron/scripts/upload/default/*.sh

app: upDb composer yiimigrate adminer
	docker compose up -d cron web

composer:
	docker compose run --rm --user="0:0" cli composer install

composerupdate:
	docker compose run --rm --user="0:0" cli composer update

cli:
	docker compose run --rm --user="0:0" cli bash

rmDb:
	docker compose kill db
	docker compose rm -f db

upDb:
	docker compose up -d db

yiimigrate:
	docker compose run --rm cli whenavail db 3306 100 ./yii migrate --interactive=0

basemodels:
	docker compose run --rm cli whenavail db 3306 100 ./rebuildbasemodels.sh

yiimigratetestDb:
	docker compose run --rm cli bash -c 'MYSQL_HOST=testDb MYSQL_DATABASE=test whenavail testDb 3306 100 ./yii migrate --interactive=0'

rmTestDb:
	docker compose kill testDb
	docker compose rm -f testDb

upTestDb:
	docker compose up -d testDb

bounce:
	docker compose up -d cron web

clean:
	docker compose kill
	docker compose rm -f

cleanVolumes:
	docker volume rm `docker volume ls -qf dangling=true`

adminer:
	docker compose up -d adminer
