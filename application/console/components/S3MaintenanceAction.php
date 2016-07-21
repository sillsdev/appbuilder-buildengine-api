<?php

namespace console\components;

use common\models\Job;
use common\components\S3;
use common\components\Appbuilder_logger;

use common\helpers\Utils;

class S3MaintenanceAction
{
    private $s3;
    public function performAction()
    {
        $logger = new Appbuilder_logger("S3MaintenanceAction");
        $prefix = Utils::getPrefix();
        echo "[$prefix] S3MaintenanceAction: Started". PHP_EOL;
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
