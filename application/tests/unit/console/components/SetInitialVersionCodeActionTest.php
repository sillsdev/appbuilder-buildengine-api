<?php
namespace tests\unit\console\components;
use console\components\SetInitialVersionCodeAction;
use common\models\Job;
use tests\unit\UnitTestBase;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;

class SetInitialVersionCodeActionTest extends UnitTestBase
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
    public function testPerformActionForSetInitialVC()
    {
        $this->setContainerObjects();
        $job_id = "22";
        $initialVC = "10";
        $action = new SetInitialVersionCodeAction($job_id, $initialVC);
        $action->performAction();
        $job = Job::findById($job_id);
        $expected = 10;
        $this->assertEquals($expected, $job->initial_version_code, " *** Wrong initial version code");
    }
}