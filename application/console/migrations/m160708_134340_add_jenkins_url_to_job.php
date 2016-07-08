<?php

use yii\db\Schema;
use yii\db\Migration;

class m160708_134340_add_jenkins_url_to_job extends Migration
{
    public function up()
    {
        $this->addColumn("{{job}}", "jenkins_build_url", "varchar(1024) null");
        $this->addColumn("{{job}}", "jenkins_publish_url", "varchar(1024) null");
    }

    public function down()
    {
        $this->dropColumn("{{job}}", "jenkins_build_url");
        $this->dropColumn("{{job}}", "jenkins_publish_url");
    }
}
