<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Handles adding initial_version_code to table `job`.
 */
class m160630_200721_add_initial_version_code_to_job extends Migration
{
    public function up()
    {
        $this->addColumn("{{job}}", "initial_version_code", Schema::TYPE_INTEGER . " DEFAULT 0");
    }

    public function down()
    {
        $this->dropColumn("{{job}}", "initial_version_code");
    }
}
