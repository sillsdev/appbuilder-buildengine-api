<?php
namespace console\components;

use common\models\Build;
use common\models\Release;
use common\models\OperationQueue;
use common\components\Appbuilder_logger;
use common\components\CodeBuild;

use console\components\ActionCommon;

use common\helpers\Utils;
use yii\web\NotFoundHttpException;

class ManageReleasesAction extends ActionCommon
{
    private $cronController;
    public function __construct($cronController)
    {
        $this->cronController = $cronController;
    }
    public function performAction()
    {
/*        $tokenSemaphore = sem_get(8);
        $tokenValue = shm_attach(9, 100);

        if (!$this->try_lock($tokenSemaphore, $tokenValue)){
            $prefix = Utils::getPrefix();
            echo "[$prefix] ManageReleasesAction: Semaphore Blocked" . PHP_EOL;
            return;
        }
        */
        try {
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
        catch (\Exception $e) {
            echo "Caught exception".PHP_EOL;
            echo $e->getMessage() .PHP_EOL;
            echo $e->getFile() . PHP_EOL;
            echo $e->getLine() . PHP_EOL;
            $logger = new Appbuilder_logger("ManageBuildsAction");
            $logException = [
            'problem' => 'Caught exception',
                ];
            $logger->appbuilderExceptionLog($logException, $e);
         }
        finally {
//            $this->release($tokenSemaphore, $tokenValue);
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
        $log['Release-Build'] = (string) $release->build_guid;
        $log['Release-Result'] = $release->result;

        echo "Release=$release->id, Build=$release->build_guid, Status=$release->status, Result=$release->result". PHP_EOL;

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
            $artifactUrl = $build->apk();
            $path = $build->artifact_url_base;

            $script = $this->cronController->renderPartial("scripts/appbuilders_publish", [
                ]);

            echo $script;
            
            // Start the build
            $codeBuild = new CodeBuild();
            $lastBuildGuid = $codeBuild->startRelease($release, (string) $script);
            if (!is_null($lastBuildGuid)){
                $release->build_guid = $lastBuildGuid;
                echo "[$prefix] Launched Build LastBuildNumber=$release->build_guid". PHP_EOL;
                $release->status = Release::STATUS_ACTIVE;
                $release->save();
            }
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryStartRelease: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = $this->getlogReleaseDetails($release);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
    /**
     * @param Release $release
     */
    private function checkReleaseStatus($release)
    {
        $logger = new Appbuilder_logger("ManageBuildsAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] checkReleaseStatus: Checking Build of ".$release->jobName()." for Channel ".$release->channel. PHP_EOL;

            $build = $release->build;
            echo "Build id : " . $build->id . PHP_EOL;
            $job = $build->job;
            if ($job) {       
                $codeBuild = new CodeBuild();
                $buildStatus = $codeBuild->getBuildStatus((string)$release->build_guid, 'publish_app');
                $phase = $buildStatus['currentPhase'];
                $status = $buildStatus['buildStatus'];
                echo " phase: " . $phase . " status: " . $status .PHP_EOL;
                if ($codeBuild->isBuildComplete($buildStatus)) 
                {
                    echo ' Build Complete' . PHP_EOL;
                } else {
                    echo ' Build Incomplete' . PHP_EOL;
                }
        
                if ($codeBuild->isBuildComplete($buildStatus)) {
                    $release->status = Release::STATUS_COMPLETED;
                    $status = $codeBuild->getStatus($buildStatus);
                    switch($status){
                        case CodeBuild::STATUS_FAILED:
                        case CodeBuild::STATUS_FAULT:
                        case CodeBuild::STATUS_TIMED_OUT:
                            $release->result = Build::RESULT_FAILURE;
                            break;
                        case CodeBuild::STATUS_STOPPED:
                            $release->result = Build::RESULT_ABORTED;
                            break;
                        case CodeBuild::STATUS_SUCCEEDED:
                            $release->result = Build::RESULT_SUCCESS;
                            $build->channel = $release->channel;
                            $build->save();
                            break;
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
            echo "[$prefix] checkBuildStatus: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            echo $e->getFile() . PHP_EOL;
            echo $e->getLine() . PHP_EOL;
            $logException = $this->getlogReleaseDetails($release);
            $logger->appbuilderExceptionLog($logException, $e);
            $this->failRelease($release);
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
    private function failRelease($release) {
        try {
            $release->result = Build::RESULT_FAILURE;
            $release->status = Release::STATUS_COMPLETED;
            $release->save();
        } catch (\Exception $e) {
        $logger = new Appbuilder_logger("ManageReleasesAction");
            $prefix = Utils::getPrefix();
            echo "[$prefix] failRelease Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            echo $e->getFile() . PHP_EOL;
            echo $e->getLine() . PHP_EOL;
            $logException = $this->getlogReleaseDetails($release);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
}
