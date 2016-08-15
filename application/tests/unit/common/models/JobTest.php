<?php
namespace tests\unit\common\models;
use tests\unit\UnitTestBase;

use common\models\Job;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;

class JobTest extends UnitTestBase
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
    public function testCreateScriptureAppJob()
    {
        $this->setContainerObjects();
        $jobRecord = new Job();
        $jobRecord->request_id = "createJobTest1";
        $jobRecord->publisher_id = 'wycliffeusa';
        $jobRecord->git_url = "ssh://git-codecommit.us-east-1.amazonaws.com/v1/repos/projects-development-dmoore-windows10-t4t";
        $jobRecord->app_id = "scriptureappbuilder";
        $jobRecord->save();
        $job = Job::findOne(['request_id' => 'createJobTest1']);
        $this->assertNotNull($job, " *** Job didn't save correctly");
        $this->assertEquals("createJobTest1", $job->request_id, "  *** Request ID incorrect"); 
    }
    public function testCreateBloombookAppJob()
    {
        $this->setContainerObjects();
        $jobRecord = new Job();
        $jobRecord->request_id = "createJobTest2";
        $jobRecord->publisher_id = 'wycliffeusa';
        $jobRecord->git_url = "3XynOB";
        $jobRecord->app_id = "bloomappmaker";
        $jobRecord->save();
        $job = Job::findOne(['request_id' => 'createJobTest2']);
        $this->assertNotNull($job, " *** Job didn't save correctly");
        $this->assertEquals("createJobTest2", $job->request_id, "  *** Request ID incorrect"); 
    }
    public function testInvalidAppJob()
    {
        $this->setContainerObjects();
        $jobRecord = new Job();
        $jobRecord->request_id = "createJobTest3";
        $jobRecord->publisher_id = 'wycliffeusa';
        $jobRecord->git_url = "ssh://git-codecommit.us-east-1.amazonaws.com/v1/repos/projects-development-dmoore-windows10-t4t";
        $jobRecord->app_id = "unknownapp";
        $jobRecord->save();
        $job = Job::findOne(['request_id' => 'createJobTest3']);
        $this->assertNull($job, " *** App rule failed");
    }
}
