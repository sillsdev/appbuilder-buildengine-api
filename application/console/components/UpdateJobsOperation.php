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
        $jenkins = JenkinsUtils::getJenkins();
        if ($jenkins){
            echo "[$prefix] UpdateJobsOperation: Telling Jenkins to regenerate Jobs" . PHP_EOL;
            $jenkins->getJob("Job-Wrapper-Seed")->launch();
        } else {
            throw new \Exception("Unable to update the jenkins job list.  Jenkins unavailable",1457101819);
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

