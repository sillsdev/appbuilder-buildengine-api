<?php

namespace console\components;

use console\components\OperationInterface;

use common\models\Job;
use common\models\Build;
use common\components\JenkinsUtils;

use common\helpers\Utils;

class FindExpiredBuildsOperation implements OperationInterface
{
    private $job_id;
    private $maxRetries = 50;
    private $maxDelay = 30;
    private $alertAfter = 5;
    public function __construct($id)
    {
        $this->job_id = $id;
    }
    public function performOperation()
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] Check for expired builds for job $this->job_id" . PHP_EOL;
        $job = Job::findOne(['id' => $this->job_id]);
        if (!is_null($job)){
           $jenkins = JenkinsUtils::getJenkins();
            $jenkinsJob = $jenkins->getJob($job->name());
            $builds = $jenkinsJob->getBuilds();
            foreach (Build::find()->where([
                'job_id' => $this->job_id])->each(50) as $build){
                $matchFound = $this->checkForMatch($builds, $build->build_number);
                if ($matchFound == false){
                    $build->status = Build::STATUS_EXPIRED;
                    $build->save();
                }
            }
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
    private function checkForMatch($jenkinsBuilds, $build_number) {
        $matchFound = false;
        foreach ($jenkinsBuilds as $jenkinsBuild) {
            if ($build_number == $jenkinsBuild->getNumber()) {
                $matchFound = true;
                break;
            }
        }
        return $matchFound;
    }

}


