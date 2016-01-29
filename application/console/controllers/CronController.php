<?php
namespace console\controllers;

use common\models\Job;
use common\models\Build;
use common\models\Release;
use common\components\S3;

use yii\console\Controller;
use common\helpers\Utils;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;

use GitWrapper\GitWrapper;
use JenkinsApi\Jenkins;
use JenkinsApi\Item\Build as JenkinsBuild;
use JenkinsApi\Item\Job as JenkinsJob;

use Yii;
use yii\log\Logger;

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
            echo "[$prefix] Telling Jenkins to regenerate Jobs\n";
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
                echo "[$prefix] Updated: $buildJobName\n";
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
                echo "[$prefix] Removing: $jobName\n";
                $git->rm($scriptFile);
            }
        }

        if ($git->hasChanges())
        {
            echo "[$prefix] Changes detected...committing...\n";
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
            echo "getArtifactURL: No artifact matching ".$artifactPattern."\n";
            return null;
        }
        $relativePath = $artifact->relativePath;
        $baseUrl = $jenkinsBuild->getJenkins()->getBaseUrl();
        $buildUrl = $jenkinsBuild->getBuildUrl();
        $pieces = explode("job", $buildUrl);
        return $baseUrl."job".$pieces[1]."artifact/".$relativePath;
    }

    /**
     * Get Configuration (Dev only)
     */
    public function actionGetConfig()
    {
        $prefix = $this->getPrefix();
        echo "[$prefix] Get Configuration...\n";

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

        echo "Repo:\n  URL:$repoUrl\n  Branch:$repoBranch\n  Path:$repoLocalPath\n  Scripts:$scriptDir\n  Key:$privateKey\n";
        echo "Jenkins:\n  BuildEngineJenkinsMasterUrl: $jenkinsUrl\n  Jenkins.baseUrl: $jenkinsBaseUrl\n";
        echo "Git:\n  Name:$userName\n  Email:$userEmail\n";
        echo "Artifacts:\n  UrlBase:$artifactUrlBase\n";
    }
    /**
     * Return all the builds. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    public function actionGetBuilds()
    {
        $prefix = $this->getPrefix();
        echo "[$prefix] All Builds...\n";
        foreach (Build::find()->each(50) as $build){
            $jobName = $build->job->name();
            echo "Job=$jobName, Id=$build->id, Status=$build->status, Number=$build->build_number, Result=$build->result, ArtifactUrl=$build->artifact_url\n";
            $logBuildDetails = $this->getlogBuildDetails($build);
            $this->outputToLogger($logBuildDetails, Logger::LEVEL_WARNING, 'debug-Cron-actionGetBuilds');
            try {
                if ($build->build_number > 0) {
                    $logJenkinsS3 = $this->getlogJenkinsS3Details($build);
                    $this->outputToLogger($logJenkinsS3, Logger::LEVEL_WARNING, 'debug-Cron-actionGetBuilds');
                }
            } catch (\Exception $e) {
                $log = [
                    'logType' => 'Exception',
                    'problem' => 'build->build_number is not > 0',
                    'jobName' => $jobName
                        ];
                $this->outputToLogger($log, Logger::LEVEL_WARNING, 'debug-Cron-actionGetBuilds');
                echo "... Not found \n";
            }
        }
    }
    /**
     * Return the builds that have not completed. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    public function actionGetBuildsRemaining()
    {
        $jenkins = $this->getJenkins();
        $prefix = $this->getPrefix();
        echo "[$prefix] Remaining Builds...\n";
        $complete = Build::STATUS_COMPLETED;
        foreach (Build::find()->where("status!='$complete'")->each(50) as $build){
            $jobName = $build->job->name();
            try {
                if ($build->build_number > 0) {
                    $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                    $buildResult = $jenkinsBuild->getResult();
                    $buildArtifact = $this->getApkArtifactUrl($jenkinsBuild);
                    $s3Url = S3::getS3Url($build,$buildArtifact);
                    echo "Job=$jobName, Number=$build->build_number, Status=$build->status\n"
                        . "  Build: Result=$buildResult, Artifact=$buildArtifact\n"
                        . "  S3: Url=$s3Url\n";
                }
            } catch (\Exception $e) {
                echo "Job=$jobName, Number=$build->build_number, Status=$build->status\n....Not found \n";
            }

        }
    }
    /**
     * Get completed build information. (Dev only)
     * Note: This should only be used during development for diagnosis.
     */
    public function actionGetBuildsCompleted()
    {
        $jenkins = $this->getJenkins();
        foreach (Build::find()->where([
            'status' => Build::STATUS_COMPLETED,
            'result' => JenkinsBuild::SUCCESS])->each(50) as $build){
                $jobName = $build->job->name();
                try {
                    $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                    $artifactUrl = $this->getApkArtifactUrl($jenkinsBuild);

                    echo "Job=$jobName, BuildNumber=$build->build_number, Url=$artifactUrl\n";
                } catch (\Exception $e) {
                    echo "Job=$jobName, BuildNumber=$build->build_number \n....Not found \n";
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
        $jenkins = $this->getJenkins();
        foreach (Build::find()->each(50) as $build){
            if ($build->status == Build::STATUS_COMPLETED
                && $build->result == JenkinsBuild::SUCCESS)
            {
                $jobName = $build->job->name();
                $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                echo "Attempting to save Build: Job=$jobName, BuildNumber=$build->build_number\n";
                $this->saveBuild($build, $jenkinsBuild);
            }
        }
    }

    /**
     * Remove expired builds from S3
    */
    public function actionRemoveExpiredBuilds()
    {
        $prefix = $this->getPrefix();
        echo "[$prefix] actionRemoveExpiredBuilds: Started\n";
        foreach (Build::find()->where([
            'status' => Build::STATUS_EXPIRED])->each(50) as $build){
            if ($build->artifact_url != null) {
                echo "...Remove expired job $build->job_id id $build->id \n";
                $this-removeS3Artifacts($build);
                $build->clearArtifactUrl();
            }
        }
        echo "[$prefix] actionRemoveExpiredBuilds: Conpleted\n";

    }
    private function getBuild($id)
    {
        $build = Build::findOne(['id' => $id]);
        if (!$build){
            echo "Build not found \n";
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
        $artifactUrl =  $this->getApkArtifactUrl($jenkinsBuild);
        $versionCodeArtifactUrl = $this->getVersionCodeArtifactUrl($jenkinsBuild);
        list($apkPublicUrl, $versionCode) = S3::saveBuildToS3($build, $artifactUrl, $versionCodeArtifactUrl);

        echo "returning: $apkPublicUrl version: $versionCode\n";

        return [$apkPublicUrl, $versionCode];
    }

    /**
     *
     * @param Build $build
     */
    private function checkBuildStatus($build){
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] checkBuildStatus: Check Build of ".$build->jobName()."\n";

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
                    echo "Job=$job->id, Build=$build->build_number, Status=$build->status, Result=$build->result\n";
                }
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] checkBuildStatus: Exception:\n" . (string)$e . "\n";
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
            echo "...not built at all, so launch a build\n";
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
            echo "...not building, so launch a build\n";

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
        echo "...is building now. Returning build ". $build->getNumber() . "\n";
        return $build;
    }

    /**
     * Try to start a build.  If it starts, then update the database.
     * @param Build $build
     */
    private function tryStartBuild($build)
    {
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] tryStartBuild: Starting Build of ".$build->jobName()."\n";

            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($build->jobName());

            if ($jenkinsBuild = $this->startBuildIfNotBuilding($jenkinsJob)){
                $build->build_number = $jenkinsBuild->getNumber();
                echo "[$prefix] Started Build $build->build_number\n";
                $build->status = Build::STATUS_ACTIVE;
                $build->save();
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] tryStartBuild: Exception:\n" . (string)$e . "\n";
        }
    }

    /**
     * Manage the state of the builds and process the current state
     * until the status is complete.
     */
    public function actionManageBuilds()
    {
        $complete = Build::STATUS_COMPLETED;
        foreach (Build::find()->where("status!='$complete'")->each(50) as $build){
            $job = $build->job;
            echo "cron/manage-builds: Job=$job->id, Build=$build->build_number, Status=$build->status, Result=$build->result\n";
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
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] tryStartRelease: Starting Build of ".$release->jobName()." for Channel ".$release->channel."\n";

            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($release->jobName());
            $parameters = array("CHANNEL" => $release->channel, "BUILD_NUMBER" => $release->build->build_number);

            if ($jenkinsBuild = $this->startBuildIfNotBuilding($jenkinsJob, $parameters)){
                $release->build_number = $jenkinsBuild->getNumber();
                echo "[$prefix] Started Build $release->build_number\n";
                $release->status = Release::STATUS_ACTIVE;
                $release->save();
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] tryStartRelease: Exception:\n" . (string)$e . "\n";
        }
    }

    /**
     *
     * @param Release $release
     */
    private function checkReleaseStatus($release)
    {
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] Check Build of ".$release->jobName()." for Channel ".$release->channel."\n";

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
                echo "Release=$release->id, Build=$release->build_number, Status=$release->status, Result=$release->result\n";
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] checkReleaseStatus Exception:\n" . (string)$e . "\n";
            echo "Exception: " . $e->getMessage() . "\n";
        }
    }

    /**
     * Manage the state of the releases and process the current state
     * until the status is complete.
     */
    public function actionManageReleases()
    {
        $complete = Release::STATUS_COMPLETED;
        foreach (Release::find()->where("status!='$complete'")->each(50) as $release){
            $build = $release->build;
            $job = $build->job;
            echo "cron/manage-releases: Job=$job->id, Release=$release->build_number, Status=$release->status, Result=$release->result\n";
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

    /*===============================================  logging ============================================*/
    /**
      *
      * Creates a log to be submitted to logentries.com
      */
    public function outputToLogger($log, $level, $category)
    {
        $jenkinsUrl = \Yii::$app->params['buildEngineJenkinsMasterUrl'];
        $prefix = $this->getPrefix();
        $logPrefix = [
            'date' => $prefix,
            'jenkinsUrl' => $jenkinsUrl
        ];
        $mergedLog = array_merge($logPrefix, $log);
        \Yii::getLogger()->log($mergedLog, $level, $category);
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

        $job = $build->job;
        $log['job_id'] = $job->id;
        $log['request_id'] = $job->request_id;

            echo "Job=$jobName, Id=$build->id, Status=$build->status, Number=$build->build_number, "
                    . "Result=$build->result, ArtifactUrl=$build->artifact_url\n";
            echo "job_id=$job->id, request_id=$job->request_id\n";

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

        echo "  Build: Result=$buildResult, Artifact=$buildArtifact\n"
            . "  S3: Url=$s3Url\n";
        return $log;
    }
}
