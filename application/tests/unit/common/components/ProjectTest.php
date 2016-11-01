<?php
namespace tests\unit\common\components;
use tests\unit\UnitTestBase;

use common\models\Project;

use tests\unit\fixtures\common\models\ProjectFixture;

class ProjectTest extends UnitTestBase
{
    /**
     * @var \UnitTester
     */
    protected function _before()
    {
    }

    protected function _after()
    {
    }
    public function fixtures()
    {
        return [
            'project' => ProjectFixture::className(),
        ];
    }
    public function testCreateProject()
    {
        $project = new Project();
        $project->app_id = "scriptureappbuilder";
        $publishKey = "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx";
        $project->publishing_key = $publishKey;
        $project->language_code = "tst";
        $project->save();
        $savedProject = Project::findOne(['language_code' => 'tst']);
        $this->assertNotNull($savedProject, " *** Did not find saved project");
        $this->assertEquals($publishKey, $savedProject->publishing_key);
        $this->assertEquals('initialized', $savedProject->status);

    }
    public function testGroupName()
    {
        $project = Project::findOne(['id' => 102]);
        $groupName = $project->groupName();
        $this->assertEquals('CodeCommit-SIL', $groupName, " *** Invalid group name");
    }
    public function testEntityName()
    {
        $project = Project::findOne(['id' => 102]);
        $entityName = $project->entityName();
        $this->assertEquals('SIL', $entityName, " *** Invalid entity name");
    }
}
