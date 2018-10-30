<?php
namespace tests\unit\console\components;

use common\models\Project;

use console\components\ProjectUpdateOperation;

use tests\unit\UnitTestBase;
use tests\unit\fixtures\common\models\ProjectFixture;
use tests\mock\common\components\MockAppbuilder_logger;

class ProjectUpdateOperationTest extends UnitTestBase
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
    public function testCheckRemoveUserFromGroup()
    {
        $this->setContainerObjects();
        $project = Project::findOne(['id' => 106]);
        $publishingKey = "ssh-rsa gobbledygookhere test@TTesterABC.local";
        $userId = "test_user2";
        $parms = [ $publishingKey, $userId];
        $projectsAction = new ProjectUpdateOperation($project->id, $parms);
        $method = $this->getPrivateMethod('console\components\ProjectUpdateOperation', 'checkRemoveUserFromGroup');
        $repoName = $method->invokeArgs($projectsAction, array( $project));
        $this->assertEquals(1, $projectsAction->iAmWrapper->removeCalled, " *** Remove call count wrong");
        $expected = "CodeCommit-SIL";
        $this->assertEquals($expected, $projectsAction->iAmWrapper->groupNameParm, " *** Wrong group name");
        $expected2 = "test_user";
        $this->assertEquals($expected2, $projectsAction->iAmWrapper->userNameParm, " *** Wrong user name");
    }
    public function testCheckRemoveUserFromGroupNot()
    {
        $this->setContainerObjects();
        $project = Project::findOne(['id' => 101]);
        $publishingKey = "ssh-rsa gobbledygookhere test@TTesterABC.local";
        $userId = "test_user2";
        $parms = [ $publishingKey, $userId];
        $projectsAction = new ProjectUpdateOperation($project->id, $parms);
        $method = $this->getPrivateMethod('console\components\ProjectUpdateOperation', 'checkRemoveUserFromGroup');
        $repoName = $method->invokeArgs($projectsAction, array( $project));
        $this->assertEquals(0, $projectsAction->iAmWrapper->removeCalled, " *** Remove call count wrong");
    }
    public function testUpdateProject()
    {
        $this->setContainerObjects();
        $project = Project::findOne(['id' => 106]);
        $publishingKey = "ssh-rsa gobbledygookhere test@TTesterABC.local";
        $userId = "test_user2";
        $parms = [ $publishingKey, $userId];
        $projectsAction = new ProjectUpdateOperation($project->id, $parms);
        $method = $this->getPrivateMethod('console\components\ProjectUpdateOperation', 'updateProject');
        $repoName = $method->invokeArgs($projectsAction, array( $project, $userId, $publishingKey));
        $modifiedProject = Project::findOne(['id' => 106]);
        $this->assertEquals($userId, $project->user_id, " *** Wrong user id");
        $this->assertEquals($publishingKey, $project->publishing_key, " *** Wrong publishing key");

    }
    public function testAdjustUrl()
    {
        $this->setContainerObjects();
        $project = Project::findOne(['id' => 106]);
        $publishingKey = "ssh-rsa gobbledygookhere test@TTesterABC.local";
        $userId = "test_user2";
        $parms = [ $publishingKey, $userId];
        $projectsAction = new ProjectUpdateOperation($project->id, $parms);
        $method = $this->getPrivateMethod('console\components\ProjectUpdateOperation', 'adjustUrl');
        $adjustedName = $method->invokeArgs($projectsAction, array($project->url, "AAABBBCCCDDD"));
        $expected = "ssh://AAABBBCCCDDD@git-codecommit.us-east-1.amazonaws.com/v1/repos/scriptureappbuilder-lsdev-cuk-SanBlasKunaGospels";
        $this->assertEquals($expected, $adjustedName, " *** Wrong adjusted url");

    }
}