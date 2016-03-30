<?php

namespace console\components;

use console\components\OperationInterface;

use common\components\JenkinsUtils;

use common\helpers\Utils;

class UpdateJobsOperation implements OperationInterface
{
    private $maxRetries = 50;
    private $maxDelay = 30;
    private $alertAfter = 5;
    

    public function performOperation()
    {
        $prefix = Utils::getPrefix();
        $buildJenkins = JenkinsUtils::getJenkins();
        if ($buildJenkins){
            echo "[$prefix] UpdateJobsOperation: Telling Jenkins to regenerate Build Jobs" . PHP_EOL;
            $buildJenkins->getJob("Build-Wrapper-Seed")->launch();
        } else {
            throw new \Exception("Unable to update the jenkins build job list.  Jenkins unavailable",1457101819);
        }
        $publishJenkins = JenkinsUtils::getPublishJenkins();
        if ($publishJenkins){
            echo "[$prefix] UpdateJobsOperation: Telling Jenkins to regenerate Publish Jobs" . PHP_EOL;
            $publishJenkins->getJob("Publish-Wrapper-Seed")->launch();
        } else {
            throw new \Exception("Unable to update the jenkins publish job list.  Jenkins unavailable",1457101819);
        }
    } 
    public function getMaximumRetries()
    {
        return $this->maxRetries;
    }
    public function getMaximumDelay()
    {
        return $this->maxDelay;
    }
    public function getAlertAfterAttemptCount()
    {
        return $this->alertAfter;
    }
}

