<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m190311_200559_add_target_env_to_release
 */
class m190311_200559_add_target_env_to_release extends Migration
{
    public function up()
    {
        $this->addColumn("{{release}}", "targets", Schema::TYPE_STRING . " null");
        $this->addColumn("{{release}}", "environment", Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->dropColumn("{{release}}", "targets");
        $this->dropColumn("{{release}}", "environment");
    }
}
