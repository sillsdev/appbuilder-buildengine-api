<?php

use yii\db\Schema;
use yii\db\Migration;

class m160108_213259_remove_channel_from_build extends Migration
{
    public function up()
    {
        $this->dropColumn("{{build}}", "channel");
    }

    public function down()
    {
        $this->addColumn("{{build}}", "channel", Schema::TYPE_STRING . " null");
        return true;
    }
}
