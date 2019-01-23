<?php
namespace tests\unit\common\components;
use common\components\CodeBuild;

use tests\unit\UnitTestBase;

use tests\mock\aws\codebuild\MockCodeBuildClient;

use common\models\Job;
use common\models\Build;
use common\models\Release;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;

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
            'release' => ReleaseFixture::className(),
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
        $retVal = $codebuild->startBuild($url, $commitId, $build, $buildspec, $versionCode, true);
        $this->assertEquals('7049fc2a-db58-4c33-8d4e-c0c568b25c7a', $retVal, " *** Wrong guid returned");
        $this->assertEquals(1, count(MockCodeBuildClient::$builds), " *** Wrong number of builds");
        $mockBuild = MockCodeBuildClient::$builds[0];
        $environmentVariables = $mockBuild['environmentVariablesOverride'];
        foreach ($environmentVariables as $environmentVariable) {
            switch ($environmentVariable['name']) {
                case 'BUILD_NUMBER':
                    $buildNumber = $environmentVariable['value'];
                    break;
                case 'PUBLISHER':
                    $publisher = $environmentVariable['value'];
                    break;
                case 'SECRETS_BUCKET':
                    $secretsBucket = $environmentVariable['value'];
                    break;
                case 'APP_BUILDER_SCRIPT_PATH':
                    $scriptPath = $environmentVariable['value'];
                    break;
                case 'VERSION_CODE':
                    $versionCode = $environmentVariable['value'];
                    break;
                default:
                    $this->assertEquals("Unknown", $environmentVariable['name'], " *** Unexpected variable definition");     
            }
        }

        $this->assertEquals("13", $buildNumber, " *** Wrong build number");
        $this->assertEquals("wycliffeusa", $publisher, " *** Wrong publisher");
        $this->assertEquals("sil-prd-aps-secrets", $secretsBucket, " *** Wrong secrets bucket");
        $this->assertEquals("1", $versionCode, " *** Wrong version code");
        $this->assertEquals("scripture-app-builder", $scriptPath, " *** Bad script path");
    }
    public function testStartBuildS3()
    {
        $this->setContainerObjects();
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $url = 's3://dem-stg-aps-projects/scriptureappbuilder/en-cuk-108-SanBlasKunaGospels';
        $commitId = '07fc609fc5c2344afcf60d0f97cc7bb6f1945ede';
        $build = Build::findOne(['id' => 30]);
        $buildspec = '      - echo "This is a test"';
        $versionCode = '1';
        $retVal = $codebuild->startBuild($url, $commitId, $build, $buildspec, $versionCode, false);
        $this->assertEquals('7049fc2a-db58-4c33-8d4e-c0c568b25c7c', $retVal, " *** Wrong guid returned");
        $this->assertEquals(1, count(MockCodeBuildClient::$builds), " *** Wrong number of builds");
        $mockBuild = MockCodeBuildClient::$builds[0];
        $environmentVariables = $mockBuild['environmentVariablesOverride'];
        foreach ($environmentVariables as $environmentVariable) {
            switch ($environmentVariable['name']) {
                case 'BUILD_NUMBER':
                    $buildNumber = $environmentVariable['value'];
                    break;
                case 'PUBLISHER':
                    $publisher = $environmentVariable['value'];
                    break;
                case 'SECRETS_BUCKET':
                    $secretsBucket = $environmentVariable['value'];
                    break;
                case 'APP_BUILDER_SCRIPT_PATH':
                    $scriptPath = $environmentVariable['value'];
                    break;
                case 'VERSION_CODE':
                    $versionCode = $environmentVariable['value'];
                    break;
                case 'PROJECT_S3':
                    $projectS3 = $environmentVariable['value'];
                    break;
                default:
                    $this->assertEquals("Unknown", $environmentVariable['name'], " *** Unexpected variable definition");
            }
        }

        $this->assertEquals("30", $buildNumber, " *** Wrong build number");
        $this->assertEquals("wycliffeusa", $publisher, " *** Wrong publisher");
        $this->assertEquals("sil-prd-aps-secrets", $secretsBucket, " *** Wrong secrets bucket");
        $this->assertEquals("1", $versionCode, " *** Wrong version code");
        $this->assertEquals("scripture-app-builder", $scriptPath, " *** Bad script path");
        $this->assertEquals($url, $projectS3, " *** Wrong project name");
    }
    public function testGetSourceLocation()
    {
        $this->setContainerObjects();
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $method = $this->getPrivateMethod('common\components\CodeBuild', 'getSourceLocation');
        $build = Build::findOne(['id' => 19]);
        $apkArn = $method->invokeArgs($codebuild, array( $build));
        $expected = 'arn:aws:s3:::sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_25/19/Test-1.0.apk';
        $this->assertEquals($expected, $apkArn, " *** Wrong Arn returned");
    }
    public function testGetArtifactsLocation()
    {
        $this->setContainerObjects();
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $method = $this->getPrivateMethod('common\components\CodeBuild', 'getArtifactsLocation');
        $build = Build::findOne(['id' => 19]);
        $apkArn = $method->invokeArgs($codebuild, array( $build));
        $expected = 's3://sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_25/19';
        $this->assertEquals($expected, $apkArn, " *** Wrong S3 location url returned");
    }
    public function testStartRelease()
    {
        $this->setContainerObjects();
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $release = Release::findOne(['id' => 12]);
        $buildspec = '      - echo "This is a test"';
        $retVal = $codebuild->startRelease($release, $buildspec);
        $this->assertEquals('7049fc2a-db58-4c33-8d4e-c0c568b25c8a', $retVal, " *** Wrong guid returned");
        $this->assertEquals(1, count(MockCodeBuildClient::$builds), " *** Wrong number of builds");
        $mockBuild = MockCodeBuildClient::$builds[0];
        $environmentVariables = $mockBuild['environmentVariablesOverride'];
        foreach ($environmentVariables as $environmentVariable) {
            switch ($environmentVariable['name']) {
                case 'RELEASE_NUMBER':
                    $buildNumber = $environmentVariable['value'];
                    break;
                case 'PUBLISHER':
                    $publisher = $environmentVariable['value'];
                    break;
                case 'SECRETS_BUCKET':
                    $secretsBucket = $environmentVariable['value'];
                    break;
                case 'CHANNEL':
                    $channel = $environmentVariable['value'];
                    break;
                case 'ARTIFACTS_S3_DIR':
                    $artifactDir = $environmentVariable['value'];
                    break;
                case 'PROMOTE_FROM':
                    $promoteFrom = $environmentVariable['value'];
                    break;
                default:
                    $this->assertEquals("Unknown", $environmentVariable['name'], " *** Unexpected variable definition");     
            }
        }

        $this->assertEquals("12", $buildNumber, " *** Wrong build number");
        $this->assertEquals("wycliffeusa", $publisher, " *** Wrong publisher");
        $this->assertEquals("sil-prd-aps-secrets", $secretsBucket, " *** Wrong secrets bucket");
        $this->assertEquals("s3://sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/12", $artifactDir, " *** Wrong artifact directory");
        $this->assertEquals("alpha", $channel, " *** Bad channel");
        $this->assertEquals("", $promoteFrom, " *** Wrong promote from value");
    }
}