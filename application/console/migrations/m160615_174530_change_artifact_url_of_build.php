<?php

use yii\db\Schema;
use yii\db\Migration;

class m160615_174530_change_artifact_url_of_build extends Migration
{
    public function up()
    {
        $this->addColumn("{{build}}", "artifact_url_base", 'varchar(2083) null');
        $this->addColumn("{{build}}", "artifact_files", Schema::TYPE_STRING . " null");

        $this->dropColumn("{{build}}", "artifact_url");
    }

    public function down()
    {
        return false;
    }
}
