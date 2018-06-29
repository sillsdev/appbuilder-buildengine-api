<?php
namespace console\components;

use common\models\Build;
use common\models\EmailQueue;
use common\models\Job;
use common\models\OperationQueue;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\EmailUtils;
use common\components\JenkinsUtils;

use console\components\ManageBuildsAction;
use console\components\ManageReleasesAction;
use console\components\OperationsQueueAction;

use common\components\CodeCommit;
use common\components\CodeBuild;

use yii\console\Controller;
use common\helpers\Utils;

use JenkinsApi\Item\Build as JenkinsBuild;

class DevelopmentAction {
    const TESTEMAIL = 'TESTEMAIL';
    const GETCONFIG = 'GETCONFIG';
    const FORCEUPLOAD = 'FORCEUPLOAD';
    const GETCOMPLETED = 'GETCOMPLETED';
    const GETREMAINING = 'GETREMAINING';
    const GETBUILDS = 'GETBUILDS';
    const DELETEJOB = 'DELETEJOB';
    const TESTAWSSTAT = 'TESTAWSSTAT';
    
    private $actionType;
    private $sendToAddress;
    private $jobIdToDelete;
    private $jenkinsUtils;
    private $buildGuid;
    
    public function __construct()
    {
        $argv = func_get_args();
        $this->actionType = $argv[0];
        if ($this->actionType == self::TESTEMAIL) {
            $this->sendToAddress = $argv[1];
        }
        if ($this->actionType == self::DELETEJOB) {
            $this->jobIdToDelete = $argv[1];
        }
        if ($this->actionType == self::TESTAWSSTAT) {
            $this->buildGuid = $argv[1];
        }
        $this->jenkinsUtils = \Yii::$container->get('jenkinsUtils');
    }
    
    public function performAction() {
        switch($this->actionType){
            case self::TESTEMAIL:
                $this->actionTestEmail();
                break;
            case self::GETCONFIG:
                $this->actionGetConfig();
                break;
            case self::FORCEUPLOAD:
                $this->actionForceUploadBuilds();
                break;
            case self::GETCOMPLETED:
                $this->actionGetBuildsCompleted();
                break;
            case self::GETREMAINING:
                $this->actionGetBuildsRemaining();
                break;
            case self::GETBUILDS:
                $this->actionGetBuilds();
                break;
            case self::DELETEJOB:
                $this->actionDeleteJob();
                break;
            case self::TESTAWSSTART:
                $this->actionTestAwsStartBuild();
                break;
            case self::TESTAWSSTAT:
                $this->actionTestAwsBuildStatus();
                break;
        }  
    }
    private function actionTestAwsBuildStatus()
    {
        echo "Testing Get Build Status" . PHP_EOL;
        $codeBuild = new CodeBuild();
        $buildProcess = "build_scriptureappbuilder";
        $buildStatus = $codeBuild->getBuildStatus($this->buildGuid, $buildProcess);
        $phase = $buildStatus['currentPhase'];
        $status = $buildStatus['buildStatus'];
        echo " phase: " . $phase . " status: " . $status .PHP_EOL;
        if ($codeBuild->isBuildComplete($buildStatus)) 
        {
            echo ' Build Complete' . PHP_EOL;
        } else {
            echo ' Build Incomplete' . PHP_EOL;
        }
        var_dump($buildStatus);
    }
    /**
     * Test email action. Requires email adddress as parameter (Dev only)
     */
    private function actionTestEmail()
    {
        $body = \Yii::$app->mailer->render('@common/mail/operations/Test/enduser-testmsg',[
            'name' => "Whom it may concern",
            'crashPlanUrl' => "www.google.com",
        ]);
        $mail = new EmailQueue();
        $mail->to = $this->sendToAddress;
        $mail->subject = 'New test message';
        $mail->html_body = $body;
        if(!$mail->save()){
            echo "Failed to send email" . PHP_EOL;
        }
    }
    /**
     * Get Configuration (Dev only)
     */
    private function actionGetConfig()
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

        $artifactsBucket = S3::getArtifactsBucket();

        echo "Repo:". PHP_EOL."  URL:$repoUrl". PHP_EOL."  Branch:$repoBranch". PHP_EOL."  Path:$repoLocalPath". PHP_EOL."  Scripts:$scriptDir". PHP_EOL."  Key:$privateKey". PHP_EOL."  SshUser: $sshUser". PHP_EOL;
        echo "Git:". PHP_EOL."  Name:$userName". PHP_EOL."  Email:$userEmail". PHP_EOL;
        echo "Artifacts:". PHP_EOL."  Bucket:$artifactsBucket". PHP_EOL;
    }
    /**
     * Force the completed successful builds to upload the builds to S3. (Dev only)
     * Note: This should only be used during development to test whether
     *       S3 configuration is correct.
     */
    private function actionForceUploadBuilds()
    {
        $logger = new Appbuilder_logger("DevelopmentAction");
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
    /**
     * Get completed build information. (Dev only)
     * Note: This should only be used during development for diagnosis.
     */
    private function actionGetBuildsCompleted()
    {
        $logger = new Appbuilder_logger("DevelopmentAction");
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
                    'Number' => $build->build_guid,
                        ];
                    $logger->appbuilderExceptionLog($logException, $e);
                    echo PHP_EOL . "Exception Job=$jobName, BuildNumber=$build->build_guid ". PHP_EOL ."....Not found ". PHP_EOL;
                }

        }
    }
    /**
     * Return the builds that have not completed. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    private function actionGetBuildsRemaining()
    {
        $logger = new Appbuilder_logger("DevelopmentAction");
        $prefix = Utils::getPrefix();
        echo "[$prefix] Remaining Builds...". PHP_EOL;
        $complete = Build::STATUS_COMPLETED;
        foreach (Build::find()->where("status!='$complete'")->each(50) as $build){
            $jobName = $build->job->name();
            try {
                if ($build->build_number > 0) {
                    $log = $this->getlogJenkinsS3Details($build);
                    $logger->appbuilderInfoLog($log);
                }
            } catch (\Exception $e) {
                $logException = [
                    'problem' => 'Build not found.',
                    'jobName' => $jobName,
                    'Number' => $build->build_number,
                    'Status' => $build->status
                        ];
                $logger->appbuilderWarningLog($logException);
                echo "Job=$jobName, Number=$build->build_number, Status=$build->status". PHP_EOL ."....Not found ". PHP_EOL;
            }
        }
    }
    /**
     * Return all the builds. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    private function actionGetBuilds()
    {
        $logger = new Appbuilder_logger("DevelopmentAction");
        $prefix = Utils::getPrefix();
        echo "[$prefix] All Builds...". PHP_EOL;
        foreach (Build::find()->each(50) as $build){
            $jobName = $build->job->name();
            $logBuildDetails = JenkinsUtils::getlogBuildDetails($build);
            $logger->appbuilderWarningLog($logBuildDetails);
            try {
                if ($build->build_number > 0) {
                    $logJenkinsS3 = $this->getlogJenkinsS3Details($build);
                    $logger->appbuilderWarningLog($logJenkinsS3);
                }
            } catch (\Exception $e) {
                $logException = [
                    'problem' => 'Jenkins build '.$build->build_number.' not found',
                    'jobName' => $jobName
                        ];
                $logger->appbuilderWarningLog($logException);
                echo 'Exception: in actionGetBuilds build-> Jenkins build '.$build->build_number.' not found' . " $jobName". PHP_EOL . PHP_EOL;
            }
        }
    }

    private function actionDeleteJob() {
        echo "Deleting job $this->jobIdToDelete".PHP_EOL;
        $job = Job::findById($this->jobIdToDelete);
        if (is_null($job)) {
            echo "Job $this->jobIdToDelete not found".PHP_EOL;
        } else if ($job->delete()) {
            echo "Successfully deleted record".PHP_EOL;
        } else {
            echo "Failed to delete record".PHP_EOL;
        }
    }
    /*===============================================  logging ============================================*/
    /**
     * get Jenkins and S3 details
     * @param Build $build
     * @return Array
     */
    private function getlogJenkinsS3Details($build)
    {
        $jobName = $build->job->name();
        $log = [
            'logType' => 'S3 details',
            'jobName' => $jobName
        ];
        $log['request_id'] = $build->job->request_id;

        $jenkins = $this->jenkinsUtils->getJenkins();
        $jenkinsJob = $jenkins->getJob($build->job->name());
        $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
        $buildResult = $jenkinsBuild->getResult();
        list($artifactUrls, $artifactRelativePaths) = $this->jenkinsUtils->getArtifactUrls($jenkinsBuild);

        $log['jenkins_buildResult'] = $buildResult;
        $i = 1;
        foreach (array_map(null, $artifactUrls, $artifactRelativePaths) as list($url, $path)) {
            $log['jenkins_artifact_'.$i] = "S3: Path=$path, Url=$url";
            $i++;
        }

        echo "Job=$jobName, Number=$build->build_number, Status=$build->status". PHP_EOL
                        . "  Build: Result=$buildResult". PHP_EOL;
        return $log;
    }
}
