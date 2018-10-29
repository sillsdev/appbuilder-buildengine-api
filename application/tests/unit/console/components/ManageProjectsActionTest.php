<?php
namespace tests\unit\console\components;

use common\models\Project;

use console\components\ManageProjectsAction;

use tests\unit\UnitTestBase;
use tests\unit\fixtures\common\models\ProjectFixture;
use tests\mock\common\components\MockAppbuilder_logger;

class ManageProjectsActionTest extends UnitTestBase
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

    public function testTryCreateRepo()
    {
        $this->setContainerObjects();
        $logger = new MockAppbuilder_logger("ManageProjectsAction");
        $projectsAction = new ManageProjectsAction();
        $project = Project::findOne(['id' => 103]);
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'tryCreateRepo');
        $method->invokeArgs($projectsAction, array( $project, $logger ));
        $projectAfter = Project::findOne(['id' => 103]);
        $expected = "completed";
        $this->assertEquals($expected, $projectAfter->status, " *** Wrong status");
        $expected2 = "SUCCESS";
        $this->assertEquals($expected2, $projectAfter->result, " *** Result is not success");
        $expected3 = "ssh://AABBCC@git-codecommit.us-east-1.amazonaws.com/v1/repos/scriptureappbuilder-LSDEV-eng-t4test";
        $this->assertEquals($expected3, $projectAfter->url, " *** Wrong URL");
    }    
    public function testTryCreateRepoException()
    {
        $this->setContainerObjects();
        $logger = new MockAppbuilder_logger("ManageProjectsAction");
        $projectsAction = new ManageProjectsAction();
        $project = Project::findOne(['id' => 104]);
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'tryCreateRepo');
        $method->invokeArgs($projectsAction, array( $project, $logger ));
        $projectAfter = Project::findOne(['id' => 104]);
        $expected = "completed";
        $this->assertEquals($expected, $projectAfter->status, " *** Wrong status");
        $expected2 = "FAILURE";
        $this->assertEquals($expected2, $projectAfter->result, " *** Result is not failure");
        $expected3 = "SAB: Unable to get users existing ssh key(s).";
        $this->assertContains($expected3, $projectAfter->error, " *** Wrong error message");
    }    
    
}