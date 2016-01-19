<?php

use yii\db\Schema;
use yii\db\Migration;

class m160119_135843_add_channel_version_number_to_build extends Migration
{
    public function up()
    {
        $this->addColumn("{{build}}", "channel", Schema::TYPE_STRING . " null");
        $this->addColumn("{{build}}", "version_code", Schema::TYPE_INTEGER . " null");
    }

    public function down()
    {
        $this->dropColumn("{{build}}", "channel");
        $this->dropColumn("{{build}}", "version_code");
    }
}
