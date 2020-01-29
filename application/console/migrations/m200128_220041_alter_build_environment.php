<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m200128_220041_alter_build_environment
 */
class m200128_220041_alter_build_environment extends Migration
{
    public function up()
    {
        $this->alterColumn("{{build}}", "environment", Schema::TYPE_TEXT. " null");
    }
    public function down()
    {
        $this->alterColumn("{{build}}", "environment", "varchar(255) null");
    }
}

