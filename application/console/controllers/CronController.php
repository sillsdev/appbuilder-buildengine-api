<?php
namespace console\controllers;

use common\models\Job;
use common\models\Build;
use common\models\Release;
use common\models\EmailQueue;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\EmailUtils;

use yii\console\Controller;
use common\helpers\Utils;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;

use GitWrapper\GitWrapper;
use JenkinsApi\Jenkins;
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
            $git->reset("--hard", "origin/$repoBranch");
        }
        $git->checkout($repoBranch);

        // Set afterwards in case the configuration changes after
        // the repo has been cloned (i.e. services has been restarted
        // with different configuration).
        $userName = \Yii::$app->params['buildEngineGitUserName'];
        $userEmail = \Yii::$app->params['buildEngineGitUserEmail'];

        $git->config('user.name', $userName);
        $git->config('user.email', $userEmail);
        return $git;
    }

    private function getPrefix()
    {
        return date('Y-m-d H:i:s');
    }
    /**
     *
     * @return Jenkins
     */
    private function getJenkins(){
        $jenkinsUrl = \Yii::$app->params['buildEngineJenkinsMasterUrl'];
        $jenkins = new Jenkins($jenkinsUrl);
        return $jenkins;
    }

    private function getArtifactUrlBase(){
        return \Yii::$app->params['buildEngineArtifactUrlBase'] . "/" . \Yii::$app->params['appEnv'];
    }

    /**
     *
     * @param string $subject
     * @param string $patterns
     * @return string
     */
    private function doReplacements($subject, $patterns)
    {
        foreach ($patterns as $pattern => $replacement )
        {
            $subject = preg_replace($pattern, $replacement, $subject);
        }
        return $subject;
    }

    /**
     * Create a new Build.  If there is a Build in the initialized state,
     * then it is OK to use that as the build.
     * @param Job $job
     * @throws ServerErrorHttpException
     * @return Build
     */
    private function createBuild($job)
    {
        $build = $job->getLatestBuild();
        if (!$build || $build->status != Build::STATUS_INITIALIZED){
            $build = $job->createBuild();
            if(!$build){
                throw new ServerErrorHttpException("Failed to create build for job $job->id", 1443811601);
            }
        }
        return $build;
    }

    private function updateJenkinsJobs()
    {
        $prefix = $this->getPrefix();
        $jenkins = $this->getJenkins();
        if ($jenkins){
            echo "[$prefix] Telling Jenkins to regenerate Jobs" . PHP_EOL;
            $jenkins->getJob("Job-Wrapper-Seed")->launch();
        }
    }

    public function actionGetRepo()
    {
        $this->getRepo();
    }
    /**
     * Synchronize the Job configuration in database with groovy scripts.
     */
    public function actionSyncScripts()
    {
        $logMsg = 'cron/sync-scripts - ';
        $prefix = $this->getPrefix();

        $repoLocalPath = \Yii::$app->params['buildEngineRepoLocalPath'];
        $scriptDir = \Yii::$app->params['buildEngineRepoScriptDir'];
        $artifactUrlBase = $this->getArtifactUrlBase();

        // When using Codecommit, the user portion in the url has to be changed
        // to the User associated with the public key in AWS.
        $buildAgentCodecommitSshUser = \Yii::$app->params['buildEngineBuildAgentCodecommitGitSshUser'];
        $gitSubstPatterns = [ '/([0-9A-Za-z]*)@git-codecommit/' => "$buildAgentCodecommitSshUser@git-codecommit" ];

        $git = $this->getRepo();

        $jobs = [];
        // TODO: Apps should be pulled from a database?
        $apps = ['scriptureappbuilder' => 1];
        $localScriptDir = $repoLocalPath . DIRECTORY_SEPARATOR . $scriptDir;
        $dataScriptDir = $this->getViewPath().DIRECTORY_SEPARATOR."scripts";
        $utilitiesSourceDir = $dataScriptDir.DIRECTORY_SEPARATOR."utilities";
        $utilitiesDestDir = $localScriptDir . DIRECTORY_SEPARATOR . "utilities";
        $this->recurse_copy($utilitiesSourceDir, $utilitiesDestDir, $git);
        foreach (array_keys($apps) as $app) {
            $appSourceDir = $dataScriptDir.DIRECTORY_SEPARATOR.$app;
            $appDestDir = $localScriptDir . DIRECTORY_SEPARATOR .$app;
             $this->recurse_copy($appSourceDir, $appDestDir, $git);
        }
        foreach (Job::find()->each(50) as $job)
        {
            $publisherName = $job->publisher_id;
            $buildJobName = $job->name();
            $gitUrl = $this->doReplacements($job->git_url, $gitSubstPatterns);

            $script = $this->renderPartial("scripts/$job->app_id", [
                'publisherName' => $publisherName,
                'buildJobName' => $buildJobName,
                'publishJobName' => Release::jobNameForBuild($buildJobName),
                'gitUrl' => $gitUrl,
                'artifactUrlBase' => $artifactUrlBase,
            ]);

            $file = $localScriptDir . DIRECTORY_SEPARATOR . $buildJobName . ".groovy";
            $handle = fopen($file, "w");
            fwrite($handle, $script);
            fclose($handle);
            if ($git->getStatus($file))
            {
                echo "[$prefix] Updated: $buildJobName" . PHP_EOL;
                $git->add($file);
                $this->createBuild($job);
            }

            $jobs[$buildJobName] = 1;
        }

        // Remove Scripts that are not in the database
        $globFileName = "*_*.groovy";
        foreach (glob($localScriptDir . DIRECTORY_SEPARATOR .  $globFileName) as $scriptFile)
        {
            $jobName = basename($scriptFile, ".groovy");
            list($app_id, $request_id) = explode("_", $jobName);
            if (!array_key_exists($app_id, $apps))
            {
                continue;
            }
            if (!array_key_exists($jobName, $jobs))
            {
                echo "[$prefix] Removing: $jobName" . PHP_EOL;
                $git->rm($scriptFile);
            }
        }

        if ($git->hasChanges())
        {
            echo "[$prefix] Changes detected...committing..." . PHP_EOL;
            $git->commit('cron update scripts');
            $git->push();
            $this->updateJenkinsJobs();
        }
    }

    /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
    private function getApkArtifactUrl($jenkinsBuild)
    {
       return $this->getArtifactUrl($jenkinsBuild, "/\.apk$/");
    }
    /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
     private function getVersionCodeArtifactUrl($jenkinsBuild)
     {
         return $this->getArtifactUrl($jenkinsBuild, "/version_code.txt/");
     }
    /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @param string $artifactPattern
     * @return string
     */
    private function getArtifactUrl($jenkinsBuild, $artifactPattern)
    {
        $artifacts = $jenkinsBuild->get("artifacts");
        if (!$artifacts) { return null; }
        $artifact = null;
        foreach ($artifacts as $testArtifact) {
            if(preg_match($artifactPattern,$testArtifact->relativePath)) {
                $artifact = $testArtifact;
                break;
            }
        }
        if (!$artifact) {
            echo "getArtifactURL: No artifact matching ".$artifactPattern . PHP_EOL;
            return null;
        }
        $relativePath = $artifact->relativePath;
        $baseUrl = $jenkinsBuild->getJenkins()->getBaseUrl();
        $buildUrl = $jenkinsBuild->getBuildUrl();
        $pieces = explode("job", $buildUrl);
        return $baseUrl."job".$pieces[1]."artifact/".$relativePath;
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
    //    $mail->cc = 'dmoore1768@yahoo.com';
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
        $prefix = $this->getPrefix();
        echo "[$prefix] Get Configuration..." . PHP_EOL;

        $repoLocalPath = \Yii::$app->params['buildEngineRepoLocalPath'];
        $scriptDir = \Yii::$app->params['buildEngineRepoScriptDir'];
        $privateKey = \Yii::$app->params['buildEngineRepoPrivateKey'];
        $repoUrl = \Yii::$app->params['buildEngineRepoUrl'];
        $repoBranch = \Yii::$app->params['buildEngineRepoBranch'];
        $repoLocalPath =\Yii::$app->params['buildEngineRepoLocalPath'];
        $userName = \Yii::$app->params['buildEngineGitUserName'];
        $userEmail = \Yii::$app->params['buildEngineGitUserEmail'];

        $jenkinsUrl = \Yii::$app->params['buildEngineJenkinsMasterUrl'];
        $jenkins = $this->getJenkins();
        $jenkinsBaseUrl = $jenkins->getBaseUrl();

        $artifactUrlBase = $this->getArtifactUrlBase();
        $appEnv = \Yii::$app->params['appEnv'];

        echo "Repo:". PHP_EOL."  URL:$repoUrl". PHP_EOL."  Branch:$repoBranch". PHP_EOL."  Path:$repoLocalPath". PHP_EOL."  Scripts:$scriptDir". PHP_EOL."  Key:$privateKey". PHP_EOL;
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
        $prefix = $this->getPrefix();
        echo "[$prefix] All Builds...". PHP_EOL;
        foreach (Build::find()->each(50) as $build){
            $jobName = $build->job->name();
            $logBuildDetails = $this->getlogBuildDetails($build);
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
        $jenkins = $this->getJenkins();
        $prefix = $this->getPrefix();
        echo "[$prefix] Remaining Builds...". PHP_EOL;
        $complete = Build::STATUS_COMPLETED;
        foreach (Build::find()->where("status!='$complete'")->each(50) as $build){
            $jobName = $build->job->name();
            try {
                if ($build->build_number > 0) {
                    $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                    $buildResult = $jenkinsBuild->getResult();
                    $buildArtifact = $this->getApkArtifactUrl($jenkinsBuild);
                    $s3Url = S3::getS3Url($build,$buildArtifact);
                    echo "Job=$jobName, Number=$build->build_number, Status=$build->status". PHP_EOL
                        . "  Build: Result=$buildResult, Artifact=$buildArtifact". PHP_EOL
                        . "  S3: Url=$s3Url". PHP_EOL;
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
        $jenkins = $this->getJenkins();
        foreach (Build::find()->where([
            'status' => Build::STATUS_COMPLETED,
            'result' => JenkinsBuild::SUCCESS])->each(50) as $build){
                $jobName = $build->job->name();
                try {
                    $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                    $artifactUrl = $this->getApkArtifactUrl($jenkinsBuild);

                    echo "Job=$jobName, BuildNumber=$build->build_number, Url=$artifactUrl". PHP_EOL;
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
     * Force the completed successful builds to upload the builds to S3. (Dev only)
     * Note: This should only be used during development to test whether
     *       S3 configuration is correct.
     */
    public function actionForceUploadBuilds()
    {
        $logger = new Appbuilder_logger("CronController");
        $jenkins = $this->getJenkins();
        foreach (Build::find()->each(50) as $build){
            if ($build->status == Build::STATUS_COMPLETED
                && $build->result == JenkinsBuild::SUCCESS)
            {
                $jobName = $build->job->name();
                $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                echo "Attempting to save Build: Job=$jobName, BuildNumber=$build->build_number". PHP_EOL;
                $logBuildDetails = $this->getlogBuildDetails($build);
                $logBuildDetails['NOTE: ']='Force the completed successful builds to upload the builds to S3.';
                $logBuildDetails['NOTE2: ']='Attempting to save Build.';
                $logger->appbuilderWarningLog($logBuildDetails);
                $this->saveBuild($build, $jenkinsBuild);
            }
        }
    }

    /**
     * Remove expired builds from S3
    */
    public function actionRemoveExpiredBuilds()
    {
        $logger = new Appbuilder_logger("CronController");
        $prefix = $this->getPrefix();
        echo "[$prefix] actionRemoveExpiredBuilds: Started". PHP_EOL;
        foreach (Build::find()->where([
            'status' => Build::STATUS_EXPIRED])->each(50) as $build){
            if ($build->artifact_url != null) {
                echo "...Remove expired job $build->job_id id $build->id ". PHP_EOL;
                $this-removeS3Artifacts($build);
                $build->clearArtifactUrl();
                $logBuildDetails = $this->getlogBuildDetails($build);
                $logBuildDetails['NOTE: ']='Remove expired S3 Atrtifacts for an expired build.';
                $logger->appbuilderWarningLog($logBuildDetails);
            }
        }
        echo "[$prefix] actionRemoveExpiredBuilds: Conpleted". PHP_EOL;

    }
    private function getBuild($id)
    {
        $build = Build::findOne(['id' => $id]);
        if (!$build){
            echo "Build not found ". PHP_EOL;
            throw new NotFoundHttpException();
        }
        return $build;
    }

    /**
     * Save the build to S3.
     * @param Build $build
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
    private function saveBuild($build, $jenkinsBuild)
    {
        $logger = new Appbuilder_logger("CronController");
        $artifactUrl =  $this->getApkArtifactUrl($jenkinsBuild);
        $versionCodeArtifactUrl = $this->getVersionCodeArtifactUrl($jenkinsBuild);
        list($apkPublicUrl, $versionCode) = S3::saveBuildToS3($build, $artifactUrl, $versionCodeArtifactUrl);

        $log = $this->getlogBuildDetails($build);
        $log['NOTE:']='save the build to S3 and return $apkPublicUrl and $versionCode';
        $log['jenkins_ArtifactUrl'] = $artifactUrl;
        $log['apkPublicUrl'] = $apkPublicUrl;
        $log['version'] = $versionCode;
        $logger->appbuilderWarningLog($log);
        echo "returning: $apkPublicUrl version: $versionCode". PHP_EOL;

        return [$apkPublicUrl, $versionCode];
    }

    /**
     *
     * @param Build $build
     */
    private function checkBuildStatus($build){
        $logger = new Appbuilder_logger("CronController");
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] checkBuildStatus: Check Build of ".$build->jobName(). PHP_EOL;

            $job = $build->job;
            if ($job){
                $jenkins = $this->getJenkins();
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
                                list($build->artifact_url, $build->version_code) = $this->saveBuild($build, $jenkinsBuild);
                                break;
                        }
                    }
                    if (!$build->save()){
                        throw new \Exception("Unable to update Build entry, model errors: ".print_r($build->getFirstErrors(),true), 1450216434);
                    }
                    $log = $this->getlogBuildDetails($build);
                    $log['job id'] = $job->id;
                    $logger->appbuilderWarningLog($log);
                    echo "Job=$job->id, Build=$build->build_number, Status=$build->status, Result=$build->result". PHP_EOL;
                }
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] checkBuildStatus: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = $this->getlogBuildDetails($build);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }

    /**
     * We can only get the build_number until the build has actually started.  So, if there is currently
     * a build running, wait until the next cycle before trying.
     * @param JenkinsJob $job
     * @param array $params
     * @return JenkinsBuild|null
     */
    private function startBuildIfNotBuilding($job, $params = array(), $timeoutSeconds = 60, $checkIntervalSeconds = 2)
    {
        // Note: JenkinsJob::isCurrentlyBuilding doesn't check for getLastBuild return null :-(
        $startTime = time();
        if (!$job->getLastBuild())
        {
            echo "...not built at all, so launch a build". PHP_EOL;
            $job->launch($params);
            $lastBuild = null;
            while ((time() < $startTime + $timeoutSeconds)
                    && !$lastBuild)
            {
                sleep($checkIntervalSeconds);
                $job->refresh();
                $lastBuild = $job->getLastBuild();
            }
        }
        else if (!$job->isCurrentlyBuilding())
        {
            echo "...not building, so launch a build". PHP_EOL;

            $lastNumber = $job->getLastBuild()->getNumber();

            $job->launch($params);

            while ((time() < $startTime + $timeoutSeconds)
                && ($job->getLastBuild()->getNumber() == $lastNumber))
            {
                sleep($checkIntervalSeconds);
                $job->refresh();
            }
        }
        else
        {
            // Currently building so wait for next cycle
            return null;
        }

        $build = $job->getLastBuild();
        echo "...is building now. Returning build ". $build->getNumber() . PHP_EOL;
        return $build;
    }

    /**
     * Try to start a build.  If it starts, then update the database.
     * @param Build $build
     */
    private function tryStartBuild($build)
    {
        $logger = new Appbuilder_logger("CronController");
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] tryStartBuild: Starting Build of ".$build->jobName(). PHP_EOL;

            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($build->jobName());

            if ($jenkinsBuild = $this->startBuildIfNotBuilding($jenkinsJob)){
                $build->build_number = $jenkinsBuild->getNumber();
                echo "[$prefix] Started Build $build->build_number". PHP_EOL;
                $build->status = Build::STATUS_ACTIVE;
                $build->save();
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] tryStartBuild: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = $this->getlogBuildDetails($build);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }

    /**
     * Manage the state of the builds and process the current state
     * until the status is complete.
     */
    public function actionManageBuilds()
    {
        $logger = new Appbuilder_logger("CronController");
        $complete = Build::STATUS_COMPLETED;
        foreach (Build::find()->where("status!='$complete'")->each(50) as $build){
            $job = $build->job;
            echo "cron/manage-builds: Job=$job->id, Build=$build->build_number, Status=$build->status, Result=$build->result". PHP_EOL;
            $logBuildDetails = $this->getlogBuildDetails($build);
            $logger->appbuilderWarningLog($logBuildDetails);
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

    /**
     *
     * @param Release $release
     */
    private function tryStartRelease($release)
    {
        $logger = new Appbuilder_logger("CronController");
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] tryStartRelease: Starting Build of ".$release->jobName()." for Channel ".$release->channel. PHP_EOL;

            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($release->jobName());
            $parameters = array("CHANNEL" => $release->channel, "BUILD_NUMBER" => $release->build->build_number);

            if ($jenkinsBuild = $this->startBuildIfNotBuilding($jenkinsJob, $parameters)){
                $release->build_number = $jenkinsBuild->getNumber();
                echo "[$prefix] Started Build $release->build_number". PHP_EOL;
                $release->status = Release::STATUS_ACTIVE;
                $release->save();
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] tryStartRelease: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = $this->getlogReleaseDetails($release);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }

    /**
     *
     * @param Release $release
     */
    private function checkReleaseStatus($release)
    {
        $logger = new Appbuilder_logger("CronController");
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] Check Build of ".$release->jobName()." for Channel ".$release->channel.PHP_EOL;

            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($release->jobName());
            $jenkinsBuild = $jenkinsJob->getBuild($release->build_number);
            if ($jenkinsBuild){
                $release->result = $jenkinsBuild->getResult();
                if (!$jenkinsBuild->isBuilding()){
                    $release->status = Release::STATUS_COMPLETED;
                    switch($release->result){
                        case JenkinsBuild::FAILURE:
                            $release->error = $jenkins->getBaseUrl().sprintf('job/%s/%s/consoleText', $release->jobName(), $release->build_number);
                            break;
                        case JenkinsBuild::SUCCESS:
                           if ($build = $this->getBuild($release->build_id))
                            {
                                $build->channel = $release->channel;
                                $build->save();
                            }
                            break;
                    }
                }
                if (!$release->save()){
                    throw new \Exception("Unable to update Build entry, model errors: ".print_r($release->getFirstErrors(),true), 1452611606);
                }
                echo "Release=$release->id, Build=$release->build_number, Status=$release->status, Result=$release->result". PHP_EOL;
                $log = $this->getlogReleaseDetails($release);
                $logger->appbuilderWarningLog($log);
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] checkReleaseStatus Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            $logException = $this->getlogReleaseDetails($release);
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }

    /**
     * Manage the state of the releases and process the current state
     * until the status is complete.
     */
    public function actionManageReleases()
    {
        $logger = new Appbuilder_logger("CronController");
        $complete = Release::STATUS_COMPLETED;
        foreach (Release::find()->where("status!='$complete'")->each(50) as $release){
            $build = $release->build;
            $job = $build->job;
            echo "cron/manage-releases: Job=$job->id, Release=$release->build_number, Status=$release->status, Result=$release->result". PHP_EOL;
            $logReleaseDetails = $this->getlogReleaseDetails($release);
            $logger->appbuilderWarningLog($logReleaseDetails);
            switch ($release->status){
                case Release::STATUS_INITIALIZED:
                    $this->tryStartRelease($release);
                    break;
                case Release::STATUS_ACTIVE:
                    $this->checkReleaseStatus($release);
                    break;
            }
        }
    }
    function recurse_copy($src,$dst, $git) {
        $dir = opendir($src);
        if (!file_exists($dst)) {
            echo "mkdir $dst ". PHP_EOL;
            if (mkdir($dst, 0777, true)){
                echo "failed to mkdir $dst ". PHP_EOL;
            }
        }
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                $srcFile = $src .DIRECTORY_SEPARATOR. $file;
                $dstFile = $dst .DIRECTORY_SEPARATOR. $file;
                if ( is_dir($srcFile) ) {
                    recurse_copy($srcFile,$dstFile, $git);
                }
                else {
                    copy($srcFile,$dstFile);
                    $git->add($dstFile);
                }
            }
        }
        closedir($dir);
    }
    /*===============================================  logging ============================================*/
    /**
     *
     * get release details for logging.
     * @param Release $release
     * @return Array
     */
    public function getlogReleaseDetails($release)
    {
        $build = $release->build;
        $job = $build->job;

        $jobName = $build->job->name();
        $log = [
            'jobName' => $jobName,
            'jobId' => $job->id
        ];
        $log['Status'] = $release->status;
        $log['Release'] = $release->build_number;
        $log['Result'] = $release->result;

        return $log;
    }

    /**
     *
     * get build details for logging.
     * @param Build $build
     * @return Array
     */
    public function getlogBuildDetails($build)
    {
        $jobName = $build->job->name();
        $log = [
            'jobName' => $jobName
        ];
        $log['buildId'] = $build->id;
        $log['buildStatus'] = $build->status;
        $log['buildNumber'] = $build->build_number;
        $log['buildResult'] = $build->result;
        $log['buildArtifactUrl'] = $build->artifact_url;

        echo "Job=$jobName, Id=$build->id, Status=$build->status, Number=$build->build_number, "
                    . "Result=$build->result, ArtifactUrl=$build->artifact_url". PHP_EOL;
        return $log;
    }

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

        $jenkins = $this->getJenkins();
        $jenkinsJob = $jenkins->getJob($build->job->name());
        $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
        $buildResult = $jenkinsBuild->getResult();
        $buildArtifact = $this->getApkArtifactUrl($jenkinsBuild);
        $s3Url = S3::getS3Url($build, $buildArtifact);

        $log['jenkins_buildResult'] = $buildResult;
        $log['jenkins_ArtifactUrl'] = $buildArtifactUrl;
        $log['S3: Url'] = $s3Url;

        echo "  Build: Result=$buildResult, Artifact=$buildArtifact" . PHP_EOL
            . "  S3: Url=$s3Url". PHP_EOL;
        return $log;
    }
}
