<?php
namespace tests\unit\common\components;
use common\components\S3;
use common\components\JenkinsUtils;

use tests\unit\UnitTestBase;

use tests\mock\aws\s3\MockS3Client;

use common\models\Job;
use common\models\Build;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;

class S3Test extends UnitTestBase
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
    public function testGetBasePrefix()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 11]);
        $prefix = $s3->getBasePrefixUrl($build, S3::getAppEnv());
        $expected = "testing/jobs/build_scriptureappbuilder_22/11/";
        $this->assertEquals($expected, $prefix, " ***Prefix doesn't match");
    }
    public function testReadS3File()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 11]);
        $fileText = $s3->readS3File($build, "testfilename.txt");
        $expected = "Test body";
        $this->assertEquals($expected, $fileText, " *** File content wrong");
        $this->assertEquals(1, count(MockS3Client::$gets), " *** Wrong number of gets to S3");
        $expectedKey = "codebuild-output/jobs/build_scriptureappbuilder_22/11/testfilename.txt";
        $parms = MockS3Client::$gets[0];
        $receivedKey = $parms['Key'];
        $this->assertEquals($expectedKey, $receivedKey, " *** Requested key bad");
        $receivedBucket = $parms['Bucket'];
        $expectedBucket = "sil-appbuilder-artifacts";
        $this->assertEquals($expectedBucket, $receivedBucket, " *** Requested bucket bad");
/*
        $output = new \Codeception\Lib\Console\Output([]);
        $output->writeln('');
        $output->writeln("Starting Test");

        or

        codecept_debug("Starting Test");
        and run with "docker-compose run --rm codecept --debug run unit"
        }
*/
    }
    public function testCopyS3File() {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 11]);
        $artifactsBucket = S3::getArtifactsBucket();
        $sourcePrefix = $s3->getBasePrefixUrl($build, 'codebuild-output');
        $destPrefix = $s3->getBasePrefixUrl($build, S3::getAppEnv());
        $filename = 'codebuild-output/jobs/build_scriptureappbuilder_22/11/package_name.txt';
        $file = [
            'Key' => $filename,
        ];
        $s3->copyS3File($file, $sourcePrefix, $destPrefix, $build);
        $file2 = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/manifest.txt',
        ];
        $s3->copyS3File($file2, $sourcePrefix, $destPrefix, $build);
        $file3 = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/default-language.txt',
        ];
        $s3->copyS3File($file3, $sourcePrefix, $destPrefix, $build);
        $file3 = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/version_code.txt',
        ];
        $s3->copyS3File($file3, $sourcePrefix, $destPrefix, $build);
        // Should only be two since manifest.txt file and default_language.txt are ignored
        $this->assertEquals(2, count(MockS3Client::$copies), " *** Wrong number of copies to S3");
        $this->assertEquals('package_name.txt,version_code.txt', $build->artifact_files, " *** Wrong artifact files value");
        $copy = MockS3Client::$copies[0];
        $expectedKey = 'testing/jobs/build_scriptureappbuilder_22/11/package_name.txt';
        $this->assertEquals($expectedKey, $copy['Key'], " *** Wrong key detected");
        $this->assertEquals('text/plain', $copy['ContentType'], " *** Wrong content type");
        $this->assertEquals('sil-appbuilder-artifacts', $copy['Bucket'], " *** Wrong S3 artifacts bucket");
        $this->assertEquals('sil-appbuilder-artifacts/' . $filename, $copy['CopySource'], " *** Bad source file name");
        $this->assertEquals('42', $build->version_code, " *** Bad version code");
    }
    public function testS3CopyFolder()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 11]);
        $s3->copyS3Folder($build);
        $this->assertEquals(14, count(MockS3Client::$copies), " *** Wrong number of copies to S3");
        $this->assertEquals(1, count(MockS3Client::$lists), " *** Wrong number of lists to S3");
        $copy = MockS3Client::$copies[0];
        $expectedKey = 'testing/jobs/build_scriptureappbuilder_22/11/about.txt';
        $this->assertEquals($expectedKey, $copy['Key'], " *** Wrong key detected");
     }

     public function testDeleteCodeBuildFolder()
     {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 11]);
        $s3->removeCodeBuildFolder($build);
        $this->assertEquals(1, count(MockS3Client::$deletes), " *** Wrong number of lists to S3");
        $delete = MockS3Client::$deletes[0];
        $this->assertEquals('codebuild-output/jobs/build_scriptureappbuilder_22/11/', $delete['key'], " *** Wrong key on delete");
        $this->assertEquals('sil-appbuilder-artifacts', $delete['bucket'], " *** Wrong bucket on delete");
     }
    /*
    public function testSaveBuild()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 11]);
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, false, 4, 0, "testJob4");
        $jenkinsBuild = new MockJenkinsBuild($jenkinsJob, 1, false);
        $jenkinsUtils = new JenkinsUtils();
        list($artifactUrls, $artifactRelativePaths) = $jenkinsUtils->getArtifactUrls($jenkinsBuild);
        $extraContent = [ "play-listing/index.html"  => "<html></html>", "play-listing/manifest.json" => "{}" ];
        $s3->saveBuildToS3($build, $artifactUrls, $artifactRelativePaths, $extraContent);
        $this->assertEquals(18, count(MockS3Client::$puts), " *** Wrong number of puts to S3");
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/Kuna_Gospels-1.0.apk";
        $this->assertEquals($expected, $build->apk(), " *** Public URL for APK doesn't match");
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/about.txt";
        $this->assertEquals($expected, $build->about(), " *** Public URL for About doesn't match");
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/play-listing/index.html";
        $this->assertEquals($expected, $build->playListing(), " *** Public URL for Play Listing doesn't match");
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/version_code.txt";
        $this->assertEquals($expected, $build->versionCode(), " *** Public URL for Version Code doesn't match");
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/package_name.txt";
        $this->assertEquals($expected, $build->packageName(), " *** Public URL for Package Name doesn't match");
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/consoleText";
        $this->assertEquals($expected, $build->consoleText(), " *** Public URL for Console Text doesn't match");

        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/";
        $this->assertEquals($expected, $build->artifact_url_base, " *** Artifact URL Base doesn't match");
        $expected = "about.txt,Kuna_Gospels-1.0.apk,package_name.txt,version_code.txt,play-listing/index.html,consoleText";
        $this->assertEquals($expected, $build->artifact_files, "*** Artifact files doesn't match");
        $artifactPut = MockS3Client::$puts[0];
        $expected = "sil-appbuilder-artifacts";
        $this->assertEquals($expected, $artifactPut['Bucket'], " *** Bad bucket data");
        $expected = "testing/jobs/build_scriptureappbuilder_22/1/about.txt";
        $this->assertEquals($expected, $artifactPut['Key'], " *** Bad Key data");
        $expected = "Contents of http://127.0.0.1/job/testJob4/1/artifact/output/about.txt";
        $this->assertEquals($expected, $artifactPut['Body'], " *** Wrong content");
        $expected = "text/plain";
        $this->assertEquals($expected, $artifactPut['ContentType'], " *** Wrong Mime Type");
    }
    public function testRemoveS3Artifacts()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 12]);
        $s3->removeS3Artifacts($build);
        $expected = "sil-appbuilder-artifacts";
        $this->assertEquals(1, count(MockS3Client::$deletes), " *** Wrong number of deletes to S3");
        $delete = MockS3Client::$deletes[0];
        $this->assertEquals($expected, $delete['bucket'], " *** Wrong bucket name deleted");
        $expected = "testing/jobs/build_scriptureappbuilder_22/1/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
    }
    public function testRemoveS3FoldersWithoutJobRecord()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $jobNames = Job::getJobNames();
        $s3 = new S3();
        $loginfo = $s3->removeS3FoldersWithoutJobRecord($jobNames);
        $this->assertEquals(2, count(MockS3Client::$deletes), " *** Wrong number of deletes to S3");
        $delete = MockS3Client::$deletes[0];
        $expected = "sil-appbuilder-artifacts";
        $this->assertEquals($expected, $delete['bucket'], " *** Wrong bucket name deleted");
        $expected = "testing/jobs/build_scriptureappbuilder_1/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
        $delete = MockS3Client::$deletes[1];
        $expected = "testing/jobs/build_scriptureappbuilder_2/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
        $this->assertEquals(3, count($loginfo), " *** Wrong number of log entries");
        $expected = "Deleted S3 bucket: sil-appbuilder-artifacts key: testing/jobs/build_scriptureappbuilder_2/\n";
        $this->assertEquals($expected, $loginfo[2], " *** Wrong Key name deleted");
        
*/        
/*
        $output = new \Codeception\Lib\Console\Output([]);
        $output->writeln('');
        $output->writeln("Starting Test");

        or

        codecept_debug("Starting Test");
        and run with "docker-compose run --rm codecept --debug run unit"
        }
*/
/*    }

    public function testGetS3Key()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $build = Build::findOne(['id' => 11]);

        $relativePaths = [
            "about.txt" => "testing/jobs/build_scriptureappbuilder_22/1/about.txt",
            "play-listing/en-us/title.txt" => "testing/jobs/build_scriptureappbuilder_22/1/play-listing/en-us/title.txt",
            "play-listing/en-us/images/phoneScreenshots/screen shot1.png" => "testing/jobs/build_scriptureappbuilder_22/1/play-listing/en-us/images/phoneScreenshots/screen shot1.png"
        ];

        foreach ($relativePaths as $path => $expect) {
            $result = S3::getS3Key($build, $path);
            $this->assertEquals($expect, $result, "*** Wrong S3 Key");
        }
    }
    public function testGetArtifactOutputFile()
    {
        // Function doesn't exist anymore
    }
    public function testGetS3Url()
    {
        // Function doesn't exist anymore
    }
   */ 
}