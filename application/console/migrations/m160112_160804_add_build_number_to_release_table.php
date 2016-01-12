<?php

use yii\db\Schema;
use yii\db\Migration;

class m160112_160804_add_build_number_to_release_table extends Migration
{
    public function up()
    {
        $this->addColumn("{{release}}", "build_number", Schema::TYPE_INTEGER . " null");
    }

    public function down()
    {
        $this->dropColumn("{{release}}", "build_number");
    }
}
