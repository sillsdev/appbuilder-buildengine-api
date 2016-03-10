<?php

use yii\db\Schema;
use yii\db\Migration;

class m160303_200821_create_operation_queue_table extends Migration
{
    public function up()
    {
        $this->createTable('{{operation_queue}}',[
            'id' => 'pk',
            'operation' => 'varchar(255) not null',
            'operation_object_id' => 'int(11) null',
            'operation_parms' => 'varchar(2048) null',
            'attempt_count' => 'int not null',
            'last_attempt' => 'datetime null',
            'try_after' => 'datetime null',
            'start_time' => 'datetime null',
            'last_error' => 'varchar(2048) null',
            'created' => 'datetime null',
            'updated' => 'datetime null',
        ],"ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $this->createIndex('idx_try_after','{{operation_queue}}','try_after',false);
        $this->createIndex('idx_start_time','{{operation_queue}}','start_time',false);
    }

    public function down()
    {
        $this->dropTable('{{operation_queue}}');
    }
}
