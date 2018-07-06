<?php

use yii\db\Schema;
use yii\db\Migration;

/**
 * Class m180704_191855_addCodeBuildUrl
 */
class m180704_191855_addCodeBuildUrl extends Migration
{
    public function up()
    {
        $this->addColumn("{{build}}", "codebuild_url", Schema::TYPE_STRING . " null");
        $this->addColumn("{{release}}", "codebuild_url", Schema::TYPE_STRING . " null");
    }

    public function down()
    {
        $this->dropColumn("{{build}}", "codebuild_url");
        $this->dropColumn("{{release}}", "codebuild_url");
    }

}
