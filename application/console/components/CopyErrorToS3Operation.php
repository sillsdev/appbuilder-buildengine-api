<?php

namespace console\components;

use console\components\OperationInterface;
use common\models\Job;
use common\models\Build;
use common\models\Release;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\JenkinsUtils;

use common\helpers\Utils;

class CopyErrorToS3Operation implements OperationInterface
{
    private $id;
    private $parms;
    private $maxRetries = 50;
    private $maxDelay = 30;
    private $alertAfter = 5;
    
    public function __construct($id, $parms)
    {
        $this->id = $id;
        $this->parms = $parms;
    }
    public function performOperation()
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] CopyErrorToS3Operation ID: " .$this->id . PHP_EOL;
        if ($this->parms == "release") {
            $release = Release::findOne(['id' => $this->id]);
            if ($release) {
                $jenkins = JenkinsUtils::getPublishJenkins();
                $s3ErrorUrl = S3::saveErrorToS3($jenkins, $release->jobName(), $release->build_number);
                $release->error = $s3ErrorUrl;
                $release->save();
            }
        } else {
            $build = Build::findOneByBuildId($this->id);
            if ($build) {
                $jenkins = JenkinsUtils::getJenkins();
                $errorUrl = $jenkins->getBaseUrl().sprintf('job/%s/%s/consoleText', $build->jobName(), $build->build_number);
                $s3ErrorUrl = S3::saveErrorToS3($jenkins, $build->jobName(), $build->build_number);
                $build->error = $s3ErrorUrl;
                $build->save();
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

}
