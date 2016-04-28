<?php

use yii\db\Schema;
use yii\db\Migration;

class m160427_162531_create_client_table extends Migration
{
    public function up()
    {
        $this->createTable('{{client}}',[
            'id' => 'pk',
            'access_token' => 'varchar(255) not null',
            'prefix' => 'varchar(4) not null',
            'created' => 'datetime null',
            'updated' => 'datetime null',
        ],"ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $this->createIndex("idx_accesS_token", "{{client}}", "access_token");
    }

    public function down()
    {
        $this->dropTable('{{client}}');
    }
}
