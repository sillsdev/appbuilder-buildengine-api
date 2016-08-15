<?php
namespace tests\unit\common\models;
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
    public function testHandleArtifact()
    {
        $this->setContainerObjects();
        $build = Build::findOne(['id' => 11]);
        $build->artifact_url_base = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/";
        $s3Key = "testing/jobs/build_scriptureappbuilder_22/1/consoleText";
        $content = "Content";
        $build->handleArtifact($s3Key, $content);
        $expected = "https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/consoleText";
        $this->assertEquals($expected, $build->consoleText(), " *** ConsoleText return incorrect");
        // Make sure it was added to the artifacts
        $expected = "consoleText";
        $this->assertEquals($expected, $build->artifact_files, " *** Artifact list doesn't match");  
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
}