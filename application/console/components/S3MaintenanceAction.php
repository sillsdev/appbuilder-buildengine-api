<?php

namespace console\components;

use common\models\Job;
use common\components\S3;
use common\components\Appbuilder_logger;

class S3MaintenanceAction
{
    private $s3;
    public function performAction()
    {
        $logger = new Appbuilder_logger("S3MaintenanceAction");
        // Clean up S3 files
        $jobNames = Job::getJobNames();
        $this->s3 = new S3();
        $logStringArray = $this->s3->removeS3FoldersWithoutJobRecord($jobNames);
        $logger->appbuilderWarningLog($logStringArray);

    }
    public function getS3()
    {
        return $this->s3;
    }
}
