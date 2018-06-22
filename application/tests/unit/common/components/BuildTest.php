<?php
namespace tests\unit\common\components;
use tests\unit\UnitTestBase;

use common\models\Job;
use common\models\Build;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;

class BuildTest extends UnitTestBase
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
    /**
     * This method is only here because if I don't put it in, the first test
     * fails because the params isn't loaded.  Don't know why, but this fixed it
     */
    public function testDummy()
    {
        $this->assertEquals(1,1);
    }
    public function testArtifactTypeTest()
    {
        $this->setContainerObjects();
        $build = Build::findOne(['id' => 11]);
        $s3Key = "testing/jobs/build_scriptureappbuilder_22/1/consoleText";
        list ($type, $file) = $build->artifactType($s3Key);
        $this->assertEquals("consoleText", $type, " *** Console Text file type not detected");
        $s3Key = "testing/jobs/build_scriptureappbuilder_22/1/play-listing/index.html";
        list ($type, $file) = $build->artifactType($s3Key);
        $this->assertEquals("play-listing", $type, " *** play listing file type not detected");
        $s3Key = "testing/jobs/build_scriptureappbuilder_22/1/version_code.txt";
        list ($type, $file) = $build->artifactType($s3Key);
        $this->assertEquals("version_code", $type, " *** version code file type not detected");
        $s3Key = "testing/jobs/build_scriptureappbuilder_22/1/about.txt";
        list ($type, $file) = $build->artifactType($s3Key);
        $this->assertEquals("about", $type, " *** about file type not detected");
        $s3Key = "testing/jobs/build_scriptureappbuilder_22/1/testproject.apk";
        list ($type, $file) = $build->artifactType($s3Key);
        $this->assertEquals("apk", $type, " *** apk file type not detected");
    }
    public function testHandleArtifact()
    {
        $this->setContainerObjects();
        $build = Build::findOne(['id' => 11]);
        $s3Key = "testing/jobs/build_scriptureappbuilder_22/1/version_code.txt";
        $content = "5";
        $build->handleArtifact($s3Key, $content);
        $this->assertEquals(5, $build->version_code, " *** Wrong version code");
        $this->assertEquals("version_code.txt", $build->artifact_files, " *** Problem adding artifact");
    }
    public function testFindAllRunningByJobId()
    {
        $this->setContainerObjects();
        $builds = Build::findAllRunningByJobId('22');
        $numberOfRunningBuilds = count($builds);
        $this->assertEquals(2, $numberOfRunningBuilds, " *** Incorrect number of active builds");

    }
}