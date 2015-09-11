<?php

use yii\db\Schema;
use yii\db\Migration;

class m150903_174303_create_job_table extends Migration
{
    public function up()
    {
        $this->createTable('{{job}}', [
            'id' => Schema::TYPE_PK,
            'request_id' => Schema::TYPE_INTEGER . " NOT NULL",
            'git_url' => 'varchar(2083) NOT NULL',
            'app_id'=> Schema::TYPE_STRING . " NOT NULL",
            'publisher_id'=> Schema::TYPE_STRING . " NOT NULL",

            'created' => 'datetime null',
            'updated' => 'datetime null',            
        ],"ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $this->createIndex("idx_request_id", "{{job}}", "request_id");
    }

    public function down()
    {
        $this->dropIndex("idx_request_id", "{{job}}");
        $this->dropTable("{{job}}");
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
