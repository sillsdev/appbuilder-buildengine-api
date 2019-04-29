<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m190424_142509_add_artifacts_to_release
 */
class m190424_142509_add_artifacts_to_release extends Migration
{
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {
        $this->addColumn("{{release}}", "artifact_url_base", Schema::TYPE_STRING . " null");
        $this->addColumn("{{release}}", "artifact_files", Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->dropColumn("{{release}}", "artifact_url_base");
        $this->dropColumn("{{release}}", "artifact_files");
        return false;
    }
}
