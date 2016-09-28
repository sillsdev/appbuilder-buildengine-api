<?php
namespace tests\unit\frontend\components;
use frontend\components\JobControllerUtils;
use tests\unit\UnitTestBase;
use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;
use common\models\Build;

class JobControllerUtilsTest extends UnitTestBase
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
    public function testViewJob()
    {
        $jobControllerUtils = new JobControllerUtils();
        $job = $jobControllerUtils->viewJob("23");
        $this->assertEquals('1451_345910', $job->request_id, "*** Job doesn't match requested job");
    }
    public function testViewJobException()
    {
        $this->setExpectedException('yii\\web\\NotFoundHttpException');
        $jobControllerUtils = new JobControllerUtils();
        $job = $jobControllerUtils->viewJob("87");        
    }
    public function testPublishBuild()
    {
        $jobControllerUtils = new JobControllerUtils();
        $release = $jobControllerUtils->publishBuild('23', '21', 'alpha', 'test', 'en');
        $this->assertEquals('21', $release->build_id, "*** Build id in published release incorrect");
        $this->assertEquals('alpha', $release->channel, "*** Channel in published release incorrect");
        $this->assertNull($release->promote_from, "*** Promote from for unpublished build is not null");
    }
    public function testPublishBuildAlphaToBeta()
    {
        $jobControllerUtils = new JobControllerUtils();
        $release = $jobControllerUtils->publishBuild('25', '24', 'beta', 'test', 'en');
        $this->assertEquals('24', $release->build_id, "*** Build id in published release incorrect");
        $this->assertEquals('beta', $release->channel, "*** Channel in published release incorrect");
        $this->assertEquals('alpha', $release->promote_from, "*** Promote From incorrect");
    }
    public function testPublishBuildAlphaToProduction()
    {
        $jobControllerUtils = new JobControllerUtils();
        $release = $jobControllerUtils->publishBuild('25', '24', 'production', 'test', 'en');
        $this->assertEquals('24', $release->build_id, "*** Build id in published release incorrect");
        $this->assertEquals('production', $release->channel, "*** Channel in published release incorrect");
        $this->assertEquals('alpha', $release->promote_from, "*** Promote From incorrect");
    }
    public function testPublishBuildBetaToAlpha()
    {
        $this->setExpectedException('yii\\web\\ServerErrorHttpException');
        $jobControllerUtils = new JobControllerUtils();
        $release = $jobControllerUtils->publishBuild('24', '25', 'alpha', 'test', 'en');
    }
    public function testPublishBuildBetaToBeta()
    {
        $jobControllerUtils = new JobControllerUtils();
        $release = $jobControllerUtils->publishBuild('24', '25', 'beta', 'test', 'en');
        $this->assertEquals('beta', $release->channel, "*** Channel in published release incorrect");
        $this->assertNull($release->promote_from);
    }
    public function testPublishBuildBetaToProduction()
    {
        $jobControllerUtils = new JobControllerUtils();
        $release = $jobControllerUtils->publishBuild('24', '25', 'production', 'test', 'en');
        $this->assertEquals('production', $release->channel, "*** Channel in published release incorrect");
        $this->assertEquals('beta', $release->promote_from, "*** Promote From incorrect");
    }
    public function testPublishBuildExceptionJobDoesntExist()
    {
        $this->setExpectedException('yii\\web\\NotFoundHttpException');
        $jobControllerUtils = new JobControllerUtils();
        $release = $jobControllerUtils->publishBuild('87', '21', 'alpha', 'test', 'en');
    }
    public function testPublishBuildExceptionBadUrl()
    {
        $this->setExpectedException('yii\\web\\ServerErrorHttpException');
        $jobControllerUtils = new JobControllerUtils();
        $release = $jobControllerUtils->publishBuild('22', '11', 'alpha', 'test', 'en');
    }
    public function testVerifyChannel()
    {
        $jobControllerUtils = new JobControllerUtils();
        $status = $jobControllerUtils->verifyChannel('25', 'beta', '4');
        $this->assertNull($status, "*** Channel with different version code different channel fails");
        $status = $jobControllerUtils->verifyChannel('25', 'alpha', '3');
        $this->assertNull($status, "*** Channel with same version code same channel fails");
        $status = $jobControllerUtils->verifyChannel('25', 'alpha', '4');
        $this->assertNull($status, "*** Channel with different version code same channel fails");
        $status = $jobControllerUtils->verifyChannel('25', 'beta', '3');
        $this->assertEquals("alpha", $status, "*** Channel with same version code different channel promotion fails");
        
    }
    public function testVerifyChannelException()
    {
        $this->setExpectedException('yii\\web\\ServerErrorHttpException');
        $jobControllerUtils = new JobControllerUtils();
        $status = $jobControllerUtils->verifyChannel('24', 'alpha', '6');
   }
   public function testGetHightestChannel()
   {
        $jobControllerUtils = new JobControllerUtils();
        $method = $this->getPrivateMethod('frontend\components\JobControllerUtils', 'getHighestPublishedChannel');
        $build = Build::findOne(['id' => 24]);
        $result = $method->invokeArgs($jobControllerUtils, array( "unpublished", $build));
        $this->assertEquals("alpha", $result, "*** Unpublished to alpha failed");
        $result = $method->invokeArgs($jobControllerUtils, array( "beta", $build));
        $this->assertEquals("beta", $result, "*** beta to alpha failed");
        $result = $method->invokeArgs($jobControllerUtils, array( "production", $build));
        $this->assertEquals("production", $result, "*** production to alpha failed");
        $build = Build::findOne(['id' => 25]);
        $result = $method->invokeArgs($jobControllerUtils, array( "alpha", $build));
        $this->assertEquals("beta", $result, "*** alpha to beta failed");
        $result = $method->invokeArgs($jobControllerUtils, array( "production", $build));
        $this->assertEquals("production", $result, "*** production to beta failed");
        $result = $method->invokeArgs($jobControllerUtils, array( "beta", $build));
        $this->assertEquals("beta", $result, "*** beta to beta failed");
        $build = Build::findOne(['id' => 26]);
        $result = $method->invokeArgs($jobControllerUtils, array( "alpha", $build));
        $this->assertEquals("production", $result, "*** alpha to production failed");
        $result = $method->invokeArgs($jobControllerUtils, array( "beta", $build));
        $this->assertEquals("production", $result, "*** beta to production failed");
   }
}