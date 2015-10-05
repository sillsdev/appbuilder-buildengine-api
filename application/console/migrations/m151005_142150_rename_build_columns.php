<?php

use yii\db\Schema;
use yii\db\Migration;

class m151005_142150_rename_build_columns extends Migration
{
    public function up()
    {
        $this->renameColumn("{{build}}", "build_result", "result");
        $this->renameColumn("{{build}}", "build_error", "error");
    }

    public function down()
    {
        $this->renameColumn("{{build}}", "result", "build_result");
        $this->renameColumn("{{build}}", "error", "build_error");
    }
}
