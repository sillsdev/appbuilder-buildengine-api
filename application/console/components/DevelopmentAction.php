<?php
namespace console\components;

use common\models\Build;
use common\models\EmailQueue;
use common\models\Job;
use common\models\Project;
use common\models\OperationQueue;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\EmailUtils;
use common\components\JenkinsUtils;
use common\components\STS;

use console\components\ManageBuildsAction;
use console\components\ManageReleasesAction;
use console\components\OperationsQueueAction;

use common\components\CodeCommit;
use common\components\CodeBuild;

use yii\console\Controller;
use common\helpers\Utils;

use JenkinsApi\Item\Build as JenkinsBuild;
use common\components\IAmWrapper;
use GitWrapper\GitWrapper;

class DevelopmentAction {
    const TESTEMAIL = 'TESTEMAIL';
    const GETCONFIG = 'GETCONFIG';
    const FORCEUPLOAD = 'FORCEUPLOAD';
    const GETCOMPLETED = 'GETCOMPLETED';
    const GETREMAINING = 'GETREMAINING';
    const GETBUILDS = 'GETBUILDS';
    const DELETEJOB = 'DELETEJOB';
    const TESTAWSSTAT = 'TESTAWSSTAT';
    const TESTIAM = 'TESTIAM';
    const GETPROJECTTOKEN = 'GETPROJECTTOKEN';
    const MOVEPROJECT = 'MOVEPROJECTTOS3';
    const MOVEALLPROJECTS = 'MOVEALLPROJECTSTOS3';
    const MIGRATEJOBS = 'MIGRATEJOBS';

    private $actionType;
    private $sendToAddress;
    private $jobIdToDelete;
    private $jenkinsUtils;
    private $buildGuid;
    private $projectId;
    
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
        if ($this->actionType == self::GETPROJECTTOKEN) {
            $this->projectId = $argv[1];
        }
        if ($this->actionType == self::MOVEPROJECT) {
            $this->projectId = $argv[1];
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
            case self::DELETEJOB:
                $this->actionDeleteJob();
                break;
            case self::TESTAWSSTAT:
                $this->actionTestAwsBuildStatus();
                break;
            case self::TESTIAM:
                $this->actionTestIam();
                break;
            case self::GETPROJECTTOKEN:
                $this->actionGetProjectToken();
                break;
            case self::MOVEPROJECT:
                $this->actionMoveProjectToS3();
                break;
            case self::MOVEALLPROJECTS:
                $this->actionMoveAllProjectsToS3();
                break;
            case self::MIGRATEJOBS:
                $this->actionMigrateJobs();
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
    private function actionTestIam()
    {
        $iam = new IAmWrapper();
        $arn = $iam->getRoleArn('build_app');
//        $cb = new CodeBuild();
//        $answer = $cb->projectExists('build_app');
//        $answer = $cb->createProject();
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
    private function actionGetProjectToken() {
        echo "Getting token for project $this->projectId".PHP_EOL;
        $project = Project::findById($this->projectId);
        if ($project->isS3Project()) {
            echo "Bucket: ". $project->getS3Bucket().PHP_EOL;
            echo "Folder: ". $project->getS3Folder().PHP_EOL;
            echo "Policy:".PHP_EOL.STS::getPolicy($project).PHP_EOL;
            $sts = new STS();
            $token = $sts->getProjectAccessToken($project, "TEST");
            echo "Path: ". $project->getS3Path().PHP_EOL;
            echo "export AWS_ACCESS_KEY_ID=".$token['AccessKeyId'].PHP_EOL;
            echo "export AWS_SECRET_ACCESS_KEY=".$token['SecretAccessKey'].PHP_EOL;
            echo "export AWS_SESSION_TOKEN=".$token['SessionToken'].PHP_EOL;
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

    private function actionMoveProjectToS3()
    {
        $project = Project::findById($this->projectId);
        $this->moveProjectToS3($project);
    }

    private function actionMoveAllProjectsToS3()
    {
        $projects = Project::find()->all();
        foreach ($projects as $project) {
            $this->moveProjectToS3($project);
        }
    }
    private function moveProjectToS3($project) {
        echo "Moving project $project->id to S3".PHP_EOL;
        if (!$this->isProjectConversionCandidate($project)) {
            echo "Project " . $project->project_name . " does not require conversion." . PHP_EOL;
        } else {
            $s3 = new S3();
            $s3Folder = $project->getS3Folder();
            echo $s3Folder . PHP_EOL;
            $baseFolder = Utils::lettersNumbersHyphensOnly($project->getS3BaseFolder());
            echo $baseFolder . PHP_EOL;
            $key = substr($s3Folder, 0, strrpos($s3Folder, '/'));
            echo $key . PHP_EOL;
            $bucket = S3::getProjectsBucket();
            if ($s3->doesObjectExist($bucket, $key, $baseFolder) == true)
            {
                echo "Folder exists on s3 already" . PHP_EOL;
            } else {
                $this->copyFolderFromCbToS3($project, $key, $s3);
                $sshUrl = $project->url;
                $project->setS3Project();
                $project->save();
                $this->updateJobs($sshUrl, $project->url);
            }
        }
        echo "Move project complete" . PHP_EOL;
    }
    private function copyFolderFromCbToS3($project, $key, $s3)
    {
        // Test 2 URL: ssh://APKAIO63SIBNZAEHNXLA@git-codecommit.us-east-1.amazonaws.com/v1/repos/scriptureappbuilder-CHB-en-EnglishGreek03134
        // Job ID 1
        // Test 4 URL: ssh://APKAIO63SIBNZAEHNXLA@git-codecommit.us-east-1.amazonaws.com/v1/repos/scriptureappbuilder-DEM-CHB-en-Test0306
        // Job ID 8
        $baseFolder = Utils::lettersNumbersHyphensOnly($project->getS3BaseFolder());
        echo "Copying Project Files for " . $baseFolder . PHP_EOL;
        $gitWrapper = new GitWrapper();
        $gitWrapper->setTimeout(600);
        $bucket = S3::getProjectsBucket();
        Utils::deleteDir("/tmp/copy");
        $tmpFolderName = "/tmp/copy/" . $project->project_name;
        mkdir($tmpFolderName, 0777, true);
        $codecommit = new CodeCommit();
        $branch = "master";
        $repoUrl = $codecommit->getSourceSshURL($project->url);
        echo "Cloning URL: $repoUrl" . PHP_EOL;
        $gitWrapper->cloneRepository($repoUrl, $tmpFolderName);
        $gitDir = $tmpFolderName . "/.git";
        Utils::deleteDir($gitDir);
        $tmpS3FolderName = "/tmp/copy/" . $baseFolder;
        echo "Renaming " . $tmpFolderName . "to " . $tmpS3FolderName . PHP_EOL;
        rename($tmpFolderName, $tmpS3FolderName);
        $keyPrefix = $key . "/";
        echo "Copying from " . $tmpS3FolderName . " to S3 " . $bucket . " " . $keyPrefix . " " . $baseFolder . PHP_EOL;
        $s3->uploadFolder("/tmp/copy", $bucket, $keyPrefix);
        Utils::deleteDir("/tmp/copy");
    }
    private function isProjectConversionCandidate($project)
    {
        $isComplete = Project::STATUS_COMPLETED == $project->status;
        $isSuccessful = Project::RESULT_SUCCESS == $project->result;
        $retVal = $isComplete && $isSuccessful && !$project->isS3Project();
        return $retVal;
    }
    private function updateJobs($sshUrl, $s3Url)
    {
        $jobs = Job::find()->where('git_url = :git_url',
        ['git_url'=>$sshUrl])->all();
        foreach ($jobs as $job) {
            echo "Updating job id " . $job->id . PHP_EOL;
            $job->git_url = $s3Url;
            $job->save();
        }
    }
    private function actionMigrateJobs()
    {
        echo "Migrating Jobs" . PHP_EOL;
        $guid = Utils::createGUID();
        echo "GUID: " . $guid . PHP_EOL;
        $jobs = Job::find()->groupBy(['git_url'])->all();
        foreach ($jobs as $job) {
            $this->migrateJob($job->git_url, $job->client_id);
        }
        echo "Migration complete" . PHP_EOL;
    }
    private function migrateJob($git_url, $client_id)
    {
        $this->migrateCreateProject($git_url, $client_id);
        $count = 1;
        $jobs = Job::find()->where('git_url = :git_url',
        ['git_url'=>$git_url])->all();
        foreach ($jobs as $job) {
            echo $count . "Migrating url " . $job->git_url . " client_id " . $job->client_id . PHP_EOL;
            $count = $count + 1;
            $guid = Utils::createGUID();
            $job->request_id = $guid;
            $job->save();
        }
    }
    private function migrateCreateProject($git_url, $client_id)
    {
        $repo = substr($git_url, strrpos($git_url, '/') + 1);
        $pos_group = strpos($repo, '-') + 1;
        $app_id = substr($repo, 0, $pos_group - 1);
        $rest = substr($repo, $pos_group);
        $group = substr($rest, 0, strpos($rest, '-'));
        $rest = substr($rest, strpos($rest, '-') + 1);
        $lang = substr($rest, 0, strpos($rest, '-'));
        $proj = substr($rest, strpos($rest, '-') + 1);
        $proj = str_replace("-", " ", $proj);
        if ($proj == '') {
            echo "**** Bad project name - Using default ****" . PHP_EOL;
            $proj = $lang .' Default';
        }
        echo "app_id: " . $app_id . " group: " . $group . " lang: " . $lang . " project: [" . $proj . "]" . PHP_EOL;
        $project = new Project();
        $project->status = Project::STATUS_COMPLETED;
        $project->result = Project::RESULT_SUCCESS;
        $project->url = $git_url;
        $project->app_id = $app_id;
        $project->client_id = $client_id;
        $project->language_code = $lang;
        $project->project_name = $proj;
        $project->save();

    }
}
