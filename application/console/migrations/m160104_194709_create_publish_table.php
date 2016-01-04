<?php

use yii\db\Schema;
use yii\db\Migration;

class m160104_194709_create_publish_table extends Migration
{
    public function up()
    {
        $this->createTable('{{publish}}', [
            'id' => Schema::TYPE_PK,
            'build_id' => 'int(11) not null',
            'status' => Schema::TYPE_STRING . " null",

            'created' => 'datetime null',
            'updated' => 'datetime null',            
        ],"ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $this->addForeignKey('fk_publish_build_id','{{publish}}','build_id',
            '{{build}}','id','NO ACTION','NO ACTION');
    }

    public function down()
    {
       $this->dropTable("{{publish}}");
    }
}
