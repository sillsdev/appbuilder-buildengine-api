<?php
namespace console\controllers;

use common\models\Build;
use common\models\Release;
use common\models\EmailQueue;
use common\models\OperationQueue;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\EmailUtils;
use common\components\JenkinsUtils;

use console\components\MaxRetriesExceededException;
use console\components\SyncScriptsAction;
use console\components\ManageBuildsAction;
use console\components\ManageReleasesAction;

use yii\console\Controller;
use common\helpers\Utils;
use yii\web\ServerErrorHttpException;

use GitWrapper\GitWrapper;
use JenkinsApi\Item\Build as JenkinsBuild;
use JenkinsApi\Item\Job as JenkinsJob;

class CronController extends Controller
{
    /**
     *
     * @return \GitWrapper\GitWorkingCopy
     */
    private function getRepo()
    {
        $privateKey = \Yii::$app->params['buildEngineRepoPrivateKey'];
        $repoUrl = \Yii::$app->params['buildEngineRepoUrl'];
        $repoBranch = \Yii::$app->params['buildEngineRepoBranch'];
        $repoLocalPath =\Yii::$app->params['buildEngineRepoLocalPath'];

        // Verify buildEngineRepoUrl is a SSH Url
        if (is_null($repoUrl) || !preg_match('/^ssh:\/\//', $repoUrl)) {
            throw new ServerErrorHttpException("BUILD_ENGINE_REPO_URL must be SSH Url: $repoUrl", 1456850613);
        }

        echo "1) RepoUrl: $repoUrl\n";

        // If buildEngineRepoUrl is CodeCommit, insert the userId
        if (preg_match('/^ssh:\/\/git-codecommit/', $repoUrl)) {
            // If using CodeCommit, GitSshUser is required
            $sshUser = \Yii::$app->params['buildEngineGitSshUser'];
            if (is_null($sshUser)) {
                throw new ServerErrorHttpException("BUILD_ENGINE_GIT_SSH_USER must be set if using codecommit: $repoUrl", 1456850614);
            }
            $repoUrl = "ssh://" . $sshUser . "@" . substr($repoUrl, 6);
        }

        echo "2) RepoUrl: $repoUrl\n";

        require_once __DIR__ . '/../../vendor/autoload.php';
        $wrapper = new GitWrapper();

        $wrapper->setEnvVar('HOME', '/data');
        $wrapper->setPrivateKey($privateKey);
        $git = null;
        if (!file_exists($repoLocalPath))
        {
            $git = $wrapper->clone($repoUrl, $repoLocalPath);
            $git->config('push.default', 'simple');
        } else {
            $git = $wrapper->init($repoLocalPath);
            $git->fetchAll();
            try {
                $git->reset("--hard", "origin/$repoBranch");
            } catch (\Exception $e) {
                echo "origin/$repoBranch doesn't exist yet. \n";
            }
        }
        // Set afterwards in case the configuration changes after
        // the repo has been cloned (i.e. services has been restarted
        // with different configuration).
        $userName = \Yii::$app->params['buildEngineGitUserName'];
        $userEmail = \Yii::$app->params['buildEngineGitUserEmail'];

        $git->config('user.name', $userName);
        $git->config('user.email', $userEmail);

        // Check to see if empty repo
        try {
            $git->checkout($repoBranch);

        } catch (\Exception $e) {
            echo "$repoBranch doesn't exist.  Trying to create it. \n";
            $git->checkoutNewBranch($repoBranch);
        }

        return $git;
    }
    /**
     * Synchronize the Job configuration in database with groovy scripts.
     */
    public function actionSyncScripts()
    {
        $viewPath = $this->getViewPath();
        $git = $this->getRepo();
        $repoLocalPath = \Yii::$app->params['buildEngineRepoLocalPath'];
        $scriptDir = \Yii::$app->params['buildEngineRepoScriptDir'];
        $appBuilderGitSshUser = \Yii::$app->params['appBuilderGitSshUser'];
        SyncScriptsAction::performAction($this, $viewPath, $git, $repoLocalPath, $scriptDir, $appBuilderGitSshUser);
    }
    /**
     * Manage the state of the builds and process the current state
     * until the status is complete.
     */
    public function actionManageBuilds()
    {
        ManageBuildsAction::performAction();
    }

    /**
     * Manage the state of the releases and process the current state
     * until the status is complete.
     */
    public function actionManageReleases()
    {
        ManageReleasesAction::performAction();
    }
    /**
     * Send queued emails
    */
    public function actionSendEmails($verbose=false)
    {
        $emailCount = EmailQueue::find()->count();

        if($emailCount && is_numeric($emailCount)){
            echo "cron/send-emails - Count: " . $emailCount . ". ". PHP_EOL;
        }

        list($emails_sent, $errors) = EmailUtils::sendEmailQueue();
        if (count($emails_sent) > 0) {
            echo "... sent=".count($emails_sent). PHP_EOL;
        }
        if ($errors && count($errors) > 0) {
            echo '... errors='.count($errors).' messages=['.join(',',$errors).']' . PHP_EOL;
        }
    }

    /**
     * Remove expired builds from S3
    */
    public function actionRemoveExpiredBuilds()
    {
        $logger = new Appbuilder_logger("CronController");
        $prefix = Utils::getPrefix();
        echo "[$prefix] actionRemoveExpiredBuilds: Started". PHP_EOL;
        foreach (Build::find()->where([
            'status' => Build::STATUS_EXPIRED])->each(50) as $build){
            if ($build->artifact_url != null) {
                echo "...Remove expired job $build->job_id id $build->id ". PHP_EOL;
                S3::removeS3Artifacts($build);
                $build->clearArtifactUrl();
                $logBuildDetails = JenkinsUtils::getlogBuildDetails($build);
                $logBuildDetails['NOTE: ']='Remove expired S3 Artifacts for an expired build.';
                $logger->appbuilderWarningLog($logBuildDetails);
            }
        }
        echo "[$prefix] actionRemoveExpiredBuilds: Conpleted". PHP_EOL;

    }

    /**
     * Process operation_queue
     * This function will iterate up to the configured limit and for each iteration
     * it will process the next job in queue
     */
    public function actionOperationQueue($verbose=false)
    {
        $logger = new Appbuilder_logger("CronController");

        // Set the maximum number of entries that may be attempted in one run
        $batchSize = 10;

        // Capture start time for log
        $starttimestamp = time();
        $starttime = Utils::getDatetime();

        // Initialize variables
        $successfulJobs = 0;
        $failedJobs = 0;
        $iterationsRun = 0;
        $maxRetriesExceeded = 0;

        $queuedJobs = OperationQueue::find()->count();
        // Do the work
        for($i=0; $i<$queuedJobs; $i++){
            $iterationsRun++;
            try{
                $results = OperationQueue::processNext(null);
                if($results) {
                    $successfulJobs++;
                } else {
                    break;
                }
            }
            catch (MaxRetriesExceededException $e) {
                // Don't count entries in the database that are now obsolete
                // and are never deleted
                echo "Caught max retry exception".PHP_EOL;
                $maxRetriesExceeded++;
            }

            catch (\Exception $e) {
                echo "Caught anothe exception".PHP_EOL;
                echo $e->getMessage() .PHP_EOL;
                $failedJobs++;
             }
            $attempts = $successfulJobs + $failedJobs;
            if ($attempts >= $batchSize) {
                break;
            }
        }

        // Capture endtime for log
        $endtimestamp = time();
        $endtime = Utils::getDatetime();
        $totaltime = $endtimestamp-$starttimestamp;

        $logMsg  = 'cron/operation-queue - queued='.$queuedJobs.' successful='.$successfulJobs.' failed='.$failedJobs;
        $logMsg .= ' retries exceeded='.$maxRetriesExceeded.' iterations='.$iterationsRun.' totaltime='.$totaltime;
        $logArray = [$logMsg];
        if($failedJobs > 0){
            $logger->appbuilderErrorLog($logArray);
        } else{
            if($verbose && $verbose != 'false'){
                $logger->appbuilderWarningLog($logArray);
            } else {
                $logger->appbuilderInfoLog($logArray);
            }
        }

        echo PHP_EOL . $logMsg . PHP_EOL;

    }

    /**
     * Test email action. Requires email adddress as parameter (Dev only)
     */
    public function actionTestEmail($sendToAddress)
    {
        $body = \Yii::$app->mailer->render('@common/mail/operations/Test/enduser-testmsg',[
            'name' => "Whom it may concern",
            'crashPlanUrl' => "www.google.com",
        ]);
        $mail = new EmailQueue();
        $mail->to = $sendToAddress;
        $mail->subject = 'New test message';
        $mail->html_body = $body;
        if(!$mail->save()){
            echo "Failed to send email" . PHP_EOL;
        }
    }
    /**
     * Get Configuration (Dev only)
     */
    public function actionGetConfig()
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] Get Configuration..." . PHP_EOL;

        $repoLocalPath = \Yii::$app->params['buildEngineRepoLocalPath'];
        $scriptDir = \Yii::$app->params['buildEngineRepoScriptDir'];
        $privateKey = \Yii::$app->params['buildEngineRepoPrivateKey'];
        $repoUrl = \Yii::$app->params['buildEngineRepoUrl'];
        $repoBranch = \Yii::$app->params['buildEngineRepoBranch'];
        $userName = \Yii::$app->params['buildEngineGitUserName'];
        $userEmail = \Yii::$app->params['buildEngineGitUserEmail'];
        $sshUser = \Yii::$app->params['buildEngineGitSshUser'] ?: "";

        $jenkinsUrl = \Yii::$app->params['buildEngineJenkinsMasterUrl'];
        $jenkins = JenkinsUtils::getJenkins();
        $jenkinsBaseUrl = $jenkins->getBaseUrl();

        $artifactUrlBase = JenkinsUtils::getArtifactUrlBase();

        echo "Repo:". PHP_EOL."  URL:$repoUrl". PHP_EOL."  Branch:$repoBranch". PHP_EOL."  Path:$repoLocalPath". PHP_EOL."  Scripts:$scriptDir". PHP_EOL."  Key:$privateKey". PHP_EOL."  SshUser: $sshUser". PHP_EOL;
        echo "Jenkins:". PHP_EOL."  BuildEngineJenkinsMasterUrl: $jenkinsUrl". PHP_EOL."  Jenkins.baseUrl: $jenkinsBaseUrl". PHP_EOL;
        echo "Git:". PHP_EOL."  Name:$userName". PHP_EOL."  Email:$userEmail". PHP_EOL;
        echo "Artifacts:". PHP_EOL."  UrlBase:$artifactUrlBase". PHP_EOL;
    }
    /**
     * Return all the builds. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    public function actionGetBuilds()
    {
        $logger = new Appbuilder_logger("CronController");
        $prefix = Utils::getPrefix();
        echo "[$prefix] All Builds...". PHP_EOL;
        foreach (Build::find()->each(50) as $build){
            $jobName = $build->job->name();
            $logBuildDetails = JenkinsUtils::getlogBuildDetails($build);
            $logger->appbuilderWarningLog($logBuildDetails);
            try {
                if ($build->build_number > 0) {
                    //$logentries = new appbuilder_logger("cron-actionGetBuilds");
                    $logJenkinsS3 = $this->getlogJenkinsS3Details($build);
                    $logger->appbuilderWarningLog($logJenkinsS3);
                }
            } catch (\Exception $e) {
                $logException = [
                    'problem' => 'build->build_number is not > 0',
                    'jobName' => $jobName
                        ];
                $logger->appbuilderExceptionLog($logException, $e);
                echo 'Exception: in actionGetBuilds build->build_number is not > 0 for job' . " $jobName". PHP_EOL . PHP_EOL;
            }
        }
    }
    /**
     * Return the builds that have not completed. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    public function actionGetBuildsRemaining()
    {
        $logger = new Appbuilder_logger("CronController");
        $prefix = Utils::getPrefix();
        echo "[$prefix] Remaining Builds...". PHP_EOL;
        $complete = Build::STATUS_COMPLETED;
        foreach (Build::find()->where("status!='$complete'")->each(50) as $build){
            $jobName = $build->job->name();
            try {
                if ($build->build_number > 0) {
                    $log = getlogJenkinsS3Details($build);
                    $logger->appbuilderInfoLog($log);
                }
            } catch (\Exception $e) {
                $logException = [
                    'problem' => 'Build not found.',
                    'jobName' => $jobName,
                    'Number' => $build->build_number,
                    'Status' => $build->status
                        ];
                $logger->appbuilderExceptionLog($logException, $e);
                echo "Job=$jobName, Number=$build->build_number, Status=$build->status". PHP_EOL ."....Not found ". PHP_EOL;
            }

        }
    }
    /**
     * Get completed build information. (Dev only)
     * Note: This should only be used during development for diagnosis.
     */
    public function actionGetBuildsCompleted()
    {
        $logger = new Appbuilder_logger("CronController");
        foreach (Build::find()->where([
            'status' => Build::STATUS_COMPLETED,
            'result' => JenkinsBuild::SUCCESS])->each(50) as $build){
                $jobName = $build->job->name();
                try {
                    $logBuildDetails = JenkinsUtils::getlogBuildDetails($build);
                    $logger->appbuilderWarningLog($logBuildDetails);
                } catch (\Exception $e) {
                    $logException = [
                    'problem' => 'Build not found.',
                    'jobName' => $jobName,
                    'Number' => $build->build_number,
                        ];
                    $logger->appbuilderExceptionLog($logException, $e);
                    echo PHP_EOL . "Exception Job=$jobName, BuildNumber=$build->build_number ". PHP_EOL ."....Not found ". PHP_EOL;
                }

        }
    }
    /**
     * Force the completed successful builds to upload the builds to S3. (Dev only)
     * Note: This should only be used during development to test whether
     *       S3 configuration is correct.
     */
    public function actionForceUploadBuilds()
    {
        $logger = new Appbuilder_logger("CronController");
        foreach (Build::find()->each(50) as $build){
            if ($build->status == Build::STATUS_COMPLETED
                && $build->result == JenkinsBuild::SUCCESS)
            {
                $jobName = $build->job->name();
                echo "Attempting to save Build: Job=$jobName, BuildNumber=$build->build_number". PHP_EOL;
                $logBuildDetails = JenkinsUtils::getlogBuildDetails($build);
                $logBuildDetails['NOTE: ']='Force the completed successful builds to upload the builds to S3.';
                $logBuildDetails['NOTE2: ']='Attempting to save Build.';
                $logger->appbuilderWarningLog($logBuildDetails);
                $task = OperationQueue::SAVETOS3;
                $build_id = $build->id;
                OperationQueue::findOrCreate($task, $build_id, null);
            }
        }
    }

    /*===============================================  logging ============================================*/
    /**
     * get Jenkins and S3 details
     * @param Build $build
     * @return Array
     */
    public function getlogJenkinsS3Details($build)
    {
        $jobName = $build->job->name();
        $log = [
            'logType' => 'S3 details',
            'jobName' => $jobName
        ];
        $log['request_id'] = $build->job->request_id;

        $jenkins = JenkinsUtils::getJenkins();
        $jenkinsJob = $jenkins->getJob($build->job->name());
        $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
        $buildResult = $jenkinsBuild->getResult();
        $buildArtifact = JenkinsUtils::getApkArtifactUrl($jenkinsBuild);
        $s3Url = S3::getS3Url($build, $buildArtifact);

        $log['jenkins_buildResult'] = $buildResult;
        $log['jenkins_ArtifactUrl'] = $buildArtifact;
        $log['S3: Url'] = $s3Url;
        
        echo "Job=$jobName, Number=$build->build_number, Status=$build->status". PHP_EOL
                        . "  Build: Result=$buildResult, Artifact=$buildArtifact". PHP_EOL
                        . "  S3: Url=$s3Url". PHP_EOL;
        return $log;
    }
}
