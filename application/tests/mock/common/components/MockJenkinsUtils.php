<?php

namespace tests\mock\common\components;

use Codeception\Util\Debug;
use common\components\JenkinsUtils;
use tests\mock\jenkins\MockJenkins;

class MockJenkinsUtils extends JenkinsUtils
{
    private static $returnJenkins = true;
    public static function setReturnJenkins($value)
    {
        self::$returnJenkins = $value;
    }
    public function getJenkins()
    {
        if (self::$returnJenkins)
        {
            $jenkins = new MockJenkins();
            return $jenkins;
        } else {
            self::$returnJenkins = true;
            return null;
        }
    }
    public function getPublishJenkins()
    {
        $jenkins = new MockJenkins();
        return $jenkins;
    }
    private function getArtifactUrl($jenkinsBuild, $artifactName)
    {
        $baseUrl = $jenkinsBuild->getJenkins()->getBaseUrl(); 
        $jobName = $jenkinsBuild->jenkinsJob->jobName;
        $buildNumber = $jenkinsBuild->getNumber();
        $artifactUrl = $baseUrl."job/".$jobName."/".$buildNumber."/artifact/output/".$artifactName;
        return $artifactUrl;    
    }

    public function getArtifactUrls($jenkinsBuild) {

        $jenkinsArtifacts = $jenkinsBuild->get("artifacts");
        if (!$jenkinsArtifacts) { return null; }
        $artifactUrls = array();
        $artifactRelativePaths = array();
        foreach ($jenkinsArtifacts as $testArtifact) {
            $relativePath = explode("output/", $testArtifact->relativePath)[1];
            array_push($artifactRelativePaths, $relativePath);
            $artifactUrl = $this->getArtifactUrlFromRelativePath($jenkinsBuild, $testArtifact->relativePath);
            array_push($artifactUrls, $artifactUrl);
        }
        return array($artifactUrls, $artifactRelativePaths);
    }

    public function getApkArtifactUrl($jenkinsBuild)
    {
        $artifactUrl = $this->getArtifactUrl($jenkinsBuild, "TestPublishing-1.0.apk");
        return $artifactUrl;
    }
    public function getVersionCodeArtifactUrl($jenkinsBuild)
    {
        $artifactUrl = $this->getArtifactUrl($jenkinsBuild, "version_code.txt");
        return $artifactUrl;
    }
     public function getPackageNameArtifactUrl($jenkinsBuild)
     {
        $artifactUrl = $this->getArtifactUrl($jenkinsBuild, "package_name.txt");
        return $artifactUrl;
    }
    public function getMetaDataArtifactUrl($jenkinsBuild)
    {
        $artifactUrl = $this->getArtifactUrl($jenkinsBuild, "publish.tar.gz");
        return $artifactUrl;
    }
    public function getAboutArtifactUrl($jenkinsBuild)
    {
        $artifactUrl = $this->getArtifactUrl($jenkinsBuild, "about.txt");
        return $artifactUrl;
    }

}
