<?php
namespace tests\unit\console\components;

use tests\mock\jenkins\MockJenkins;
use tests\mock\jenkins\MockJenkinsJob;
use common\models\Build;
use common\models\OperationQueue;

use console\components\ManageBuildsAction;
use console\components\SetInitialVersionCodeAction;

use tests\unit\UnitTestBase;
use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\OperationQueueFixture;

class ManageBuildActionTest extends UnitTestBase
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
            'operation' => OperationQueueFixture::className(),
        ];
    }
    /**
     * The start building tests test the method in ActionCommon
     */
    public function testStartBuildIsBuilding()
    {
        $this->setContainerObjects();
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, true, 2, 1, "testJob1");
        $buildsAction = new ManageBuildsAction();
        $params = [];
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'startBuildIfNotBuilding');
        $jenkinsBuild = $method->invokeArgs($buildsAction, array( $jenkinsJob, $params, 5, 1));
        $this->assertNull($jenkinsBuild, " *** Build should be null if build in progress");
    }
    public function testStartBuildNotBuilding()
    {
        $this->setContainerObjects();
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, false, 4, 1, "testJob2");
        $buildsAction = new ManageBuildsAction();
        $params = [];
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'startBuildIfNotBuilding');
        $jenkinsBuild = $method->invokeArgs($buildsAction, array( $jenkinsJob, $params, 5, 1));
        $this->assertNotNull($jenkinsBuild, " *** Build should not be null if build not in progress");
        $this->assertEquals(2, $jenkinsBuild->getNumber(), " *** Build number increments by one");
    }
    public function testStartBuildTimeout()
    {
        $this->setContainerObjects();
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, false, 20, 1, "testJob3");
        $buildsAction = new ManageBuildsAction();
        $params = [];
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'startBuildIfNotBuilding');
        $jenkinsBuild = $method->invokeArgs($buildsAction, array( $jenkinsJob, $params, 2, 1));
        $this->assertNull($jenkinsBuild, " *** Build should be null if timed out");
    }
    public function testStartBuildFirstBuild()
    {
        $this->setContainerObjects();
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, false, 4, 0, "testJob4");
        $buildsAction = new ManageBuildsAction();
        $params = [];
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'startBuildIfNotBuilding');
        $jenkinsBuild = $method->invokeArgs($buildsAction, array( $jenkinsJob, $params, 5, 1));
        $this->assertNotNull($jenkinsBuild, " *** Build should not be null if build not in progress");
        $this->assertEquals(1, $jenkinsBuild->getNumber(), " *** Build number increments by one");
    }
    public function testPerformAction()
    {
        $this->setContainerObjects();
        $buildsAction = new ManageBuildsAction();
        $buildsAction->performAction();
        $build = Build::findOne(['id' => 13]);
        $this->assertEquals(Build::STATUS_ACTIVE, $build->status, " *** Status should be active");
        $build = Build::findOne(['id' => 14]);
        $this->assertEquals(Build::STATUS_POSTPROCESSING, $build->status, " *** Status should be postprocessing after successful completion");
        $this->assertEquals("SUCCESS", $build->result, " *** Result should be Success after good successful completion");
        $build = Build::findOne(['id' => 15]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Status should be completed after failure");
        $this->assertEquals("FAILURE", $build->result, " *** Result should be Failure after failed build");
        $build = Build::findOne(['id' => 16]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Status should be completed after abort");
        $this->assertEquals("ABORTED", $build->result, " *** Result should be aborted after an aborted build");
        $queuedRecords = OperationQueue::find()->count();
        $this->assertEquals(6, $queuedRecords, " *** Queued record count should be ");
        $queuedErrorRecords = OperationQueue::find()->where(['operation' => OperationQueue::SAVEERRORTOS3])->count();
        $this->assertEquals(2, $queuedErrorRecords, " *** SAVEERRORTOS3 Count should be 2 ");
        $queuedSaveRecords = OperationQueue::find()->where(['operation' => OperationQueue::SAVETOS3])->count();
        $this->assertEquals(1, $queuedSaveRecords, " *** SAVETOS3 Count should be 1 ");
        $queuedFindExpiredRecords = OperationQueue::find()->where(['operation' => OperationQueue::FINDEXPIREDBUILDS])->count();
        $this->assertEquals(3, $queuedFindExpiredRecords, " *** FINDEXPIREDBUILDS Count should be 3 ");
    }
    public function testNextVersion()
    {
        $this->setContainerObjects();
        $buildsAction = new ManageBuildsAction();
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'getNextVersionCode');
        $build = Build::findOne(['id' => 13]);
        $job = $build->job;
        $versionCode = $method->invokeArgs($buildsAction, array( $job, $build));
        $this->assertEquals(3, $versionCode, " *** version code should max for completed job +1"); 
    }
    public function testNextVersionWithSet()
    {
        $this->setContainerObjects();
        $job_id = "22";
        $initialVC = "10";
        $action = new SetInitialVersionCodeAction($job_id, $initialVC);
        $action->performAction();
        $buildsAction = new ManageBuildsAction();
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'getNextVersionCode');
        $build = Build::findOne(['id' => 13]);
        $job = $build->job;
        $versionCode = $method->invokeArgs($buildsAction, array( $job, $build));
        $this->assertEquals(11, $versionCode, " *** version code should be initial version code +1");
    }
}
