<?php
namespace tests\unit\console\components;
use console\components\UpdateJobsOperation;
use tests\unit\UnitTestBase;
use tests\mock\jenkins\MockJenkinsJob;
use tests\mock\common\components\MockJenkinsUtils;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;

class UpdateJobsOperationTest extends UnitTestBase
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
    public function testPerformOperationForUpdateJobs()
    {
        $this->setContainerObjects();
        MockJenkinsJob::resetLaunchedJobs();
        $updateJobsOperation = new UpdateJobsOperation();
        $updateJobsOperation->performOperation();
        $launchedJobs = MockJenkinsJob::getLaunchedJobs();
        $this->assertEquals(2, count($launchedJobs), " *** 2 jobs should have been launched");
        $this->assertEquals("Build-Wrapper-Seed", $launchedJobs[0], " *** First job launched should be Build-Wrapper-Seed");
        $this->assertEquals("Publish-Wrapper-Seed", $launchedJobs[1], " *** First job launched should be Build-Wrapper-Seed");
    }
    public function testExceptionIfJenkinsDown()
    {
        $this->setExpectedException('\Exception');
        $this->setContainerObjects();
        MockJenkinsUtils::setReturnJenkins(false);
        $updateJobsOperation = new UpdateJobsOperation();
        $updateJobsOperation->performOperation();
        $this->setExpectedException('\Exception');
    }
}

