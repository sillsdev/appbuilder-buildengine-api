<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m180613_134204_change_build_number_to_build_guid
 */
class m180613_134204_change_build_number_to_build_guid extends Migration
{
    public function up()
    {
        $this->dropColumn("{{build}}", "build_number");
        $this->addColumn("{{build}}", "build_guid", Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->addColumn("{{build}}", "build_number", Schema::TYPE_INTEGER . " null");
        $this->dropColumn("{{build}}", "build_guid");
    }
}
