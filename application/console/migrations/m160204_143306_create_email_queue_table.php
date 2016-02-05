<?php

use yii\db\Schema;
use yii\db\Migration;

class m160204_143306_create_email_queue_table extends Migration
{
    public function up()
    {
        $this->createTable('{{email_queue}}',[
            'id' => 'pk',
            'to' => 'varchar(255) not null',
            'cc' => 'varchar(255) null',
            'bcc' => 'varchar(255) null',
            'subject' => 'varchar(255) not null',
            'text_body' => 'text null',
            'html_body' => 'text null',
            'attempts_count' => 'tinyint(1) null',
            'last_attempt' => 'datetime null',
            'created' => 'datetime null',
            'error' => 'varchar(255) null',
        ],"ENGINE=InnoDB DEFAULT CHARSET=utf8");

    }

    public function down()
    {
        $this->dropTable('{{email_queue}}');
    }}
