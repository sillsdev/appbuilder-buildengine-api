<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m180625_151653_change_build_number_to_build_guid_for_release
 */
class m180625_151653_change_build_number_to_build_guid_for_release extends Migration
{
    public function up()
    {
        $this->dropColumn("{{release}}", "build_number");
        $this->addColumn("{{release}}", "build_guid", Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->addColumn("{{release}}", "build_number", Schema::TYPE_INTEGER . " null");
        $this->dropColumn("{{release}}", "build_guid");
    }
}
