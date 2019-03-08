<?php
namespace tests\unit\console\components;

use tests\mock\console\controllers\MockCronController;
use common\models\Build;
use common\models\Job;
use common\models\OperationQueue;

use console\components\ManageBuildsAction;

use tests\unit\UnitTestBase;
use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\OperationQueueFixture;
use tests\mock\aws\codebuild\MockCodeBuildClient;
use tests\mock\aws\codecommit\MockCodeCommitClient;

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
    public function testFailBuild()
    {
        $this->setContainerObjects();
        $cronController = new MockCronController();
        $buildsAction = new ManageBuildsAction($cronController);
        $build = Build::findOne(['id' => 28]);
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'failBuild');
        $buildId = $method->invokeArgs($buildsAction, array( $build));
        $build2 = Build::findOne(['id' => 28]);
        $this->assertEquals(Build::RESULT_FAILURE, $build2->result);
        $this->assertEquals(Build::STATUS_COMPLETED, $build2->status);      
    }
    public function testNextVersion()
    {
        $this->setContainerObjects();
        $cronController = new MockCronController();
        $buildsAction = new ManageBuildsAction($cronController);
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'getVersionCode');
        $build = Build::findOne(['id' => 13]);
        $job = $build->job;
        $versionCode = $method->invokeArgs($buildsAction, array( $job, $build));
        $this->assertEquals(2, $versionCode, " *** version code should max for completed job"); 
    }
    public function testNextVersionWithSet()
    {
        $this->setContainerObjects();
        $cronController = new MockCronController();
        $initialVC = "10";
        $job = Job::findOne(['id' => 22]);
        $job->existing_version_code = $initialVC;
        $job->save();
        $buildsAction = new ManageBuildsAction($cronController);
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'getVersionCode');
        $build = Build::findOne(['id' => 13]);
        $job2 = $build->job;
        $versionCode = $method->invokeArgs($buildsAction, array( $job2, $build));
        $this->assertEquals(10, $versionCode, " *** version code should be initial version code");
    }    /**
     * The start building tests test the method in ActionCommon
     */
    public function testCheckStartedBuildNotStarted()
    {
        $this->setContainerObjects();
        MockCodeCommitClient::clearGlobals();
        $cronController = new MockCronController();
        $buildsAction = new ManageBuildsAction($cronController);
        $build = Build::findOne(['id' => 22]);
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'tryStartBuild');
        $method->invokeArgs($buildsAction, array($build));
        $build = Build::findOne(['id' => 22]);
        $this->assertEquals('7049fc2a-db58-4c33-8d4e-c0c568b25c7b', $build->build_guid, " *** Guid was not changed correctly");
        $this->assertEquals(Build::STATUS_ACTIVE, $build->status, " *** Status incorrect");
        $this->assertEquals(1, count(MockCodeCommitClient::$getRepo), " *** Wrong count for number of GetRepository");
    }
    public function testCheckStartedBuildS3NotStarted()
    {
        $this->setContainerObjects();
        MockCodeCommitClient::clearGlobals();
        MockCodeBuildClient::clearGlobals();
        $cronController = new MockCronController();
        $buildsAction = new ManageBuildsAction($cronController);
        $build = Build::findOne(['id' => 30]);
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'tryStartBuild');
        $method->invokeArgs($buildsAction, array($build));
        $build = Build::findOne(['id' => 30]);
        $this->assertEquals('7049fc2a-db58-4c33-8d4e-c0c568b25c7c', $build->build_guid, " *** Guid was not changed correctly");
        $this->assertEquals(Build::STATUS_ACTIVE, $build->status, " *** Status incorrect");
        $this->assertEquals(1, count(MockCodeBuildClient::$builds), " *** Wrong count for number of StartBuildAsync");
        $this->assertEquals(0, count(MockCodeCommitClient::$getRepo), " *** Wrong count for number of GetRepository");
    }
    public function testCheckStartedBuildStarted()
    {
        $this->setContainerObjects();
        MockCodeCommitClient::clearGlobals();
        $cronController = new MockCronController();
        $buildsAction = new ManageBuildsAction($cronController);
        $build = Build::findOne(['id' => 13]);
        $method = $this->getPrivateMethod('console\components\ManageBuildsAction', 'tryStartBuild');
        $method->invokeArgs($buildsAction, array($build));
        $build = Build::findOne(['id' => 13]);
        $this->assertEquals(0, count(MockCodeCommitClient::$getRepo), " *** Wrong count for number of GetRepository");
        $this->assertNull($build->build_guid, " *** Build was running so build_number remain null");
        $this->assertEquals(Build::STATUS_INITIALIZED, $build->status, " *** Build was started so status updated");
    }
    public function testPerformAction()
    {
        $this->setContainerObjects();
        MockCodeCommitClient::clearGlobals();
        $cronController = new MockCronController();
        $buildsAction = new ManageBuildsAction($cronController);
        $buildsAction->performAction();
        $build = Build::findOne(['id' => 13]);
        $this->assertEquals(Build::STATUS_INITIALIZED, $build->status, " *** Status should be initialized since another job is active");
        $build = Build::findOne(['id' => 22]);
        $this->assertEquals(Build::STATUS_ACTIVE, $build->status, " *** Status should be active since no other active task for job");
        $build = Build::findOne(['id' => 14]);
        $this->assertEquals(Build::STATUS_POSTPROCESSING, $build->status, " *** Status should be postprocessing after successful completion");
        $this->assertNull($build->result, " *** Result should remain null when postprocessing");
        $build = Build::findOne(['id' => 15]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Status should be complete after failure");
        $this->assertEquals("FAILURE", $build->result, " *** Result should be Failure after failed build");
        $build = Build::findOne(['id' => 16]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Status should be completed after abort");
        $this->assertEquals("ABORTED", $build->result, " *** Result should be aborted after an aborted build");
        $build = Build::findOne(['id' => 21]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Status should be completed after failure");
        $this->assertEquals("FAILURE", $build->result, " *** Result should be Failure after failed build");
        $queuedRecords = OperationQueue::find()->count();
        $this->assertEquals(1, $queuedRecords, " *** Queued record count should be ");
        $queuedSaveRecords = OperationQueue::find()->where(['operation' => OperationQueue::SAVETOS3])->count();
        $this->assertEquals(1, $queuedSaveRecords, " *** SAVETOS3 Count should be 1 ");
    }

}
