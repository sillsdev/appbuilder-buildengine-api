<?php

namespace console\components;

use common\models\Job;
use common\components\S3;
use common\components\Appbuilder_logger;

class S3MaintenanceAction
{
    public function performAction()
    {
        $logger = new Appbuilder_logger("S3MaintenanceAction");
        // Clean up S3 files
        $jobNames = Job::getJobNames();
        $logStringArray = S3::removeS3FoldersWithoutJobRecord($jobNames);
        $logger->appbuilderWarningLog($logStringArray);

    }
}
