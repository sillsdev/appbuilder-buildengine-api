start: app

test: composer rmTestDb upTestDb yiimigratetestDb rmTestDb
	docker-compose run --rm cli bash -c 'MYSQL_HOST=testDb MYSQL_DATABASE=test ./vendor/bin/codecept run unit'

app: upDb composer yiimigrate 
	docker-compose up -d cron web

composer:
	docker-compose run --rm --user="0:0" cli composer install

composerupdate:
	docker-compose run --rm --user="0:0" cli composer update

rmDb:
	docker-compose kill db
	docker-compose rm -f db

upDb:
	docker-compose up -d db

yiimigrate:
	docker-compose run --rm cli whenavail db 3306 100 ./yii migrate --interactive=0

basemodels:
	docker-compose run --rm cli whenavail db 3306 100 ./rebuildbasemodels.sh

yiimigratetestDb:
	docker-compose run --rm cli bash -c 'MYSQL_HOST=testDb MYSQL_DATABASE=test whenavail testDb 3306 100 ./yii migrate --interactive=0'

rmTestDb:
	docker-compose kill testDb
	docker-compose rm -f testDb

upTestDb:
	docker-compose up -d testDb

bounce:
	docker-compose up -d cron web

clean:
	docker-compose kill
	docker-compose rm -f
