<?php
namespace tests\unit\console\components;
use console\components\RemoveExpiredBuildsAction;
use tests\unit\UnitTestBase;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;
use tests\mock\aws\s3\MockS3Client;

class RemoveExpiredBuildsActionTest extends UnitTestBase
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
    public function testPerformActionForExpiredBuilds()
    {
/*        MockS3Client::clearGlobals();
        $this->setContainerObjects();
        $removeExpiredBuildsAction = new RemoveExpiredBuildsAction();
        $removeExpiredBuildsAction->performAction();
        $this->assertEquals(1, count(MockS3Client::$deletes), " *** Wrong number of deletes to S3");
        $delete = MockS3Client::$deletes[0];
        $expected = "sil-appbuilder-artifacts";
        $this->assertEquals($expected, $delete['bucket'], " *** Wrong bucket name deleted");
        $expected = "testing/jobs/build_scriptureappbuilder_24/27/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
        */
    }

}