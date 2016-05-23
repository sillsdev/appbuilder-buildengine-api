<?php
namespace tests\mock\jenkins;

use Codeception\Util\Debug;

class MockJenkinsJob
{
    public $jenkins;
    public $currentlyBuilding;
    public $changeBuildsCount;
    public $buildCheckCount = 0;
    public $lastBuild;
    public $currentBuildNumber;
    public $countLastBuildsCalled = 0;
    public $refreshCount = 0;
    public $jobName;
    public function __construct($jenkins, $currentlyBuilding, $changeBuildsCount, $initialBuildNumber, $jobName) {
        $this->jenkins = $jenkins;
        $this->$jobName = $jobName;
        $this->changeBuildsCount = $changeBuildsCount;
        $this->currentlyBuilding = $currentlyBuilding;
        $this->currentBuildNumber = $initialBuildNumber; 
        if ($initialBuildNumber > 0) {
            $this->lastBuild = new MockJenkinsBuild($this->getJenkins(), $initialBuildNumber, $currentlyBuilding);
        }
    }
    public function getJenkins()
    {
        return $this->jenkins;
    }
    public function getLastBuild()
    {
        $this->countLastBuildsCalled = $this->countLastBuildsCalled + 1;
        if ($this->buildCheckCount < $this->changeBuildsCount)
        {
            $this->buildCheckCount = $this->buildCheckCount + 1;
        } else {
            $this->buildCheckCount = 0;
            $this->currentBuildNumber = $this->currentBuildNumber + 1;
            $this->lastBuild = new MockJenkinsBuild($this->getJenkins(), $this->currentBuildNumber, true);
        }
        
        return $this->lastBuild;
    }
    public function launch($params)
    {
        
    }
    public function refresh()
    {
        $this->refreshCount = $this->refreshCount + 1;
    }
    public function isCurrentlyBuilding()
    {
        return $this->currentlyBuilding;
    }
    public function getBuild($buildNumber)
    {
        return new MockJenkinsBuild($this->getJenkins(), $buildNumber, $this->currentlyBuilding);
    }
}

