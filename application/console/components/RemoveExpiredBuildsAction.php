<?php

namespace console\components;

use common\models\Build;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\JenkinsUtils;

use common\helpers\Utils;

class RemoveExpiredBuildsAction
{
    private $s3;
    public function performAction()
    {
        $logger = new Appbuilder_logger("RemoveExpiredBuildsAction");
        $jenkinsUtils = \Yii::$container->get('jenkinsUtils');
        $prefix = Utils::getPrefix();
        echo "[$prefix] RemoveExpiredBuildsAction: Started". PHP_EOL;

        foreach (Build::find()->where([
            'status' => Build::STATUS_EXPIRED])->each(50) as $build){
            if ($build->apk() != null) {
                echo "...Remove expired job $build->job_id id $build->id ". PHP_EOL;
                $s3 = new S3();
                $s3->removeS3Artifacts($build);
                $build->clearArtifacts();
                $logBuildDetails = JenkinsUtils::getlogBuildDetails($build);
                $logBuildDetails['NOTE: ']='Remove expired S3 Artifacts for an expired build.';
                $logger->appbuilderWarningLog($logBuildDetails);
            }
        }
        echo "[$prefix] actionRemoveExpiredBuilds: Conpleted". PHP_EOL;

    }
    public function getS3()
    {
        return $this->s3;
    }
}
