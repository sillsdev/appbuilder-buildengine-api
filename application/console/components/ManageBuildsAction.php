<?php
namespace console\components;

use common\models\Build;
use common\models\OperationQueue;
use common\components\Appbuilder_logger;

use common\components\CodeCommit;
use common\components\CodeBuild;

use common\helpers\Utils;

use console\components\ActionCommon;

class ManageBuildsAction extends ActionCommon
{
    private $cronController;
    public function __construct($cronController)
    {
        $this->cronController = $cronController;
    }
    public function performAction()
    {
        $prefix = Utils::getPrefix();
        $tokenSemaphore = sem_get(11);
        $tokenValue = shm_attach(12, 100);

        if (!$this->try_lock($tokenSemaphore, $tokenValue)){
            echo "[$prefix] ManageBuildsAction: Semaphore Blocked" . PHP_EOL;
            return;
        }
        echo "[$prefix] ManageBuilds Action start" . PHP_EOL;
        try {
            $logger = new Appbuilder_logger("ManageBuildsAction");
            $complete = Build::STATUS_COMPLETED;
            foreach (Build::find()->where("status!='$complete'")->each(50) as $build){
                if ($build->status != Build::STATUS_EXPIRED) {
                    $logBuildDetails = Build::getlogBuildDetails($build);
                    $logger->appbuilderWarningLog($logBuildDetails);
                }
                // codecept_debug("Build: " . (string)$build->id);
                switch ($build->status){
                    case Build::STATUS_INITIALIZED:
                        $this->tryStartBuild($build);
                        break;
                    case Build::STATUS_ACTIVE:
                        $this->checkBuildStatus($build);
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
            $this->release($tokenSemaphore, $tokenValue);
        }
    }
    
    /**
     * Try to start a build.  If it starts, then update the database.
     * @param Build $build
     */
    private function tryStartBuild($build)
    {
        $logger = new Appbuilder_logger("ManageBuildsAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryStartBuild: Starting Build of ".$build->jobName(). PHP_EOL;

            // Find the repo and commit id to be built
            $job = $build->job;

            // Don't start job if a job for this build is currently running
            $builds = Build::findAllRunningByJobId((string)$build->job_id);
            // codecept_debug("Count of active builds: " . (string)count($builds));
            if (count($builds) > 0) {
                // codecept_debug("...is currentlyBuilding so wait");
                echo '...is currentlyBuilding so wait'.  PHP_EOL;
                return;
            }
            $gitUrl = $job->git_url;
            // Check to see if codebuild project
            $codeCommitProject = (substr( $gitUrl, 0, 6) === "ssh://");
            if ($codeCommitProject) {
                // Left this block intact to make it easier to remove when codecommit is not supported
                $codecommit = new CodeCommit();
                $branch = "master";
                $repoUrl = $codecommit->getSourceURL($gitUrl);
                $commitId = $codecommit->getCommitId($gitUrl, $branch);

                $script = $this->cronController->renderPartial("scripts/appbuilders_build", [
                ]);
                // Start the build
                $codeBuild = new CodeBuild();
                $versionCode = $this->getNextVersionCode($job, $build);
                $lastBuildGuid = $codeBuild->startBuild($repoUrl, (string)$commitId, $build, (string) $script, (string)$versionCode, $codeCommitProject);
                if (!is_null($lastBuildGuid)){
                    $build->build_guid = $lastBuildGuid;
                    $build->codebuild_url = CodeBuild::getCodeBuildUrl('build_app', $lastBuildGuid);
                    $build->console_text_url = CodeBuild::getConsoleTextUrl('build_app', $lastBuildGuid);
                    echo "[$prefix] Launched Build LastBuildNumber=$build->build_guid". PHP_EOL;
                    $build->status = Build::STATUS_ACTIVE;
                    $build->save();
                }
           }
           else {
                $script = $this->cronController->renderPartial("scripts/appbuilders_s3_build", [
                    ]);
                // Start the build
                $codeBuild = new CodeBuild();
                $commitId = ""; // TODO: Remove when git is removed
                $versionCode = $this->getNextVersionCode($job, $build);
                $lastBuildGuid = $codeBuild->startBuild($gitUrl, (string)$commitId, $build, (string) $script, (string)$versionCode, $codeCommitProject);
                if (!is_null($lastBuildGuid)){
                    $build->build_guid = $lastBuildGuid;
                    $build->codebuild_url = CodeBuild::getCodeBuildUrl('build_app', $lastBuildGuid);
                    $build->console_text_url = CodeBuild::getConsoleTextUrl('build_app', $lastBuildGuid);
                    echo "[$prefix] Launched Build LastBuildNumber=$build->build_guid". PHP_EOL;
                    $build->status = Build::STATUS_ACTIVE;
                    $build->save();
                }
           }
            
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryStartBuild: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = Build::getlogBuildDetails($build);
            $logger->appbuilderExceptionLog($logException, $e);
            $this->failBuild($build);
        }
    }

    /**
     *
     * @param Build $build
     */
    private function checkBuildStatus($build){
        $logger = new Appbuilder_logger("ManageBuildsAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] checkBuildStatus: Check Build of ".$build->jobName(). PHP_EOL;

            $job = $build->job;
            if ($job) {       
                $codeBuild = new CodeBuild();
                $buildStatus = $codeBuild->getBuildStatus((string)$build->build_guid, CodeBuild::getCodeBuildProjectName('build_app'));
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
                    $build->status = Build::STATUS_COMPLETED;
                    $status = $codeBuild->getStatus($buildStatus);
                    switch($status){
                        case CodeBuild::STATUS_FAILED:
                        case CodeBuild::STATUS_FAULT:
                        case CodeBuild::STATUS_TIMED_OUT:
                            $build->result = Build::RESULT_FAILURE;
                            $build->error = $build->cloudWatch();
                            break;
                        case CodeBuild::STATUS_STOPPED:
                            $build->result = Build::RESULT_ABORTED;
                            $build->error = $build->cloudWatch();
                            break;
                        case CodeBuild::STATUS_SUCCEEDED:
                            $build->status = Build::STATUS_POSTPROCESSING;
                            $task = OperationQueue::SAVETOS3;
                            $build_id = $build->id;
                            OperationQueue::findOrCreate($task, $build_id, null);
                            break;
                    break;
                    }
                }
                if (!$build->save()){
                    throw new \Exception("Unable to update Build entry, model errors: ".print_r($build->getFirstErrors(),true), 1450216434);
                }
                $log = Build::getlogBuildDetails($build);
                $log['job id'] = $job->id;
                $logger->appbuilderWarningLog($log);
                echo "Job=$job->id, Build=$build->build_guid, Status=$build->status, Result=$build->result". PHP_EOL;
            }
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] checkBuildStatus: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = Build::getlogBuildDetails($build);
            $logger->appbuilderErrorLog($logException);
            $this->failBuild($build);
        }
    }

    private function getNextVersionCode($job, $build) {
        $id = $job->id;
        $retval = $job->existing_version_code;
        foreach (Build::find()->where([
            'job_id' => $id,
            'status' => Build::STATUS_COMPLETED,
            'result' => "SUCCESS"])->each(50) as $build){
                if (($build->version_code) && ($build->version_code > $retval)) {
                    $retval = $build->version_code;
                }
        }
        $retval = $retval + 1;
        return $retval;
    }
    private function failBuild($build) {
        $logger = new Appbuilder_logger("ManageBuildsAction");
        try {
            $build->result = Build::RESULT_FAILURE;
            $build->status = Build::STATUS_COMPLETED;
            $build->save();
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] failBuild Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            echo $e->getFile() . PHP_EOL;
            echo $e->getLine() . PHP_EOL;
            $logException = $this->getlogBuildDetails($build);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
}
