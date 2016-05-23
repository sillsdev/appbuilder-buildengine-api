<?php
namespace tests\mock\jenkins;

use Codeception\Util\Debug;
use JenkinsApi\Item\Build as JenkinsBuild;

class MockJenkinsBuild
{
    public $jenkins;
    public $buildNumer;
    public $isBuilding;
    public function __construct($jenkins, $buildNumber, $isBuilding) {
        $this->jenkins = $jenkins;
        $this->buildNumber = $buildNumber;
        $this->isBuilding = $isBuilding;
    }
    public function getJenkins()
    {
        return $jenkins;
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
}

