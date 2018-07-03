<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m180629_183318_addConsoleTextUrl
 */
class m180629_183318_addConsoleTextUrl extends Migration
{
    public function up()
    {
        $this->addColumn("{{build}}", "console_text_url", Schema::TYPE_STRING . " null");
        $this->addColumn("{{release}}", "console_text_url", Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->dropColumn("{{build}}", "console_text_url");
        $this->dropColumn("{{release}}", "console_text_url");
    }

}
