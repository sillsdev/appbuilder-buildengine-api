<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m210825_131342_alter_release_environment
 */
class m210825_131342_alter_release_environment extends Migration
{
    public function up()
    {
        $this->alterColumn("{{release}}", "environment", Schema::TYPE_TEXT. " null");
    }

    public function down()
    {
        $this->alterColumn("{{release}}", "environment", "varchar(255) null");
    }
}
