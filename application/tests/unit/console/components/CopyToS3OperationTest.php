<?php
namespace tests\unit\console\components;
use console\components\CopyToS3Operation;
use tests\unit\UnitTestBase;

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

    public function testPerformAction()
    {
        $this->setContainerObjects();
        $buildNumber = 11;
        $copyOperation = new CopyToS3Operation($buildNumber);
        $copyOperation->performOperation();
        $build = Build::findOne(['id' => 11]);
        $this->assertEquals(42, $build->version_code, " *** Version code should be set to 42");
        $expectedUrl = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1|about.txt,Kuna_Gospels-1.0.apk,version_code.txt,play-listing/index.html";
        $this->assertEquals($expectedUrl, $build->artifact_url, " *** Incorrect Artifact Url");
    }
}

