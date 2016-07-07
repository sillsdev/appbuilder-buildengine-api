<?php

use yii\db\Schema;
use yii\db\Migration;

class m160707_142745_change_initial_to_existing_version_code extends Migration
{
    public function up()
    {
        $this->renameColumn("{{job}}", "initial_version_code", "existing_version_code");
    }

    public function down()
    {
        $this->renameColumn("{{job}}", "existing_version_code", "initial_version_code");
    }
}
