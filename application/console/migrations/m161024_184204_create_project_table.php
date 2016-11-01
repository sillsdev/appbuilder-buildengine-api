<?php

use yii\db\Schema;
use yii\db\Migration;

class m161024_184204_create_project_table extends Migration
{
    public function up()
    {
        $this->createTable('{{project}}', [
            'id' => Schema::TYPE_PK,
            'status' => Schema::TYPE_STRING . " null",
            'result' => Schema::TYPE_STRING . " null",
            'error' => Schema::TYPE_STRING . " null",
            "url" => "varchar(1024) null",
            'user_id' => Schema::TYPE_STRING . " null",
            'group_id' => Schema::TYPE_STRING . " null",
            'app_id' => Schema::TYPE_STRING . " null",
            'project_name' => Schema::TYPE_STRING . " null",
            'language_code' => Schema::TYPE_STRING . " null",
            "publishing_key" => "varchar(1024) null",
            
            'created' => 'datetime null',
            'updated' => 'datetime null',            
        ],"ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }

    public function down()
    {
       $this->dropTable("{{project}}");
    }

}
