<?php

namespace common\models;
use common\models\EmailQueue;
use common\components\EmailUtils;
use console\components\CopyToS3Operation;
use console\components\CopyErrorToS3Operation;
use console\components\FindExpiredBuildsOperation;
use console\components\UpdateJobsOperation;
use console\components\MaxRetriesExceededException;

use common\helpers\Utils;
use yii\helpers\ArrayHelper;

/**
 * Class OperationQueue
 * @package common\models
 */
class OperationQueue extends OperationQueueBase
{
    const UPDATEJOBS = 'UPDATEJOBS';
    const SAVETOS3 = 'SAVETOS3';
    const FINDEXPIREDBUILDS = 'FINDEXPIREDBUILDS';
    const SAVEERRORTOS3 = "SAVEERRORTOS3";

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
        return $job;
    }
    public static function createOperation($operation, $objectID, $parms)
    {
        $job = new OperationQueue();
        $job->operation = $operation;
        $job->operation_object_id = $objectID;
        $job->operation_parms = $parms;
        $job->attempt_count = 0;
        $job->try_after = Utils::getDatetime();
        if (!$job->save()) {
             echo "Failed to create operation for task $operation" . PHP_EOL;         
        }

        return $job;
    }
    public static function createOperationObject($operation, $id, $operation_parms) {
        $operationObject = null;
        switch($operation){
            case OperationQueue::UPDATEJOBS:
                $operationObject = new UpdateJobsOperation();
                break;
            case OperationQueue::SAVETOS3:
                $operationObject = new CopyToS3Operation($id);
                break;
            case OperationQueue::FINDEXPIREDBUILDS:
                $operationObject = new FindExpiredBuildsOperation($id);
                break;
            case OperationQueue::SAVEERRORTOS3:
                $operationObject = new CopyErrorToS3Operation($id, $operation_parms);
        }
        return $operationObject;
    }
    /**
     * Find the next job in queue and process it if not over max attempts
     * @param string|null $currentTime The datetime after which to try jobs
     * @return bool|null
     * @throws \Exception
     */
    public static function processNext($currentTime=null)
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
        $object_id = $job->operation_object_id;
        $operationObject = self::createOperationObject($job->operation,$object_id, $job->operation_parms);
        $maxDelay = $operationObject->getMaximumDelay();
        $maxAttempts = $operationObject->getMaximumRetries();
        $alertAfterAttemptCount = $operationObject->getAlertAfterAttemptCount();
        /**
         * Make sure we're not over the limit of max_attempts before proceeding
         */
        if($job->attempt_count > $maxAttempts){
            $message = "Operation Queue entry beyond max attempts. ID: ".$job->id.", "
                      ."for task: ".$job->operation;
            $job->try_after = Utils::getDatetime(self::getNextTryTime($job->attempt_count, false, $maxAttempts, $maxDelay));
            $job->save();
            throw new MaxRetriesExceededException($message, 1457104373);
        }
        /**
         * Process operation
         * Catch exceptions to increment attempt_count and such
         */
        try{
            $operationObject->performOperation();
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
                $job->try_after = Utils::getDatetime(self::getNextTryTime($job->attempt_count, false, $maxAttempts, $maxDelay));
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
    public static function getNextTryTime($attempts, $time, $maxAttempts, $maxDelay)
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
}
