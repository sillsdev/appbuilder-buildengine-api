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
        $jenkinsUtils = \Yii::$container->get('jenkinsUtils');
        if ($this->parms == "release") {
            $release = Release::findOne(['id' => $this->id]);
            if ($release) {
                $jenkins = $jenkinsUtils->getPublishJenkins();
                $s3 = new S3();
                $s3ErrorUrl = $s3->saveErrorToS3($release->jobName(), $release->build_number, $jenkins);
                $release->error = $s3ErrorUrl;
                $release->save();
            }
        } else {
            $build = Build::findOneByBuildId($this->id);
            if ($build) {
                $jenkins = $jenkinsUtils->getJenkins();
                $s3 = new S3();
                $s3ErrorUrl = $s3->saveErrorToS3($build->jobName(), $build->build_number, $jenkins);
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
