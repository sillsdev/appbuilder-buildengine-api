<?php
namespace tests\unit\common\components;
use common\components\S3;

use \yii\codeception\DbTestCase;

use tests\mock\jenkins\MockJenkins;
use tests\mock\jenkins\MockJenkinsJob;
use tests\mock\jenkins\MockJenkinsUtils;

use common\models\Job;
use common\models\Build;
use common\models\OperationQueue;

use console\components\ManageBuildsAction;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\OperationQueueFixture;

class ManageBuildActionTest extends DbTestCase
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    public $appConfig = '@tests/codeception/config/config.php';

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
        $build = Build::findOne(['id' => 15]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Status should be completed after failure");
        $build = Build::findOne(['id' => 16]);
        $this->assertEquals(Build::STATUS_COMPLETED, $build->status, " *** Status should be completed after abort");
        $queuedRecords = OperationQueue::find()->count();
        $this->assertEquals(6, $queuedRecords, " *** Queued record count should be ");
        $queuedErrorRecords = OperationQueue::find()->where(['operation' => OperationQueue::SAVEERRORTOS3])->count();
        $this->assertEquals(2, $queuedErrorRecords, " *** SAVEERRORTOS3 Count should be 2 ");
        $queuedSaveRecords = OperationQueue::find()->where(['operation' => OperationQueue::SAVETOS3])->count();
        $this->assertEquals(1, $queuedSaveRecords, " *** SAVETOS3 Count should be 1 ");
        $queuedFindExpiredRecords = OperationQueue::find()->where(['operation' => OperationQueue::FINDEXPIREDBUILDS])->count();
        $this->assertEquals(3, $queuedFindExpiredRecords, " *** FINDEXPIREDBUILDS Count should be 3 ");
    }    /**
     * getPrivateMethod
     *
     * @author	Joe Sexton <joe@webtipblog.com>
     * @param 	string $className
     * @param 	string $methodName
     * @return	ReflectionMethod
     */
    public function getPrivateMethod( $className, $methodName ) {
            $reflector = new \ReflectionClass( $className );
            $method = $reflector->getMethod( $methodName );
            $method->setAccessible( true );

            return $method;
    }
    private function setContainerObjects() {
        \Yii::$container->set('fileUtils', 'tests\mock\common\components\MockFileUtils');
        \Yii::$container->set('jenkinsUtils', 'tests\mock\common\components\MockJenkinsUtils');
    }

}
