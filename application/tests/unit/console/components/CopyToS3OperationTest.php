<?php
namespace tests\unit\console\components;
use console\components\CopyToS3Operation;
use tests\unit\UnitTestBase;

use common\models\Build;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\mock\s3client\MockS3Client;
use tests\mock\common\components\MockJenkinsUtils;
use tests\mock\jenkins\MockJenkins;
use tests\mock\jenkins\MockJenkinsJob;
use tests\mock\jenkins\MockJenkinsBuild;

class CopyToS3OperationTest extends UnitTestBase
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

    public function testPerformAction()
    {
        $this->setContainerObjects();
        $buildNumber = 11;
        MockS3Client::clearGlobals();
        $copyOperation = new CopyToS3Operation($buildNumber);
        $copyOperation->performOperation();
        $build = Build::findOne(['id' => 11]);
        $this->assertEquals(42, $build->version_code, " *** Version code should be set to 42");
        $expectedBase = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/";
        $expectedFiles = "about.txt,Kuna_Gospels-1.0.apk,package_name.txt,version_code.txt,play-listing/index.html";
        $this->assertEquals($expectedBase, $build->artifact_url_base, " *** Incorrect Artifact Url Base");
        $this->assertEquals($expectedFiles, $build->artifact_files, " *** Incorrect Artifact Files");
        $this->assertEquals(16, count(MockS3Client::$puts), " *** Wrong number of files");
        $expected = "testing/jobs/build_scriptureappbuilder_22/1/play-listing.html";
        $testParms = MockS3Client::$puts[14];
        $this->assertEquals($expected, $testParms['Key'], " *** Wrong file name");
        $expected = "text/html";
        $this->assertEquals($expected, $testParms['ContentType'], " *** Wrong Mime Type");
    }
    public function testPerformActionNoArtifacts()
    {
        $this->setContainerObjects();
        $buildNumber = 20;
        MockS3Client::clearGlobals();
        $copyOperation = new CopyToS3Operation($buildNumber);
        $copyOperation->performOperation();
        $build = Build::findOne(['id' => 20]);
        $this->assertEquals(4, $build->version_code, " *** Version code should not be changed");
        $this->assertEquals(NULL, $build->artifact_url_base, " *** Incorrect Artifact Url Base");
        $this->assertEquals(NULL, $build->artifact_files, " *** Incorrect Artifact Files");
        $this->assertEquals(0, count(MockS3Client::$puts), " *** Wrong number of files");
    }
    public function testGetDefaultPath()
    {
        $this->setContainerObjects();
        $buildNumber = 11;
        $jenkinsUtils = new MockJenkinsUtils();
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, false, 4, 0, "testJob4");
        $jenkinsBuild = new MockJenkinsBuild($jenkinsJob, 1, false);
        list($artifactUrls, $artifactRelativePaths) = $jenkinsUtils->getArtifactUrls($jenkinsBuild);
        $copyOperation = new CopyToS3Operation($buildNumber);
        $method = $this->getPrivateMethod('console\components\CopyToS3Operation', 'getDefaultPath');
        list($defaultLanguage, $newArtifactUrls) = $method->invokeArgs($copyOperation, array($artifactUrls));
        $this->assertEquals("es-419", $defaultLanguage, " *** Wrong default language");
        $foundDefault = false;
        foreach ($newArtifactUrls as $key => $path) {
            if (strpos($path, "default-language.txt") !== false) {
                $foundDefault = true;
                break;
            }
        }
        $this->assertEquals(false, $foundDefault, " *** defaultLanguage.txt not removed from array");
    }
    public function testGetExtraContent()
    {
        $this->setContainerObjects();
        $buildNumber = 11;
        $jenkinsUtils = new MockJenkinsUtils();
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, false, 4, 0, "testJob4");
        $jenkinsBuild = new MockJenkinsBuild($jenkinsJob, 1, false);
        list($artifactUrls, $artifactRelativePaths) = $jenkinsUtils->getArtifactUrls($jenkinsBuild);
        $copyOperation = new CopyToS3Operation($buildNumber);
        $method = $this->getPrivateMethod('console\components\CopyToS3Operation', 'getExtraContent');
        $defaultLanguage = "engl";
        $extraContent = $method->invokeArgs($copyOperation, array($artifactRelativePaths, $defaultLanguage ));
        $manifest = $extraContent["play-listing/manifest.json"];
        $expectedString = '"default-language":"engl"';
        $foundDefaultString = strpos($manifest, $expectedString);
        $this->assertEquals(true, $foundDefaultString, " *** Didn't find default language string");
     }
    public function testGetExtraContentWithNull()
    {
        $this->setContainerObjects();
        $buildNumber = 11;
        $jenkinsUtils = new MockJenkinsUtils();
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, false, 4, 0, "testJob4");
        $jenkinsBuild = new MockJenkinsBuild($jenkinsJob, 1, false);
        list($artifactUrls, $artifactRelativePaths) = $jenkinsUtils->getArtifactUrls($jenkinsBuild);
        $copyOperation = new CopyToS3Operation($buildNumber);
        $method = $this->getPrivateMethod('console\components\CopyToS3Operation', 'getExtraContent');
        $defaultLanguage = null;
        $extraContent = $method->invokeArgs($copyOperation, array($artifactRelativePaths, $defaultLanguage ));
        $manifest = $extraContent["play-listing/manifest.json"];
        $expectedString = '"default-language":"es-419"';
        $foundDefaultString = strpos($manifest, $expectedString);
        $this->assertEquals(true, $foundDefaultString, " *** Didn't find default language string");
     }
}

