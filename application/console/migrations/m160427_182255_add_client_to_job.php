<?php

use yii\db\Schema;
use yii\db\Migration;

class m160427_182255_add_client_to_job extends Migration
{
    public function up()
    {
        $this->addColumn("{{job}}", "client_id", "int(11) null");
        $this->addForeignKey('fk_job_client_id','{{job}}','client_id',
            '{{client}}','id','NO ACTION','NO ACTION');
    }

    public function down()
    {
        $this->dropForeignKey('fk_job_client_id', '{{job}}');
        $this->dropColumn("{{job}}", "client_id");
    }
}
