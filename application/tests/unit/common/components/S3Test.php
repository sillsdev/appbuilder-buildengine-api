<?php
namespace tests\unit\common\components;
use common\components\S3;

use \yii\codeception\DbTestCase;

use tests\mock\jenkins\MockJenkins;
use tests\mock\s3client\MockS3Client;
use tests\mock\common\components\MockFileUtils;
use tests\unit\fixtures\ParamFixture;

use common\models\Job;
use common\models\Build;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use common\components\FileUtilsInterface;
use yii\di\Container;

class S3Test extends DbTestCase
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
        ];
    }

    // tests
    public function testSaveError()
    {
//        ParamFixture::setParams();
        $this->setContainerObjects();
        $client = new MockS3Client();
        $jenkins = new MockJenkins();
        $s3 = new S3($client);
        $publicUrl = $s3->saveErrorToS3("build_scriptureappbuilder_3", "1", $jenkins);
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_3/1/consoleText";
        $this->assertEquals($expected, $publicUrl, " *** Mismatching sent emails");
        $this->assertEquals(1, count($client->puts), " *** Wrong number of puts to S3");
        $expected = "Contents of http://127.0.0.1/S3/job/build_scriptureappbuilder_3/1/consoleText";
        $put = $client->puts[0];
        $this->assertEquals($expected, $put['Body'], " *** Wrong content");
    }
    public function testSaveBuild()
    {
        $this->setContainerObjects();
        $client = new MockS3Client();
        $s3 = new S3($client);
        $build = Build::findOne(['id' => 11]);
        $artifactUrl = "http://127.0.0.1:8080/job/build_scriptureappbuilder_22/11/artifact/output/Test-1.0.apk";
        $versionCodeUrl = "http://127.0.0.1:8080/job/build_scriptureappbuilder_22/11/artifact/output/version_code.txt";
        $packageNameUrl = "http://127.0.0.1:8080/job/build_scriptureappbuilder_22/11/artifact/output/package_name.txt";
        $metadataUrl = "http://127.0.0.1:8080/job/build_scriptureappbuilder_22/11/artifact/output/publish.tar.gz";
        list($apkPublicUrl, $versionCodeReturned) = $s3->saveBuildToS3($build, $artifactUrl, $versionCodeUrl, array($packageNameUrl, $metadataUrl));
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/Test-1.0.apk";
        $this->assertEquals($expected, $apkPublicUrl, " *** Public URL doesn't match");
        $this->assertEquals(4, count($client->puts), " *** Wrong number of puts to S3");
        $artifactPut = $client->puts[0];
        $expected = "sil-appbuilder-artifacts";
        $this->assertEquals($expected, $artifactPut['Bucket'], " *** Bad bucket data");
        $expected = "testing/jobs/build_scriptureappbuilder_22/1/Test-1.0.apk";
        $this->assertEquals($expected, $artifactPut['Key'], " *** Bad Key data");
        $expected = "Contents of http://127.0.0.1:8080/job/build_scriptureappbuilder_22/11/artifact/output/Test-1.0.apk";
        $this->assertEquals($expected, $artifactPut['Body'], " *** Wrong content");
    }
    public function testSaveBuildNoPackageOrMetadata()
    {
        $this->setContainerObjects();
        $client = new MockS3Client();
        $s3 = new S3($client);
        $build = Build::findOne(['id' => 11]);
        $artifactUrl = "http://127.0.0.1:8080/job/build_scriptureappbuilder_22/11/artifact/output/Test-1.0.apk";
        $versionCodeUrl = "http://127.0.0.1:8080/job/build_scriptureappbuilder_22/11/artifact/output/version_code.txt";
        $packageNameUrl = null;
        $metadataUrl = null;
        list($apkPublicUrl, $versionCodeReturned) = $s3->saveBuildToS3($build, $artifactUrl, $versionCodeUrl, array($packageNameUrl, $metadataUrl));
        $this->assertEquals(2, count($client->puts), " *** Wrong number of puts to S3");
    }
    public function testRemoveS3Artifacts()
    {
        $this->setContainerObjects();
        $client = new MockS3Client();
        $s3 = new S3($client);
        $build = Build::findOne(['id' => 12]);
        $s3->removeS3Artifacts($build);
        $expected = "sil-appbuilder-artifacts";
        $this->assertEquals(1, count($client->deletes), " *** Wrong number of deletes to S3");
        $delete = $client->deletes[0];
        $this->assertEquals($expected, $delete['bucket'], " *** Wrong bucket name deleted");
        $expected = "testing/jobs/build_scriptureappbuilder_22/1/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
    }
    public function testRemoveS3FoldersWithoutJobRecord()
    {
        $this->setContainerObjects();
        $client = new MockS3Client();
        $jobNames = Job::getJobNames();
        $s3 = new S3($client);
        $loginfo = $s3->removeS3FoldersWithoutJobRecord($jobNames);
        $this->assertEquals(2, count($client->deletes), " *** Wrong number of deletes to S3");
        $delete = $client->deletes[0];
        $expected = "sil-appbuilder-artifacts";
        $this->assertEquals($expected, $delete['bucket'], " *** Wrong bucket name deleted");
        $expected = "testing/jobs/build_scriptureappbuilder_1/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
        $delete = $client->deletes[1];
        $expected = "testing/jobs/build_scriptureappbuilder_2/";
        $this->assertEquals($expected, $delete['key'], " *** Wrong Key name deleted");
        $this->assertEquals(3, count($loginfo), " *** Wrong number of log entries");
        $expected = "Deleted S3 bucket: sil-appbuilder-artifacts key: testing/jobs/build_scriptureappbuilder_2/\n";
        $this->assertEquals($expected, $loginfo[2], " *** Wrong Key name deleted");
        
        
/*
        $output = new \Codeception\Lib\Console\Output([]);
        $output->writeln('');
        $output->writeln("Starting Test");

        $bucket = "bucket";
        $prefix = "prefix";
        $results = $client->getPaginator('ListObjects', [
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);
        foreach ($results as $result) {
            foreach ($result['Contents'] as $object) {
                $key = $object['Key'];
                $output->writeln("Key: $key");
            }
        }
*/
    }
    private function setContainerObjects() {
        \Yii::$container->set('fileUtils', 'tests\mock\common\components\MockFileUtils');
    }
}