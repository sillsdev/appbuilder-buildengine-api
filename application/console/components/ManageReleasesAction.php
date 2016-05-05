<?php
namespace console\components;

use common\models\Build;
use common\models\Release;
use common\models\OperationQueue;
use common\components\Appbuilder_logger;
use common\components\JenkinsUtils;

use console\components\ActionCommon;

use common\helpers\Utils;
use yii\web\NotFoundHttpException;

use JenkinsApi\Item\Build as JenkinsBuild;
use JenkinsApi\Item\Job as JenkinsJob;

class ManageReleasesAction extends ActionCommon
{
    public function performAction()
    {
        $logger = new Appbuilder_logger("ManageReleasesAction");
        $complete = Release::STATUS_COMPLETED;
        foreach (Release::find()->where("status!='$complete'")->each(50) as $release){
            $build = $release->build;
            $job = $build->job;
            echo "cron/manage-releases: Job=$job->id, ". PHP_EOL;
            $logReleaseDetails = $this->getlogReleaseDetails($release);
            $logger->appbuilderWarningLog($logReleaseDetails);
            switch ($release->status){
                case Release::STATUS_INITIALIZED:
                    $this->tryStartRelease($release);
                    break;
                case Release::STATUS_ACTIVE:
                    $this->checkReleaseStatus($release);
                    break;
            }
        }
    }
    /*===============================================  logging ============================================*/
    /**
     *
     * get release details for logging.
     * @param Release $release
     * @return Array
     */
    public function getlogReleaseDetails($release)
    {
        $build = $release->build;
        $job = $build->job;

        $jobName = $build->job->name();
        $log = [
            'jobName' => $jobName,
            'jobId' => $job->id
        ];
        $log['Release-id'] = $release->id;
        $log['Release-Status'] = $release->status;
        $log['Release-Build'] = $release->build_number;
        $log['Release-Result'] = $release->result;

        echo "Release=$release->id, Build=$release->build_number, Status=$release->status, Result=$release->result". PHP_EOL;

        return $log;
    }
    /**
     *
     * @param Release $release
     */
    private function tryStartRelease($release)
    {
        $logger = new Appbuilder_logger("ManageReleasesAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryStartRelease: Starting Build of ".$release->jobName()." for Channel ".$release->channel. PHP_EOL;

            $build = $release->build;
            $artifactUrl = $build->artifact_url;
            $path = substr($artifactUrl, 0, strrpos( $artifactUrl, '/'));

            $jenkins = JenkinsUtils::getPublishJenkins();
            $jenkinsJob = $this->getJenkinsJob($jenkins, $release);
            if (!is_null($jenkinsJob)) {
                $parameters = array("CHANNEL" => $release->channel, "APK_URL" => $artifactUrl, "ARTIFACT_URL" => $path);

                if ($jenkinsBuild = $this->startBuildIfNotBuilding($jenkinsJob, $parameters)){
                    $release->build_number = $jenkinsBuild->getNumber();
                    echo "[$prefix] Started Build $release->build_number". PHP_EOL;
                    $release->status = Release::STATUS_ACTIVE;
                    $release->save();
                }
            }
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryStartRelease: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = $this->getlogReleaseDetails($release);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
    private function getJenkinsJob($jenkins, $release) {
        try {
            $jenkinsJob = $jenkins->getJob($release->jobName());
            return $jenkinsJob;
        } catch (\Exception $e) {
            // If Jenkins is up and you can't get the job, then resync the scripts
            echo "Job not found, trigger wrapper seed job".PHP_EOL;
            $task = OperationQueue::UPDATEJOBS;
            OperationQueue::findOrCreate($task, null, null);
            return null;
        }
    }

    /**
     *
     * @param Release $release
     */
    private function checkReleaseStatus($release)
    {
        $logger = new Appbuilder_logger("ManageReleasesAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] Check Build of ".$release->jobName()." for Channel ".$release->channel.PHP_EOL;

            $jenkins = JenkinsUtils::getPublishJenkins();
            $jenkinsJob = $jenkins->getJob($release->jobName());
            $jenkinsBuild = $jenkinsJob->getBuild($release->build_number);
            if ($jenkinsBuild){
                $release->result = $jenkinsBuild->getResult();
                if (!$jenkinsBuild->isBuilding()){
                    $release->status = Release::STATUS_COMPLETED;
                    switch($release->result){
                        case JenkinsBuild::FAILURE:
                        case JenkinsBuild::ABORTED:
                            $task = OperationQueue::SAVEERRORTOS3;
                            $release_id = $release->id;
                            OperationQueue::findOrCreate($task, $release_id, "release");
                            break;
                        case JenkinsBuild::SUCCESS:
                            if ($build = $this->getBuild($release->build_id))
                            {
                                $build->channel = $release->channel;
                                $build->save();
                            }
                            break;
                    }
                }
                if (!$release->save()){
                    throw new \Exception("Unable to update Build entry, model errors: ".print_r($release->getFirstErrors(),true), 1452611606);
                }
                $log = $this->getlogReleaseDetails($release);
                $logger->appbuilderWarningLog($log);
            }
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] checkReleaseStatus Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            $logException = $this->getlogReleaseDetails($release);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
    private function getBuild($id)
    {
        $build = Build::findOne(['id' => $id]);
        if (!$build){
            echo "Build not found ". PHP_EOL;
            throw new NotFoundHttpException();
        }
        return $build;
    }
}
