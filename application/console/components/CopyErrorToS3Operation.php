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
                $s3 = new S3();
                $s3->copyS3Folder($release);
                $release->status = Release::STATUS_COMPLETED;
                $release->save();
            }
        } else {
            $build = Build::findOneByBuildId($this->id);
            if ($build) {
                $s3 = new S3();
                $s3->copyS3Folder($build);
                $build->status = Build::STATUS_COMPLETED;
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
