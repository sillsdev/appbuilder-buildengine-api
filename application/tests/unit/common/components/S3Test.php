<?php
namespace tests\unit\common\components;
use common\components\S3;

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
        $prefix = $build->getBasePrefixUrl(S3::getAppEnv());
        $expected = "testing/jobs/build_scriptureappbuilder_22/11";
        $this->assertEquals($expected, $prefix, " ***Prefix doesn't match");
    }
    public function testGetS3Arn()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 11]);
        $arn = S3::getS3Arn($build, S3::getAppEnv(), "testname.apk");
        $expected = 'arn:aws:s3:::sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/11/testname.apk';
        $this->assertEquals($expected, $arn, " ***Arn doesn't match");

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
        $sourcePrefix = $build->getBasePrefixUrl('codebuild-output') . "/";
        $destPrefix = $build->getBasePrefixUrl(S3::getAppEnv()) . "/";
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
    public function testCopyS3BuildFolder()
    {
        $this->setContainerObjects();
        MockS3Client::clearGlobals();
        $s3 = new S3();
        $build = Build::findOne(['id' => 11]);
        $s3->copyS3Folder($build);
        $this->assertEquals(15, count(MockS3Client::$copies), " *** Wrong number of copies to S3");
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
}