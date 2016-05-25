<?php
namespace tests\unit\common\components;
use console\components\CopyToS3Operation;
use tests\unit\UnitTestBase;

use tests\mock\jenkins\MockJenkins;
use tests\mock\s3client\MockS3Client;

use common\models\Job;
use common\models\Build;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;

class CopyToS3OperationTest extends UnitTestBase
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
            'job' => JobFixture::className(),
            'build' => BuildFixture::className(),
        ];
    }
    /**
     * This method is only here because if I don't put it in, the first test
     * fails because the params isn't loaded.  Don't know why, but this fixed it
     */
    public function testDummy()
    {
        $this->assertEquals(1,1);
    }
    public function testPerformAction()
    {
        $this->setContainerObjects();
        $buildNumber = 11;
        $copyOperation = new CopyToS3Operation($buildNumber);
        $copyOperation->performOperation();
        $build = Build::findOne(['id' => 11]);
        $this->assertEquals(42, $build->version_code, " *** Version code should be set to 42");
        $expectedUrl = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/TestPublishing-1.0.apk";
        $this->assertEquals($expectedUrl, $build->artifact_url, " *** Incorrect Artifact Url");
    }
}

