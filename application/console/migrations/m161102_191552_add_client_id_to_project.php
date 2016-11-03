<?php

use yii\db\Schema;
use yii\db\Migration;

class m161102_191552_add_client_id_to_project extends Migration
{
    public function up()
    {
        $this->addColumn("{{project}}", "client_id", "int(11) null");
        $this->addForeignKey('fk_project_client_id','{{project}}','client_id',
            '{{client}}','id','NO ACTION','NO ACTION');
    }

    public function down()
    {
        $this->dropForeignKey('fk_project_client_id', '{{project}}');
        $this->dropColumn("{{project}}", "client_id");
    }
}
