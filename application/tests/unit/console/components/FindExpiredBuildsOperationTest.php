<?php
namespace tests\unit\console\components;
use console\components\FindExpiredBuildsOperation;
use tests\unit\UnitTestBase;

use common\models\Build;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;

class FindExpiredBuildsOperationTest extends UnitTestBase
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
        $jobNumber = 25;
        $findExpiredBuildsOperation = new FindExpiredBuildsOperation($jobNumber);
        $findExpiredBuildsOperation->performOperation();
        $build = Build::findOne(['id' => 18]);
        $this->assertEquals(Build::STATUS_EXPIRED, $build->status, " *** Build should be marked expired");
        $build = Build::findOne(['id' => 19]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Build should be left completed");
        $build = Build::findOne(['id' => 20]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Build should be left completed");
    }

}

