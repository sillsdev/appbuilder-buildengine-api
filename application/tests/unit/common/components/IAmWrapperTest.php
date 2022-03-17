<?php
namespace tests\unit\common\components;
use common\components\IAmWrapper;

use tests\unit\UnitTestBase;

use tests\mock\aws\iam\MockIamClient;
use tests\mock\aws\iam\MockIamGroup;
use tests\mock\aws\iam\MockIamResult;
use tests\mock\aws\iam\MockIamUser;

use common\models\Project;

use tests\unit\fixtures\common\models\ProjectFixture;

class IAmWrapperTest extends UnitTestBase
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
    public function testAddUserToIamGroup()
    {
        $this->setContainerObjects();
        $iamWrapper = new IAmWrapper();
        $project = Project::findOne(['id' => 102]);
        $result = $iamWrapper->addUserToIamGroup($project->user_id, $project->groupName());
        $expected = "david_moore";
        $this->assertEquals($expected, $result->parms['UserName'], " *** User name invalid");
        $expected2 = "CodeCommit-SIL";
        $this->assertEquals($expected2, $result->parms['GroupName'], " *** Group name invalid");
    }    
    public function testRemoveUserFromIamGroup()
    {
        $this->setContainerObjects();
        $iamWrapper = new IAmWrapper();
        $project = Project::findOne(['id' => 102]);
        $result = $iamWrapper->removeUserFromIamGroup($project->user_id, $project->groupName());
        $expected = "david_moore";
        $this->assertEquals($expected, $iamWrapper->iamClient->parms['UserName'], " *** User name invalid");
        $expected2 = "CodeCommit-SIL";
        $this->assertEquals($expected2, $iamWrapper->iamClient->parms['GroupName'], " *** Group name invalid");
    }    
    public function testAddPublicSshKeyNewKey()
    {
        $this->setContainerObjects();
        $iamWrapper = new IAmWrapper();
        $user_name = 'normal';
        $public_key = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx';
        $result = $iamWrapper->addPublicSshKey( $user_name, $public_key );
        $this->assertEquals('normal', $result['UserName'], " *** User name invalid");
        $this->assertEquals('AABBCC', $result['SSHPublicKey']['SSHPublicKeyId']);
        $this->assertEquals($public_key, $result['SSHPublicKey']['SSHPublicKeyBody'], " *** Public key invalid");
    }
    public function testAddPublishSshKeyDuplicateKey()
    {
        $this->setContainerObjects();
        $iamWrapper = new IAmWrapper();
        $user_name = 'throwKey';
        $public_key = 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx';
        $result = $iamWrapper->addPublicSshKey( $user_name, $public_key );
        $this->assertEquals('throwKey', $result['UserName'], " *** User name invalid");
        $this->assertEquals($public_key, $result['SSHPublicKey']['SSHPublicKeyBody'], " *** Public key invalid");
    }
    public function testAddPublishSshKeyDuplicateKeyNoMatch()
    {
        $this->expectException('yii\web\ServerErrorHttpException');
        $this->setContainerObjects();
        $iamWrapper = new IAmWrapper();
        $user_name = 'throwKey';
        $public_key = 'ssh-rsa BBBBC3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx';
        $result = $iamWrapper->addPublicSshKey( $user_name, $public_key );
    }
    public function testAddPublishSshKeyDuplicateKeyCantSave()
    {
        $this->expectException('yii\web\ServerErrorHttpException');
        $this->setContainerObjects();
        $iamWrapper = new IAmWrapper();
        $user_name = 'throwKeyTwice';
        $public_key = 'ssh-rsa BBBBC3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx';
        $result = $iamWrapper->addPublicSshKey( $user_name, $public_key );
    }
    public function testCreateAwsAccount()
    {
        $this->setContainerObjects();
        $iamWrapper = new IAmWrapper();
        $project = Project::findOne(['id' => 102]);
        $user = $iamWrapper->createAwsAccount( $project->user_id);
        $expected = "david_moore";
        $returned = $user['User']['UserName'];
        $this->assertEquals($expected, $returned, " *** User name invalid");
    } 
}
