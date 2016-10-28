<?php
namespace tests\unit\console\components;

use common\models\Project;

use console\components\ManageProjectsAction;

use tests\unit\UnitTestBase;
use tests\unit\fixtures\common\models\ProjectFixture;

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
    public function testConstructRepoName()
    {
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $project = Project::findOne(['id' => 102]);
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'constructRepoName');
        $repoName = $method->invokeArgs($projectsAction, array( $project));
        $expected = "scriptureappbuilder-SIL-cuk-San-Blas-Kuna-Gospels";
        $this->assertEquals($expected, $repoName, " *** Repo name build failed");
    }
    public function testConstructRepoNameFilterInvalidCharacters()
    {
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $project = Project::findOne(['id' => 103]);
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'constructRepoName');
        $repoName = $method->invokeArgs($projectsAction, array( $project));
        $expected = "scriptureappbuilder-SIL-cuk-San-Blas-Kuna-Gospels";
        $this->assertEquals($expected, $repoName, " *** Repo name build failed");
    }    
    public function testCreateAwsAccount()
    {
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $project = Project::findOne(['id' => 102]);
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'createAwsAccount');
        $user = $method->invokeArgs($projectsAction, array( $project));
        $expected = "david_moore";
        $returned = $user->parms['UserName'];
        $this->assertEquals($expected, $returned, " *** User name invalid");
    }    
    public function testAddUserToIamGroup()
    {
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $project = Project::findOne(['id' => 102]);
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'addUserToIamGroup');
        $result = $method->invokeArgs($projectsAction, array( $project->user_id, $project->groupName() ));
        $expected = "david_moore";
        $this->assertEquals($expected, $result->parms['UserName'], " *** User name invalid");
        $expected2 = "CodeCommit-SIL";
        $this->assertEquals($expected2, $result->parms['GroupName'], " *** Group name invalid");
    }    

    
    
}