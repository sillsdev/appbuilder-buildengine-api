<?php

namespace console\components;

use common\models\Job;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\helpers\Utils;

class SetInitialVersionCodeAction
{
    private $job_id;
    private $version_code;
    public function __construct($id, $version_code)
    {
        $this->job_id = $id;
        $this->version_code = $version_code;
    }
    public function performAction()
    {
        $logger = new Appbuilder_logger("SetInitialVersionCodeAction");
        $prefix = Utils::getPrefix();
        echo "[$prefix] SetInitialVersionCodeAction ID: " .$this->job_id . " Version: " . $this->version_code . PHP_EOL;
        $job = Job::findById($this->job_id);
        if (!is_null($job)) {
            $job->initial_version_code = $this->version_code;
            $job->save();
        } else {
            echo "  Job $this->job_id not found";
        }
    }
}
