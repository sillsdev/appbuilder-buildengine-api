<?php

use yii\db\Schema;
use yii\db\Migration;

class m150914_144408_create_build_table extends Migration
{
    public function up()
    {
        $this->createTable('{{build}}', [
            'id' => Schema::TYPE_PK,
            'job_id' => 'int(11) not null',
            'status' => Schema::TYPE_STRING . " null",
            'build_number'=> Schema::TYPE_INTEGER . " null",
            'build_result' => Schema::TYPE_STRING . " null",
            'build_error' => Schema::TYPE_STRING . " null",
            'artifact_url'=> 'varchar(2083) null',

            'created' => 'datetime null',
            'updated' => 'datetime null',            
        ],"ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $this->addForeignKey('fk_build_job_id','{{build}}','job_id',
            '{{job}}','id','NO ACTION','NO ACTION');
    }

    public function down()
    {
       $this->dropTable("{{build}}");
    }

    /*
    // Use safeUp/safeDown to run migration code within a transaction
    public function safeUp()
    {
    }

    public function safeDown()
    {
    }
    */
}
