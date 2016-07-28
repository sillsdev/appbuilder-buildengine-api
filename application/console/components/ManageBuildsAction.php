<?php
namespace console\components;

use common\models\Build;
use common\models\OperationQueue;
use common\components\Appbuilder_logger;
use common\components\JenkinsUtils;

use common\helpers\Utils;

use console\components\ActionCommon;

use JenkinsApi\Item\Build as JenkinsBuild;

class ManageBuildsAction extends ActionCommon
{
    private $jenkinsUtils;
    public function __construct()
    {
        $this->jenkinsUtils = \Yii::$container->get('jenkinsUtils');
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
                $job = $build->job;
                if ($build->status != Build::STATUS_EXPIRED) {
                    $logBuildDetails = JenkinsUtils::getlogBuildDetails($build);
                    $logger->appbuilderWarningLog($logBuildDetails);
                }
                switch ($build->status){
                    case Build::STATUS_INITIALIZED:
                        $this->tryStartBuild($job, $build);
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
    private function tryStartBuild($job, $build)
    {
        $logger = new Appbuilder_logger("ManageBuildsAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryStartBuild: Starting Build of ".$build->jobName(). PHP_EOL;

            $jenkins = $this->jenkinsUtils->getJenkins();
            $jenkinsJob = $this->getJenkinsJob($jenkins, $build);
            if (!is_null($jenkinsJob)) {
                $versionCode = $this->getNextVersionCode($job, $build);
                $parameters = array("VERSION_CODE" => $versionCode);
                $jenkinsBuild = $this->startBuildIfNotBuilding($jenkinsJob, $parameters);
                if (!is_null($jenkinsBuild)){
                    $build->build_number = $jenkinsBuild->getNumber();
                    echo "[$prefix] Started Build $build->build_number". PHP_EOL;
                    $build->status = Build::STATUS_ACTIVE;
                    $build->save();
                }
            }
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryStartBuild: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = JenkinsUtils::getlogBuildDetails($build);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
    private function getJenkinsJob($jenkins, $build) {
        try {
            $jenkinsJob = $jenkins->getJob($build->jobName());
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
     * @param Build $build
     */
    private function checkBuildStatus($build){
        $logger = new Appbuilder_logger("ManageBuildsAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] checkBuildStatus: Check Build of ".$build->jobName(). PHP_EOL;

            $job = $build->job;
            if ($job){
                $jenkins = $this->jenkinsUtils->getJenkins();
                $jenkinsJob = $jenkins->getJob($job->nameForBuild());
                $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
                if ($jenkinsBuild){
                    $build->result = $jenkinsBuild->getResult();
                    if (!$jenkinsBuild->isBuilding()){
                        $build->status = Build::STATUS_COMPLETED;
                        switch($build->result){
                            case JenkinsBuild::FAILURE:
                            case JenkinsBuild::ABORTED:
                                $task = OperationQueue::SAVEERRORTOS3;
                                $build_id = $build->id;
                                OperationQueue::findOrCreate($task, $build_id, "build");
                                break;
                            case JenkinsBuild::SUCCESS:
                                $build->status = Build::STATUS_POSTPROCESSING;
                                $task = OperationQueue::SAVETOS3;
                                $build_id = $build->id;
                                OperationQueue::findOrCreate($task, $build_id, null);
                                break;
                        }
//                        $task = OperationQueue::FINDEXPIREDBUILDS;
//                        $job_id = $job->id;
//                        OperationQueue::findOrCreate($task, $job_id, null);
                    }
                    if (!$build->save()){
                        throw new \Exception("Unable to update Build entry, model errors: ".print_r($build->getFirstErrors(),true), 1450216434);
                    }
                    $log = JenkinsUtils::getlogBuildDetails($build);
                    $log['job id'] = $job->id;
                    $logger->appbuilderWarningLog($log);
                    echo "Job=$job->id, Build=$build->build_number, Status=$build->status, Result=$build->result". PHP_EOL;
                }
            }
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] checkBuildStatus: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = JenkinsUtils::getlogBuildDetails($build);
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
            $build->result = JenkinsBuild::FAILURE;
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
