<?php
namespace console\components;

use common\models\OperationQueue;
use common\components\Appbuilder_logger;
use console\components\MaxRetriesExceededException;
use common\helpers\Utils;

class OperationsQueueAction extends ActionCommon {
    private $verbose;
    private $batchSize;
    private $successfulJobs;
    private $failedJobs;
    private $iterationsRun;
    private $maxRetriesExceeded;
    
    public function __construct($verbose)
    {
        $this->verbose = $verbose;
        // Set the maximum number of entries that may be attempted in one run
        $this->batchSize = 10;
        $this->successfulJobs = 0;
        $this->failedJobs = 0;
        $this->iterationsRun = 0;
        $this->maxRetriesExceeded = 0;
    }
    /**
     * Process operation_queue
     * This function will iterate up to the configured limit and for each iteration
     * it will process the next job in queue
     */
    public function performAction()
    {
        $prefix = Utils::getPrefix();

/*        $tokenSemaphore = sem_get(20);
        $tokenValue = shm_attach(21, 100);
        if (!$this->try_lock($tokenSemaphore, $tokenValue)){
            echo "[$prefix] OperationQueueAction: Semaphore Blocked" . PHP_EOL;
            return;
        }
*/
        echo "[$prefix] OperationQueue: Action start" . PHP_EOL;
        try {
            // Capture start time for log
            $starttimestamp = time();
            $queuedJobs = OperationQueue::find()->count();
            // Do the work
            for($i=0; $i<$queuedJobs; $i++){
                if (!$this->processNextJob()) {
                    // No more jobs to process
                    break;
                }
                $attempts = $this->successfulJobs + $this->failedJobs;
                if ($attempts >= $this->batchSize) {
                    break;
                }
            }
            $this->logResults($starttimestamp, $queuedJobs);
        }
        catch (\Exception $e) {
            echo "Caught exception".PHP_EOL;
            echo $e->getMessage() .PHP_EOL;
            echo $e->getFile() . PHP_EOL;
            echo $e->getLine() . PHP_EOL;
            $logger = new Appbuilder_logger("OperationQueueAction");
            $logException = [
                'problem' => 'Caught exception',
            ];
            $logger->appbuilderExceptionLog($logException, $e);
        }
        finally {
//            $this->release($tokenSemaphore, $tokenValue);
        }
    }
    private function processNextJob()
    {
        $retVal = true;            
        $this->iterationsRun++;
        try{
            $results = OperationQueue::processNext(null);
            if($results) {
                $this->successfulJobs++;
            } else {
                $retVal = false;
            }
        }
        catch (MaxRetriesExceededException $e) {
            // Don't count entries in the database that are now obsolete
            // and are never deleted
            echo "Caught max retry exception".PHP_EOL;
            $logger = new Appbuilder_logger("OperationsQueueAction");
            $logException = [
            'problem' => 'Maximum retries exceeded',
                ];
            $logger->appbuilderExceptionLog($logException, $e);
            $this->maxRetriesExceeded++;
        }

        catch (\Exception $e) {
            echo "Caught another exception".PHP_EOL;
            echo $e->getMessage() .PHP_EOL;
            echo $e->getFile() . PHP_EOL;
            echo $e->getLine() . PHP_EOL;
            $logger = new Appbuilder_logger("OperationsQueueAction");
            $logException = [
            'problem' => 'Caught another exception',
                ];
            $logger->appbuilderExceptionLog($logException, $e);
            $this->failedJobs++;
         }
         return $retVal;
    }
    private function logResults($starttimestamp, $queuedJobs)
    {
        $logger = new Appbuilder_logger("OperationsQueueAction");
        // Capture endtime for log
        $endtimestamp = time();
        $totaltime = $endtimestamp-$starttimestamp;

        $logMsg  = 'cron/operation-queue - queued='.$queuedJobs.' successful='.$this->successfulJobs.' failed='.$this->failedJobs;
        $logMsg .= ' retries exceeded='.$this->maxRetriesExceeded.' iterations='.$this->iterationsRun.' totaltime='.$totaltime;
        $logArray = [$logMsg];
        if($this->failedJobs > 0){
            $logger->appbuilderErrorLog($logArray);
        } else{
            if($this->verbose && $this->verbose != 'false'){
                $logger->appbuilderWarningLog($logArray);
            } else {
                $logger->appbuilderInfoLog($logArray);
            }
        }

        echo PHP_EOL . $logMsg . PHP_EOL;        
    }
}