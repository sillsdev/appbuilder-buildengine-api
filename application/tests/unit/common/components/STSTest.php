<?php

namespace tests\unit\common\components;


use common\components\AWSCommon;
use tests\unit\UnitTestBase;

use common\models\Project;
use common\components\STS;

use tests\mock\aws\sts\MockStsClient;

use tests\unit\fixtures\common\models\ProjectFixture;



class STSTest extends UnitTestBase
{
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

    public function testGetProjectAccessTokenReadOnly()
    {
        $this->setContainerObjects();
        $user = "bob";
        $project = Project::findOne(['id' => 108]);
        $sts = new STS();
        $result = $sts->getProjectAccessToken($project,$user, false);
        $this->assertArrayHasKey('AccessKeyId', $result, " *** AccessKeyId is missing");
        $this->assertArrayHasKey('SecretAccessKey', $result, " *** SecretAccessKey is missing");
        $this->assertArrayHasKey('SessionToken', $result, " *** SessionToken is missing");
        $this->assertArrayHasKey('Expiration', $result, " *** Expiration is missing");
        $this->assertArrayHasKey('Region', $result, " *** Region key is missing");
        $this->assertEquals('us-west-2', $result['Region'], " *** Region key is incorrect");
        $this->assertEquals(false, $result['ReadOnly'], " *** ReadOnly key is incorrect");
    }

    public function testGetProjectAccessTokenReadWrite()
    {
        $this->setContainerObjects();
        $user = "bob";
        $project = Project::findOne(['id' => 108]);
        $sts = new STS();
        $result = $sts->getProjectAccessToken($project,$user, true);
        $this->assertArrayHasKey('AccessKeyId', $result, " *** AccessKeyId is missing");
        $this->assertArrayHasKey('SecretAccessKey', $result, " *** SecretAccessKey is missing");
        $this->assertArrayHasKey('SessionToken', $result, " *** SessionToken is missing");
        $this->assertArrayHasKey('Expiration', $result, " *** Expiration is missing");
        $this->assertArrayHasKey('Region', $result, " *** Region key is missing");
        $this->assertEquals('us-west-2', $result['Region'], " *** Region key is incorrect");
        $this->assertEquals(true, $result['ReadOnly'], " *** ReadOnly key is incorrect");
    }

}