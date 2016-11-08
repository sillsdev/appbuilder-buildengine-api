<?php

use yii\db\Schema;
use yii\db\Migration;

class m161108_135607_alter_project_error_column extends Migration
{
    public function up()
    {
        $this->alterColumn("{{project}}", "error", "varchar(2083) null");
    }

    public function down()
    {
        $this->alterColumn("{{project}}", "error", Schema::TYPE_STRING. " null");
    }

}
