<?php
namespace tests\mock\jenkins;

use Codeception\Util\Debug;
use JenkinsApi\Item\Build as JenkinsBuild;

class MockJenkinsBuild
{
    public $jenkinsJob;
    public $buildNumer;
    public $isBuilding;
    public function __construct($jenkinsJob, $buildNumber, $isBuilding) {
        $this->jenkinsJob = $jenkinsJob;
        $this->buildNumber = $buildNumber;
        $this->isBuilding = $isBuilding;
    }
    public function getJenkins()
    {
        return $this->jenkinsJob->getJenkins();
    }
    public function getNumber()
    {
        return $this->buildNumber;        
    }
    public function getResult()
    {
        $retStatus = JenkinsBuild::SUCCESS;
        switch ($this->buildNumber) {
            case 3:
                $retStatus = JenkinsBuild::FAILURE;
                break;
            case 4:
                $retStatus = JenkinsBuild::ABORTED;
                break;
            default:
                $retStatus = JenkinsBuild::SUCCESS;
        }
        return $retStatus;
    }
    public function isBuilding()
    {
        return $this->isBuilding;
    }
    public function get($prop)
    {
        if ($prop === "artifacts") {
           $json =<<<EOT
[
{"displayPath":"about.txt","fileName":"about.txt","relativePath":"output/about.txt"},
{"displayPath":"Kuna_Gospels-1.0.apk","fileName":"Kuna_Gospels-1.0.apk","relativePath":"output/Kuna_Gospels-1.0.apk"},
{"displayPath":"package_name.txt","fileName":"package_name.txt","relativePath":"output/package_name.txt"},
{"displayPath":"default-language.txt","fileName":"package_name.txt","relativePath":"output/play-listing/default-language.txt"},
{"displayPath":"full_description.txt","fileName":"full_description.txt","relativePath":"output/play-listing/es-419/full_description.txt"},
{"displayPath":"featureGraphic.png","fileName":"featureGraphic.png","relativePath":"output/play-listing/es-419/images/featureGraphic.png"},
{"displayPath":"icon.png","fileName":"icon.png","relativePath":"output/play-listing/es-419/images/icon.png"},
{"displayPath":"screen-0.png","fileName":"screen-0.png","relativePath":"output/play-listing/es-419/images/phoneScreenshots/screen-0.png"},
{"displayPath":"screen-1.png","fileName":"screen-1.png","relativePath":"output/play-listing/es-419/images/phoneScreenshots/screen-1.png"},
{"displayPath":"screen-2.png","fileName":"screen-2.png","relativePath":"output/play-listing/es-419/images/phoneScreenshots/screen-2.png"},
{"displayPath":"short_description.txt","fileName":"short_description.txt","relativePath":"output/play-listing/es-419/short_description.txt"},
{"displayPath":"title.txt","fileName":"title.txt","relativePath":"output/play-listing/es-419/title.txt"},
{"displayPath":"whats_new.txt","fileName":"whats_new.txt","relativePath":"output/play-listing/es-419/whats_new.txt"},
{"displayPath":"version_code.txt","fileName":"version_code.txt","relativePath":"output/version_code.txt"}
]
EOT;
            return json_decode($json);
        }

        return null;
    }
    public function getBuildUrl() {
        return "http://localhost/job/" . $this->jenkinsJob->jobName . "/" . $this->buildNumber . "/";
    }
}

