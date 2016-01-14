<?php

use yii\db\Schema;
use yii\db\Migration;

class m160112_220005_alter_error_to_hold_url extends Migration
{
    public function up()
    {
        $this->alterColumn("{{build}}", "error", "varchar(2083) null");
        $this->alterColumn("{{release}}", "error", "varchar(2083) null");
    }

    public function down()
    {
        $this->alterColumn("{{build}}", "error", Schema::TYPE_STRING. " null");
        $this->alterColumn("{{release}}", "error", Schema::TYPE_STRING. " null");
    }
}
