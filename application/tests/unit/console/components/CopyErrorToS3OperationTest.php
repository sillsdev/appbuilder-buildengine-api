<?php
namespace tests\unit\console\components;
use console\components\CopyErrorToS3Operation;
use tests\unit\UnitTestBase;

use common\models\Build;
use common\models\Release;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;

class CopyErrorToS3OperationTest extends UnitTestBase
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
            'release' => ReleaseFixture::className(),
        ];
    }
    public function testPerformActionForReleaseError()
    {
        $this->setContainerObjects();
        $buildNumber = 10;
        $copyOperation = new CopyErrorToS3Operation($buildNumber, 'release');
        $copyOperation->performOperation();
        $release = Release::findOne(['id' => 10]);
        $expectedUrl = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/publish_scriptureappbuilder_22/1/consoleText";
        $this->assertEquals($expectedUrl, $release->error, " *** Incorrect Error Url");
    }
    public function testPerformActionForBuildError()
    {
        $this->setContainerObjects();
        $buildNumber = 17;
        $copyOperation = new CopyErrorToS3Operation($buildNumber, 'build');
        $copyOperation->performOperation();
        $build = Build::findOne(['id' => 17]);
        $expectedUrl = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/consoleText";
        $this->assertEquals($expectedUrl, $build->error, " *** Incorrect Error Url");
    }
}
