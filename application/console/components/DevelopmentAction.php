<?php
namespace console\components;

use common\models\Build;
use common\models\EmailQueue;
use common\models\OperationQueue;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\EmailUtils;
use common\components\JenkinsUtils;

use console\components\SyncScriptsAction;
use console\components\ManageBuildsAction;
use console\components\ManageReleasesAction;
use console\components\OperationsQueueAction;

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
    const UPDATEJOBS = 'UPDATEJOBS';
    
    private $actionType;
    private $sendToAddress;
    
    public function __construct()
    {
        $argv = func_get_args();
        $this->actionType = $argv[0];
        if ($this->actionType == self::TESTEMAIL) {
            $this->sendToAddress = $argv[1];
        }
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
            case self::UPDATEJOBS:
                $this->actionUpdateJobs();
        }  
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
     * Force the completed successful builds to upload the builds to S3. (Dev only)
     * Note: This should only be used during development to test whether
     *       S3 configuration is correct.
     */
    private function actionForceUploadBuilds()
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
    /**
     * Get completed build information. (Dev only)
     * Note: This should only be used during development for diagnosis.
     */
    private function actionGetBuildsCompleted()
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
     * Return the builds that have not completed. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    private function actionGetBuildsRemaining()
    {
        $logger = new Appbuilder_logger("CronController");
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
        $logger = new Appbuilder_logger("CronController");
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

    private function actionUpdateJobs() {
            $task = OperationQueue::UPDATEJOBS;
            OperationQueue::findOrCreate($task, null, null);
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