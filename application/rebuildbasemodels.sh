#!/usr/bin/env bash

TABLES=(job build release email_queue operation_queue client)
SUFFIX="Base"

declare -A models
models["job"]="JobBase"
models["build"]="BuildBase"
models["release"]="ReleaseBase"
models["email_queue"]="EmailQueueBase"
models["operation_queue"]="OperationQueueBase"
models["client"]="ClientBase"

for i in "${!models[@]}"; do
    CMD="./yii gii/model --tableName=$i --modelClass=${models[$i]} --generateRelations=1 --enableI18N=1 --overwrite=1 --interactive=0 --ns=\common\models"
    echo $CMD
    $CMD
done
