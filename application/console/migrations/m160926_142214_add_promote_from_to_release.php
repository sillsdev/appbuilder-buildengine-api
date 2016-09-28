<?php

use yii\db\Schema;
use yii\db\Migration;

class m160926_142214_add_promote_from_to_release extends Migration
{
    public function up()
    {
        $this->addColumn("{{release}}", "promote_from",Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->dropColumn("{{release}}", "promote_from");
    }
}
