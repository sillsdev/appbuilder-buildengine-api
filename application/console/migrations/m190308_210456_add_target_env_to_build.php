<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m190308_210456_add_target_env_to_build
 */
class m190308_210456_add_target_env_to_build extends Migration
{
    public function up()
    {
        $this->addColumn("{{build}}", "targets", Schema::TYPE_STRING . " null");
        $this->addColumn("{{build}}", "environment", Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->dropColumn("{{build}}", "targets");
        $this->dropColumn("{{build}}", "environment");
    }

}
