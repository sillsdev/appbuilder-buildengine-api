<?php
namespace tests\unit\common\components;
use common\components\CodeBuild;

use tests\unit\UnitTestBase;

use tests\mock\aws\codebuild\MockCodeBuildClient;

use common\models\Job;
use common\models\Build;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;

class CodeBuildTest extends UnitTestBase
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

    // tests
    public function testGetBuildId()
    {
        $this->setContainerObjects();
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $method = $this->getPrivateMethod('common\components\CodeBuild', 'getBuildId');
        $guid = '7049fc2a-db58-4c33-8d4e-c0c568b25c7a Guid: 7049fc2a-db58-4c33-8d4e-c0c568b25c7a';
        $buildproject = 'build_app';
        $expected = 'build_app:7049fc2a-db58-4c33-8d4e-c0c568b25c7a Guid: 7049fc2a-db58-4c33-8d4e-c0c568b25c7a';
        $buildId = $method->invokeArgs($codebuild, array( $guid, $buildproject));
        $this->assertEquals($expected, $buildId, " ***Wrong Build ID");
    }
    public function testGetBuildPath()
    {
        $this->setContainerObjects();
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $method = $this->getPrivateMethod('common\components\CodeBuild', 'getBuildPath');
        $build = Build::findOne(['id' => 11]);
        $job = $build->job;
        $buildPath = $method->invokeArgs($codebuild, array( $job));
        $this->assertEquals('scripture-app-builder', $buildPath, " ***Wrong path for scripture app");
        $job->app_id = Job::APP_TYPE_READINGAPP;
        $buildPath = $method->invokeArgs($codebuild, array( $job));
        $this->assertEquals('reading-app-builder', $buildPath, " ***Wrong path for reading app");
        $job->app_id = Job::APP_TYPE_DICTIONARYAPP;
        $buildPath = $method->invokeArgs($codebuild, array( $job));
        $this->assertEquals('dictionary-app-builder', $buildPath, " ***Wrong path for dictionary app");
        $job->app_id = 'garbage';
        $buildPath = $method->invokeArgs($codebuild, array( $job));
        $this->assertEquals('unknown', $buildPath, " ***Wrong path for default");
    }
    public function testGetBuildStatus()
    {
        $this->setContainerObjects();
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $result = $codebuild->getBuildStatus('7049fc2a-db58-4c33-8d4e-c0c568b25c7a', 'build_app');
        $complete = $codebuild->isBuildComplete($result);
        $this->assertFalse($complete, " *** Incomplete test failed");
        $result = $codebuild->getBuildStatus('7049fc2a-db58-4c33-8d4e-c0c568b25c7b', 'build_app');
        $complete = $codebuild->isBuildComplete($result);
        $this->assertTrue($complete, " *** Complete Test Failed");
    }
    public function testStartBuild()
    {
        $this->setContainerObjects();
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $url = 'https://git-codecommit.us-east-1.amazonaws.com/v1/repos/scriptureappbuilder-LSDEV-eng-t4test';
        $commitId = '07fc609fc5c2344afcf60d0f97cc7bb6f1945ede';
        $build = Build::findOne(['id' => 13]);
        $buildspec = '      - echo "This is a test"';
        $versionCode = '1';
        $retVal = $codebuild->startBuild($url, $commitId, $build, $buildspec, $versionCode);
        $this->assertEquals('7049fc2a-db58-4c33-8d4e-c0c568b25c7a', $retVal, " *** Wrong guid returned");
    }
}