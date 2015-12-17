<?php

use yii\db\Schema;
use yii\db\Migration;

class m151217_202114_remove_artifact_url_from_job extends Migration
{
    public function up()
    {
        $this->dropColumn("{{job}}", "artifact_url_base");
    }

    public function down()
    {
        $this->addColumn("{{job}}", "artifact_url_base", "varchar(1024) null");
    }
}
