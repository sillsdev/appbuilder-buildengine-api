<?php

use yii\db\Schema;
use yii\db\Migration;

class m160108_213227_rename_publish_to_release extends Migration
{
    public function up()
    {
        $this->dropForeignKey("fk_publish_build_id", "{{publish}}");
        $this->renameTable("{{publish}}", "{{release}}");
        $this->addColumn("{{release}}", 'result', Schema::TYPE_STRING . " null");
        $this->addColumn("{{release}}", 'error', Schema::TYPE_STRING . " null");
        $this->addColumn("{{release}}", 'channel', Schema::TYPE_STRING . " not null");
        $this->addColumn("{{release}}", 'title', "varchar(30) null");
        $this->addColumn("{{release}}", 'defaultLanguage', Schema::TYPE_STRING . " null");
        
        $this->addForeignKey('fk_release_build_id','{{release}}','build_id',
            '{{build}}','id','NO ACTION','NO ACTION');        
    }

    public function down()
    {
        $this->dropForeignKey('fk_release_build_id', "{{release}}");
        $this->dropColumn('{{release}}', 'result');
        $this->dropColumn('{{release}}', 'error');
        $this->dropColumn('{{release}}', 'channel');
        $this->dropColumn('{{release}}', 'title');
        $this->dropColumn('{{release}}', 'defaultLanguage');
        $this->renameTable('{{release}}', '{{publish}}');
        $this->addForeignKey('fk_publish_build_id','{{publish}}','build_id',
            '{{build}}','id','NO ACTION','NO ACTION');
    }
}
