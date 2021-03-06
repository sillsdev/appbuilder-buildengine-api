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
    public function getJenkinsBaseUrl()
    {
        return "http://192.168.70.241:8080";
    }
    public function getPublishJenkins()
    {
        $jenkins = new MockJenkins();
        return $jenkins;
    }
    public function getPublishJenkinsBaseUrl()
    {
        return "http://192.168.70.242:8080";
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
        if ($jenkinsBuild->getNumber() == 22) {
            return null;
        } else {
            foreach ($jenkinsArtifacts as $testArtifact) {
                $relativePath = explode("output/", $testArtifact->relativePath)[1];
                array_push($artifactRelativePaths, $relativePath);
                $artifactUrl = $this->getArtifactUrlFromRelativePath($jenkinsBuild, $testArtifact->relativePath);
                array_push($artifactUrls, $artifactUrl);
               }
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
