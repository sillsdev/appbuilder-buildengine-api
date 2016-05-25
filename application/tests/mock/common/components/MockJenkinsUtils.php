<?php

namespace tests\mock\common\components;

use Codeception\Util\Debug;
use tests\mock\jenkins\MockJenkins;

class MockJenkinsUtils
{
    public function getJenkins()
    {
        $jenkins = new MockJenkins();
        return $jenkins;
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
