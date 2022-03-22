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
                case 'TARGETS':
                    $targets = $environmentVariable['value'];
                    break;
                case 'SCRIPT_S3':
                    $scriptS3 = $environmentVariable['value'];
                    break;
                case 'VAR1':
                    $var1 = $environmentVariable['value'];
                    break;
                case 'VAR2' :
                    $var2 = $environmentVariable['value'];
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
        $this->assertEquals('play-listing', $targets, " *** Wrong target");
        $this->assertEquals('s3://sil-prd-aps-projects/default', $scriptS3, " ***Wrong S3 Script");
        $this->assertEquals("VALUE1", $var1, " *** Wrong test var1 value");
        $this->assertEquals("VALUE2", $var2, " *** Wrong test var2 value");
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
                    $releaseNumber = $environmentVariable['value'];
                    break;
                case 'BUILD_NUMBER':
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
                case 'TARGETS':
                    $targets = $environmentVariable['value'];
                    break;
                case 'SCRIPT_S3':
                    $scriptS3 = $environmentVariable['value'];
                    break;
                case 'VAR1':
                    $var1 = $environmentVariable['value'];
                    break;
                case 'VAR2' :
                    $var2 = $environmentVariable['value'];
                    break;
                case 'SCRIPTURE_EARTH_KEY';
                    $scriptureEarthKey = $environmentVariable['value'];
                    break;
                default:
                    $this->assertEquals("Unknown", $environmentVariable['name'], " *** Unexpected variable definition");     
            }
        }

        $this->assertEquals("12", $releaseNumber, " *** Wrong release number");
        $this->assertEquals("12", $buildNumber, " *** Wrong build number");
        $this->assertEquals("wycliffeusa", $publisher, " *** Wrong publisher");
        $this->assertEquals("sil-prd-aps-secrets", $secretsBucket, " *** Wrong secrets bucket");
        $this->assertEquals("s3://sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/12", $artifactDir, " *** Wrong artifact directory");
        $this->assertEquals("alpha", $channel, " *** Bad channel");
        $this->assertEquals("", $promoteFrom, " *** Wrong promote from value");
        $this->assertEquals("google-play", $targets, " *** Wrong target");
        $this->assertEquals("s3://sil-prd-aps-projects/default", $scriptS3, " *** Wrong S3 Script");
        $this->assertEquals("VALUE1", $var1, " *** Wrong test var1 value");
        $this->assertEquals("VALUE2", $var2, " *** Wrong test var2 value");
        $this->assertEquals("0123456789abcdef", $scriptureEarthKey, " *** Wrong ScriptureEarthKey");
    }
    public function testAddEnvironmentVariables()
    {
        $this->setContainerObjects();
        $build = Build::findOne(['id' => 12]);
        $buildNumber = '1';
        $buildPath = 'build';
        $environmentArray = [
            [
                'name' => 'BUILD_NUMBER',
                'value' => $buildNumber,
            ],
            [
                'name' => 'APP_BUILDER_SCRIPT_PATH',
                'value' => $buildPath,
            ],
        ];
        MockCodeBuildClient::clearGlobals();
        $codebuild = new CodeBuild();
        $method = $this->getPrivateMethod('common\components\CodeBuild', 'addEnvironmentToArray');
        $returnedEnvironment = $method->invokeArgs($codebuild, array( $environmentArray, $build->environment));
        $this->assertEquals(4, sizeof($returnedEnvironment), "*** Wrong array size");
        $env0 = $returnedEnvironment[0];
        $env2 = $returnedEnvironment[2];
        $env3 = $returnedEnvironment[3];
        $this->assertEquals("BUILD_NUMBER", $env0['name'], ' *** Wrong name for 1st env var');
        $this->assertEquals("1", $env0['value'], " *** Wrong value for first env var");
        $this->assertEquals("VAR1", $env2['name'], " *** Wrong name for 3rd env var");
        $this->assertEquals("VALUE1", $env2['value'], " *** Wrong value for 3rd env var");
        $this->assertEquals("VAR2", $env3['name'], " *** Wrong name for 4th env var");
        $this->assertEquals("VALUE2", $env3['value'], " *** Wrong value for 4th env var");
    }
}
