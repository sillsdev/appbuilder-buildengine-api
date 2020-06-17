<?php

use yii\db\Migration;

/**
 * Class m200616_193900_alter_multiple_apks
 */
class m200616_193900_alter_multiple_apks extends Migration
{
    public function up()
    {
        $this->alterColumn("{{build}}", "artifact_files", "varchar(4096) null");
    }

    public function down()
    {
        $this->alterColumn("{{build}}", "artifact_files", Schema::TYPE_STRING. " null");
    }
}
