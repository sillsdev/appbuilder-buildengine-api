<?php

use yii\db\Migration;

class m150917_163901_add_artifact_url_base_to_job extends Migration
{
    public function up()
    {
        $this->addColumn("{{job}}", "artifact_url_base", "varchar(1024) null");
    }

    public function down()
    {
        $this->dropColumn("{{job}}", "artifact_url_base");
    }
}
