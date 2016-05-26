<?php
namespace tests\unit\console\components;
use console\components\S3MaintenanceAction;
use tests\unit\UnitTestBase;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;

class S3MaintenanceActionTest extends UnitTestBase
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
        $this->setContainerObjects();
        $s3MaintenanceAction = new S3MaintenanceAction();
        $s3MaintenanceAction->performAction();
        $s3 = $s3MaintenanceAction->getS3();
        $client = $s3->s3Client;
        $this->assertEquals(2, count($client->deletes), " *** Wrong number of deletes to S3");
        $delete = $client->deletes[0];
        $expected = "sil-appbuilder-artifacts";
        $this->assertEquals($expected, $delete['bucket'], " *** Wrong bucket name deleted");
        $expected = "testing/jobs/build_scriptureappbuilder_1/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
        $delete = $client->deletes[1];
        $expected = "testing/jobs/build_scriptureappbuilder_2/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
    }

}