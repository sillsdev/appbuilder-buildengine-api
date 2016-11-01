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
    public function testAddPublicSshKeyNewKey()
    {
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $user_name = 'normal';
        $public_key = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx';
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'addPublicSshKey');
        $result = $method->invokeArgs($projectsAction, array( $user_name, $public_key ));
        $this->assertEquals('normal', $result['UserName'], " *** User name invalid");
        $this->assertEquals('AABBCC', $result['SSHPublicKey']['SSHPublicKeyId']);
        $this->assertEquals($public_key, $result['SSHPublicKey']['SSHPublicKeyBody'], " *** Public key invalid");
    }
    public function testAddPublishSshKeyDuplicateKey()
    {
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $user_name = 'throwKey';
        $public_key = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx';
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'addPublicSshKey');
        $result = $method->invokeArgs($projectsAction, array( $user_name, $public_key ));
        $this->assertEquals('throwKey', $result['UserName'], " *** User name invalid");
        $this->assertEquals($public_key, $result['SSHPublicKey']['SSHPublicKeyBody'], " *** Public key invalid");
    }
    public function testAddPublishSshKeyDuplicateKeyNoMatch()
    {
        $this->setExpectedException('yii\web\ServerErrorHttpException');
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $user_name = 'throwKey';
        $public_key = 'ssh-rsa BBBBC3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx';
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'addPublicSshKey');
        $result = $method->invokeArgs($projectsAction, array( $user_name, $public_key ));
    }
    public function testAddPublishSshKeyDuplicateKeyCantSave()
    {
        $this->setExpectedException('yii\web\ServerErrorHttpException');
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $user_name = 'throwKeyTwice';
        $public_key = 'ssh-rsa BBBBC3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx';
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'addPublicSshKey');
        $result = $method->invokeArgs($projectsAction, array( $user_name, $public_key ));
    }
    
    public function testTryCreateRepoException()
    {
        return;
        $this->setContainerObjects();
        $projectsAction = new ManageProjectsAction();
        $project = Project::findOne(['id' => 104]);
        $method = $this->getPrivateMethod('console\components\ManageProjectsAction', 'tryCreateRepo');
        $method->invokeArgs($projectsAction, array( $project ));
        $projectAfter = Project::findOne(['id' => 104]);
        $expected = "completed";
        $this->assertEquals($expected, $projectAfter->status, " *** Wrong status");
        $expected2 = "FAILURE";
        $this->assertEquals($expected2, $projectAfter->result, " *** Result is not failure");
        $expected3 = "SAB: Unable to get users existing ssh key(s).";
        $this->assertContains($expected3, $projectAfter->error, " *** Wrong error message");
    }    
    
}