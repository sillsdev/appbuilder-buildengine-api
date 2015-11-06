<?php

use yii\db\Schema;
use yii\db\Migration;

class m151106_195737_change_job_request_id_type extends Migration
{
    public function up()
    {
        $this->alterColumn('{{job}}', 'request_id', Schema::TYPE_STRING . " NOT NULL");
    }

    public function down()
    {
         $this->alterColumn('{{job}}', 'request_id', Schema::TYPE_INTEGER . " NOT NULL");
    }
}
