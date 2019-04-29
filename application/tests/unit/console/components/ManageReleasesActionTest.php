<?php
namespace tests\unit\console\components;

use tests\mock\console\controllers\MockCronController;
use common\models\Build;
use common\models\Release;
use common\models\OperationQueue;

use console\components\ManageReleasesAction;

use tests\unit\UnitTestBase;
use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;
use tests\unit\fixtures\common\models\OperationQueueFixture;

class ManageReleasesActionTest extends UnitTestBase
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
            'operation' => OperationQueueFixture::className(),
        ];
    }
    public function testTryStartReleaseNotStarted()
    {
        $this->setContainerObjects();
        $cronController = new MockCronController();
        $releasesAction = new ManageReleasesAction($cronController);
        $release = Release::findOne(['id' => 11]);
        $method = $this->getPrivateMethod('console\components\ManageReleasesAction', 'tryStartRelease');
        $method->invokeArgs($releasesAction, array($release));
        $release = Release::findOne(['id' => 11]);
        $this->assertEquals('7049fc2a-db58-4c33-8d4e-c0c568b25c8c', $release->build_guid, " *** Incorrect build guid");
        $this->assertEquals(Release::STATUS_ACTIVE, $release->status, " *** Release not active after start");
    }
    public function testPerformAction()
    {
        $this->setContainerObjects();
        $cronController = new MockCronController();
        $releasesAction = new ManageReleasesAction($cronController);
        $releasesAction->performAction();
        $release = Release::findOne(['id' => 11]);
        $this->assertEquals(Release::STATUS_ACTIVE, $release->status, " *** Status should be active");
        $this->assertEquals('7049fc2a-db58-4c33-8d4e-c0c568b25c8c', $release->build_guid, " *** Wrong guid for build 11");
        $release = Release::findOne(['id' => 12]);
        $this->assertEquals(Release::STATUS_POSTPROCESSING, $release->status, " *** Status should be completed after successful completion");
        $this->assertEquals("SUCCESS", $release->result, " *** Result should be set to successful after a good build");
        $build = Build::findOne(['id' => $release->build_id]);
        $this->assertEquals("alpha", $build->channel, " *** Successful release sets channel");
        $release = Release::findOne(['id' => 13]);
        $this->assertEquals(Build::STATUS_POSTPROCESSING, $release->status, " *** Status should be completed after failure");
        $this->assertEquals("FAILURE", $release->result, " *** result should be failure after a failed build");
        $release = Release::findOne(['id' => 16]);
        $this->assertEquals(Build::STATUS_POSTPROCESSING, $release->status, " *** Status should be completed after abort");
        $this->assertEquals("ABORTED", $release->result, " *** result should be aborted after an aborted build");
        $release = Release::findOne(['id' => 15]);
        $this->assertEquals(Build::STATUS_POSTPROCESSING, $release->status, " *** Status should be completed after failure");
        $this->assertEquals("FAILURE", $release->result, " *** result should be failure after a failed build");
        $queuedRecords = OperationQueue::find()->count();
        $this->assertEquals(5, $queuedRecords, " *** Queued record count should be ");
        $queuedSaveRecords = OperationQueue::find()->where(['operation' => OperationQueue::SAVETOS3])->count();
        $this->assertEquals(2, $queuedSaveRecords, " *** SAVETOS3 Count should be 1 ");
        $queuedSaveErrorRecords = OperationQueue::find()->where(['operation' => OperationQueue::SAVEERRORTOS3])->count();
        $this->assertEquals(3, $queuedSaveErrorRecords, " *** SAVEERRORTOS3 Count should be 3 ");
    }
    public function testFailRelease()
    {
        $this->setContainerObjects();
        $cronController = new MockCronController();
        $releasesAction = new ManageReleasesAction($cronController);
        $release = Release::findOne(['id' => 15]);
        $method = $this->getPrivateMethod('console\components\ManageReleasesAction', 'failRelease');
        $method->invokeArgs($releasesAction, array($release));
        $release = Release::findOne(['id' => 15]);
        $this->assertEquals(Build::STATUS_COMPLETED, $release->status, " *** Status should be completed after failure");
        $this->assertEquals("FAILURE", $release->result, " *** result should be failure after a failed build");
    }

}
