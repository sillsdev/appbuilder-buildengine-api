<?php
namespace tests\unit\console\components;

use tests\mock\jenkins\MockJenkins;
use tests\mock\jenkins\MockJenkinsJob;
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
    public function testCheckStartedBuildNotStarted()
    {
        return;
        $this->setContainerObjects();
        $releasesAction = new ManageReleasesAction();
        $release = Release::findOne(['id' => 16]);
        $method = $this->getPrivateMethod('console\components\ManageReleasesAction', 'checkReleaseStarted');
        $method->invokeArgs($releasesAction, array($release));
        $release = Release::findOne(['id' => 16]);
        $this->assertEquals(1, $release->build_number, " *** Release was not started so build_number should remain 1");
        $this->assertEquals(Release::STATUS_ACCEPTED, $release->status, " *** Release was not started so status not changed");
    }
    public function testCheckStartedBuildStarted()
    {
        $this->setContainerObjects();
        $releasesAction = new ManageReleasesAction();
        $release = Release::findOne(['id' => 17]);
        $method = $this->getPrivateMethod('console\components\ManageReleasesAction', 'checkReleaseStarted');
        $method->invokeArgs($releasesAction, array($release));
        $release = Release::findOne(['id' => 17]);
        $this->assertEquals(1, $release->build_number, " *** Release was started so build_number should increment to 1");
        $this->assertEquals(Release::STATUS_ACTIVE, $release->status, " *** Release was started so status updated");
    }
    public function testPerformAction()
    {
        $this->setContainerObjects();
        $releasesAction = new ManageReleasesAction();
        $releasesAction->performAction();
        $release = Release::findOne(['id' => 11]);
        $this->assertEquals(Release::STATUS_ACCEPTED, $release->status, " *** Status should be accepted");
        $this->assertEquals(1, $release->build_number, " *** build number should stay at 1 when going to accepted");
        $release = Release::findOne(['id' => 12]);
        $this->assertEquals(Release::STATUS_COMPLETED, $release->status, " *** Status should be completed after successful completion");
        $this->assertEquals("SUCCESS", $release->result, " *** Result should be set to successful after a good build");
        $build = Build::findOne(['id' => $release->build_id]);
        $this->assertEquals("alpha", $build->channel, " *** Successful release sets channel");
        $release = Release::findOne(['id' => 13]);
        $this->assertEquals(Build::STATUS_COMPLETED, $release->status, " *** Status should be completed after failure");
        $this->assertEquals("FAILURE", $release->result, " *** result should be failure after a failed build");
        $release = Release::findOne(['id' => 14]);
        $this->assertEquals(Build::STATUS_COMPLETED, $release->status, " *** Status should be completed after abort");
        $this->assertEquals("ABORTED", $release->result, " *** result should be aborted after an aborted build");
        $queuedRecords = OperationQueue::find()->count();
        $this->assertEquals(2, $queuedRecords, " *** Queued record count should be ");
        $queuedErrorRecords = OperationQueue::find()->where(['operation' => OperationQueue::SAVEERRORTOS3])->count();
        $this->assertEquals(2, $queuedErrorRecords, " *** SAVEERRORTOS3 Count should be 2 ");
        $release = Release::findOne(['id' => 15]);
        $this->assertEquals(Build::STATUS_COMPLETED, $release->status, " *** Status should be completed after failure");
        $this->assertEquals("FAILURE", $release->result, " *** result should be failure after a failed build");
    }
}
