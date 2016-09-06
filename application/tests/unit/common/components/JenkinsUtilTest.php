<?php

namespace tests\unit\common\components;

use common\components\JenkinsUtils;
use tests\mock\jenkins\MockJenkins;
use tests\mock\jenkins\MockJenkinsJob;
use tests\mock\jenkins\MockJenkinsBuild;
use tests\unit\UnitTestBase;

class JenkinsUtilsTest extends UnitTestBase
{
    private $startConfig;

    public function fixtures()
    {
        return [
        ];
    }

    protected function _before()
    {
    }

    public function testGetArtifactUrls()
    {
        $this->setContainerObjects();
        $jenkins = new MockJenkins();
        $jenkinsJob = new MockJenkinsJob($jenkins, false, 4, 0, "testJob4");
        $jenkinsBuild = new MockJenkinsBuild($jenkinsJob, 1, false);

        $jenkinsUtil = new JenkinsUtils();
        list($artifactUrls, $artifactRelativePaths) = $jenkinsUtil->getArtifactUrls($jenkinsBuild);
        $this->assertEquals(15, count($artifactUrls), "*** Wrong number of artifacts");
        $files = array ("about.txt", "Kuna_Gospels-1.0.apk", "package_name.txt",
            "play-listing/default-language.txt",
            "play-listing/es-419/full_description.txt",
            "play-listing/es-419/images/featureGraphic.png",
            "play-listing/es-419/images/icon.png",
            "play-listing/es-419/images/phoneScreenshots/screen-0.png",
            "play-listing/es-419/images/phoneScreenshots/screen-1.png",
            "play-listing/es-419/images/phoneScreenshots/screen-2.png",
            "play-listing/es-419/images/phoneScreenshots/screen with bad @+% chars.png",
            "play-listing/es-419/short_description.txt",
            "play-listing/es-419/title.txt",
            "play-listing/es-419/whats_new.txt",
            "version_code.txt");
        $expectedFiles = array ("about.txt", "Kuna_Gospels-1.0.apk", "package_name.txt",
            "play-listing/default-language.txt",
            "play-listing/es-419/full_description.txt",
            "play-listing/es-419/images/featureGraphic.png",
            "play-listing/es-419/images/icon.png",
            "play-listing/es-419/images/phoneScreenshots/screen-0.png",
            "play-listing/es-419/images/phoneScreenshots/screen-1.png",
            "play-listing/es-419/images/phoneScreenshots/screen-2.png",
            "play-listing/es-419/images/phoneScreenshots/screen%20with%20bad%20%40%2B%25%20chars.png",
            "play-listing/es-419/short_description.txt",
            "play-listing/es-419/title.txt",
            "play-listing/es-419/whats_new.txt",
            "version_code.txt");
        $expect = array_map(function($f) { return "http://127.0.0.1/job/testJob4/1/artifact/output/" . $f; }, $expectedFiles);
        $this->assertEquals($expect, $artifactUrls, "*** Artifacts array doesn't match");
        $this->assertEquals($files, $artifactRelativePaths, "*** RelativePaths array doesn't match");
    }
}