<?php

use yii\db\Schema;
use yii\db\Migration;

class m151117_175414_add_channel_to_build extends Migration
{
    public function up()
    {
        $this->addColumn("{{build}}", "channel", Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->dropColumn("{{build}}", "channel");
    }
}
