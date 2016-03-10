<?php

namespace common\models;
use common\models\Job;
use common\models\Build;
use common\models\EmailQueue;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\JenkinsUtils;
use common\components\EmailUtils;

use common\helpers\Utils;
use yii\helpers\ArrayHelper;

use JenkinsApi\Item\Build as JenkinsBuild;


/**
 * Class OperationQueue
 * @package common\models
 */
class OperationQueue extends OperationQueueBase
{
    const UPDATEJOBS = 'UPDATEJOBS';
    const SAVETOS3 = 'SAVETOS3';
    const FINDEXPIREDBUILDS = 'FINDEXPIREDBUILDS';

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(),[
            [
                ['created','updated'],'default', 'value' => Utils::getDatetime(),
            ],
            [
                'updated', 'default', 'value' => Utils::getDatetime(), 'isEmpty' => function(){
                    // always return true so it get set on every save
                    return true;
                },
            ],
        ]);
    }
    public static function findOrCreate($operation, $id, $parms) {
        echo "OperationQueue::findOrCreate start". PHP_EOL;
        $job = self::findOne([
            'operation' => $operation,
            'operation_object_id' => $id
             ]);
        if(!$job){
            $job = self::createOperation($operation, $id, $parms);
        } else {
            // If another identical entry is requested, reset the timer
            // and retry counts
            $job->attempt_count = 0;
            $job->try_after = Utils::getDatetime();
            if (!$job->save()) {
                 echo "Failed to save operation for task $operation" . PHP_EOL;         
            }
        }
        echo "OperationQueue::findOrCreate exit". PHP_EOL;
        return $job;
    }
    public static function createOperation($operation, $objectID, $parms)
    {
        echo "OperationQueue::create start". PHP_EOL;
        $job = new OperationQueue();
        $job->operation = $operation;
        $job->operation_object_id = $objectID;
        $job->operation_parms = $parms;
        $job->attempt_count = 0;
        $job->try_after = Utils::getDatetime();
        if (!$job->save()) {
             echo "Failed to create operation for task $operation" . PHP_EOL;         
        }

        echo "OperationQueue::create exit". PHP_EOL;
        return $job;
    }
    /**
     * Find the next job in queue and process it if not over max attempts
     * @param string|null $currentTime The datetime after which to try jobs
     * @param int $maxAttempts
     * @param int $alertAfterAttemptCount
     * @return bool|null
     * @throws \Exception
     */
    public static function processNext($currentTime=null,$maxAttempts=50,$alertAfterAttemptCount=3)
    {
        /**
         * Get the next job in queue
         */
        $currentTime = $currentTime ?: Utils::getDatetime();
        /* @var $job OperationQueue */
        $job = OperationQueue::find()
            ->where(['start_time' => null])
            ->andWhere('`try_after` <= :now',[':now' => $currentTime])
            ->orderBy('try_after',SORT_ASC)
            ->one();
 
        /**
         * If there are no jobs ready in queue, return null
         */
        if(!$job){
            return false;
        }
        /**
         * Make sure we're not over the limit of max_attempts before proceeding
         */
        if($job->attempt_count > $maxAttempts){
            $message = "Operation Queue entry beyond max attempts. ID: ".$job->id.", "
                      ."for task: ".$job->operation;
            throw new \Exception($message,1457104373);
        }
        /**
         * Process operation
         * Catch exceptions to increment attempt_count and such
         */
        try{
      
            switch($job->operation){
                case OperationQueue::UPDATEJOBS:
                    $job->updateJenkinsJobs();
                    break;
                case OperationQueue::SAVETOS3:
                    $build_id = $job->operation_object_id;
                    $job->saveBuildToS3($build_id);
                    break;
                case OperationQueue::FINDEXPIREDBUILDS:
                    $job_id = $job->operation_object_id;
                    $job->checkForExpiredBuilds($job_id);
                    break;
            }
        } catch (\Exception $e) {
            /**
             * Log exception
             */
            \Yii::error('cron=operation-queue exceptionId='.$e->getCode().' message='.$e->getMessage(),'cron');

            /**
             * Update job
             */
            try{
                $job->attempt_count++;
                $job->last_attempt = Utils::getDatetime();
                $job->start_time = null;
                $job->last_error = $e->getMessage();
                $job->try_after = Utils::getDatetime(self::getNextTryTime($job->attempt_count, false, $maxAttempts));
                if(!$job->save()){
                    throw new \Exception("Unable to update OperationQueue entry, model errors: ".print_r($job->getFirstErrors(),true),1457104658);
                }
            } catch (\Exception $e) {
                \Yii::error('cron=operation-queue exceptionId='.$e->getCode().' message='.$e->getMessage(),'cron');
            }

            /**
             * Check if we need to send an alert
             */
            if($job->attempt_count > $alertAfterAttemptCount){
                $to = EmailUtils::getAdminEmailAddress();
                $subject = "OperationQueue entry failed more than limit";
                $body  = "OperationQueue ID: ".$job->id."<br>".PHP_EOL;
                $body .= "Activity: ".$job->operation."<br>".PHP_EOL;
                $body .= "Failure Count: ".$job->attempt_count."<br>".PHP_EOL;
                $body .= "Failed Since: ".$job->created." GMT<br>".PHP_EOL;
                $body .= "Exception: ".$e->getMessage();
                $email = new EmailQueue();
                $email->to = $to;
                $email->subject = $subject;
                $email->html_body = $body;
                try{
                    $email->save();
                } catch (\Exception $ex){
                    \Yii::error('cron=operation-queue EmailAlertError=OperationQueue entry failed more than limit message='.$body,'cron');
                }
            }

            throw $e;
        }
        /**
         * Processing step complete, delete queue entry
         */
        $job->delete();

        return true;
    }
    /**
     * Calculate delay factor based on number of attempts and return
     * as datetime based on time provided (optional)
     * @param int $attempts
     * @param int|bool $time
     * @param int $maxAttempts
     * @param int $maxDelay
     * @return int
     */
    public static function getNextTryTime($attempts, $time = false, $maxAttempts = 50, $maxDelay = 30)
    {
        $time = $time ?: time();
        if($attempts > $maxAttempts){
            \Yii::warning("OperationQueue: Have been trying operation for over 1 week", "application");
            return $time * 2; // set to way in the future so it won't try again
        }
        $delay = (int) pow($attempts, 3);
        if($delay > $maxDelay){
            $delay = $maxDelay;
        }
        return $time + ($delay * 60);
    }

    private function updateJenkinsJobs()
    {
        $prefix = Utils::getPrefix();
        $jenkins = JenkinsUtils::getJenkins();
        if ($jenkins){
            echo "[$prefix] Telling Jenkins to regenerate Jobs" . PHP_EOL;
            $jenkins->getJob("Job-Wrapper-Seed")->launch();
        } else {
            throw new \Exception("Unable to update the jenkins job list.  Jenkins unavailable",1457101819);
        }
    }
    private function checkForExpiredBuilds($job_id) {
        $prefix = Utils::getPrefix();
        echo "[$prefix] Check for expired builds for job $job_id" . PHP_EOL;
        $job = Job::findOne(['id' => $job_id]);
        if (!is_null($job)){
            $jenkins = JenkinsUtils::getJenkins();
            $jenkinsJob = $jenkins->getJob($job->name());
            $builds = $jenkinsJob->getBuilds();
            foreach (Build::find()->where([
                'job_id' => $job_id])->each(50) as $build){
                $matchFound = $this->checkForMatch($builds, $build->build_number);
                if ($matchFound == false){
                    $build->status = Build::STATUS_EXPIRED;
                    $build->save();
                }
            }
        }
    }
    private function checkForMatch($jenkinsBuilds, $build_number) {
        $matchFound = false;
        foreach ($jenkinsBuilds as $jenkinsBuild) {
            if ($build_number == $jenkinsBuild->getNumber()) {
                $matchFound = true;
                break;
            }
        }
        return $matchFound;
    }
    private function saveBuildToS3($build_id) {
        $prefix = Utils::getPrefix();
        echo "[$prefix] Save build to S3 $build_id" . PHP_EOL;
        $build = Build::findOneByBuildId($build_id);
        if ($build) {
            $job = $build->job;
            if ($job){
                $jenkins = JenkinsUtils::getJenkins();
                $jenkinsJob = $jenkins->getJob($job->name());
                $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
                if ($jenkinsBuild){
                    list($build->artifact_url, $build->version_code) = $this->saveBuild($build, $jenkinsBuild);
                    if (!$build->save()){
                        throw new \Exception("Unable to update Build entry, model errors: ".print_r($build->getFirstErrors(),true), 1450216434);
                    }
                }    
            }
        }
    }
     /**
     * Save the build to S3.
     * @param Build $build
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
    private function saveBuild($build, $jenkinsBuild)
    {
        $logger = new Appbuilder_logger("OperationQueue");
        $artifactUrl =  JenkinsUtils::getApkArtifactUrl($jenkinsBuild);
        $versionCodeArtifactUrl = JenkinsUtils::getVersionCodeArtifactUrl($jenkinsBuild);
        list($apkPublicUrl, $versionCode) = S3::saveBuildToS3($build, $artifactUrl, $versionCodeArtifactUrl);
        $log = JenkinsUtils::getlogBuildDetails($build);
        $log['NOTE:']='save the build to S3 and return $apkPublicUrl and $versionCode';
        $log['jenkins_ArtifactUrl'] = $artifactUrl;
        $log['apkPublicUrl'] = $apkPublicUrl;
        $log['version'] = $versionCode;
        $logger->appbuilderWarningLog($log);
        echo "returning: $apkPublicUrl version: $versionCode". PHP_EOL;

        return [$apkPublicUrl, $versionCode];
    }
}
