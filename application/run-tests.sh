#!/usr/bin/env bash

# Run database migrations
/data/yii migrate --interactive=0
/data/yii migrate --interactive=0 --migrationPath=console/migrations-test

# Run codeception tests
cd /data
codecept run unit
