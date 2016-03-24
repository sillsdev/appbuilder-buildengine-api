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
    public function performAction()
    {
        $logger = new Appbuilder_logger("ManageBuildsAction");
        $complete = Build::STATUS_COMPLETED;
        foreach (Build::find()->where("status!='$complete'")->each(50) as $build){
            $job = $build->job;
            echo "cron/manage-builds: ". PHP_EOL;
            $logBuildDetails = JenkinsUtils::getlogBuildDetails($build);
            $logger->appbuilderWarningLog($logBuildDetails);
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

            $jenkins = JenkinsUtils::getJenkins();
            $jenkinsJob = $jenkins->getJob($build->jobName());
            $versionCode = $this->getNextVersionCode($job, $build);
            $parameters = array("VERSION_CODE" => $versionCode);
            $jenkinsBuild = $this->startBuildIfNotBuilding($jenkinsJob, $parameters);
            if (!is_null($jenkinsBuild)){
                $build->build_number = $jenkinsBuild->getNumber();
                echo "[$prefix] Started Build $build->build_number". PHP_EOL;
                $build->status = Build::STATUS_ACTIVE;
                $build->save();
            }
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryStartBuild: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = JenkinsUtils::getlogBuildDetails($build);
            $logger->appbuilderExceptionLog($logException, $e);
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
                $jenkins = JenkinsUtils::getJenkins();
                $jenkinsJob = $jenkins->getJob($job->name());
                $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
                if ($jenkinsBuild){
                    $build->result = $jenkinsBuild->getResult();
                    if (!$jenkinsBuild->isBuilding()){
                        $build->status = Build::STATUS_COMPLETED;
                        switch($build->result){
                            case JenkinsBuild::FAILURE:
                                $build->error = $jenkins->getBaseUrl().sprintf('job/%s/%s/consoleText', $build->jobName(), $build->build_number);
                                break;
                            case JenkinsBuild::SUCCESS:
                                $task = OperationQueue::SAVETOS3;
                                $build_id = $build->id;
                                OperationQueue::findOrCreate($task, $build_id, null);
                                break;
                        }
                        $task = OperationQueue::FINDEXPIREDBUILDS;
                        $job_id = $job->id;
                        OperationQueue::findOrCreate($task, $job_id, null);
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
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }

    private function getNextVersionCode($job, $build) {
        $id = $job->id;
        $retval = 0;
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
}
