<?php
namespace console\controllers;

use common\models\Job;
use common\models\Build;
use common\models\Release;

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
    private function getArtifactUrl($jenkinsBuild)
    {
        $artifact = $jenkinsBuild->get("artifacts")[0];

        $relativePath = $artifact->relativePath;
        $baseUrl = $jenkinsBuild->getJenkins()->getBaseUrl();
        $buildUrl = $jenkinsBuild->getBuildUrl();
        $pieces = explode("job", $buildUrl);
        return $baseUrl."job".$pieces[1]."artifact/".$relativePath;
    }

    /**
     * Get Confiuration (Dev only)
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
        $jenkins = $this->getJenkins();
        $prefix = $this->getPrefix();
        echo "[$prefix] All Builds...\n";
        $complete = Build::STATUS_COMPLETED;
        foreach (Build::find()->each(50) as $build){
            $jobName = $build->job->name();
            echo "Job=$jobName, Id=$build->id, Status=$build->status, Number=$build->build_number, Result=$build->result, ArtifactUrl=$build->artifact_url\n";
            if ($build->build_number > 0) {
                $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                $buildResult = $jenkinsBuild->getResult();
                $buildArtifact = $this->getArtifactUrl($jenkinsBuild);
                $s3Url = $this->getS3Url($build, $jenkinsBuild);
                echo "  Build: Result=$buildResult, Artifact=$buildArtifact\n"
                    . "  S3: Url=$s3Url\n";
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
            if ($build->build_number > 0) {
                $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                $buildResult = $jenkinsBuild->getResult();
                $buildArtifact = $this->getArtifactUrl($jenkinsBuild);
                $s3Url = $this->getS3Url($build, $jenkinsBuild);
                echo "Job=$jobName, Number=$build->build_number, Status=$build->status\n"
                    . "  Build: Result=$buildResult, Artifact=$buildArtifact\n"
                    . "  S3: Url=$s3Url\n";
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
                $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                $artifactUrl = $this->getArtifactUrl($jenkinsBuild);

                echo "Job=$jobName, BuildNumber=$build->build_number, Url=$artifactUrl\n";
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
     * Configure and get the S3 Client
     * @return \Aws\S3\S3Client
     */
    private function getS3Client()
    {
        $client = new \Aws\S3\S3Client([
            'region' => 'us-west-2',
            'version' => '2006-03-01'
            ]);
        $client->registerStreamWrapper();
        return $client;
    }

    /**
     * Get the S3 Url to use to archive a build
     * @param Build $build
     * @param JenkinsBuild $jenkinsBuild
     */
    private function getS3Url($build, $jenkinsBuild)
    {

        $artifactUrl = $this->getArtifactUrl($jenkinsBuild);
        $job = $build->job;
        return $this->getArtifactUrlBase()."/jobs/".$job->name()."/".$build->build_number."/".basename($artifactUrl);
    }

    /**
     * Get the S3 Bucket and Key to use to archive a build
     * @param string s3Url
     * @return [string,string] Bucket, Key
     */
    private function getS3BucketKey($s3Url)
    {
        $pattern = '/s3:\/\/([^\/]*)\/(.*)$/';
        if (preg_match($pattern, $s3Url, $matches)){
            $bucket = $matches[1];
            $key = $matches[2];
            return [$bucket, $key];
        }

        throw new ServerErrorHttpException("Failed to match $s3Url", 1444051300);
    }

    /**
     * Save the build to S3.
     * @param Build $build
     * @param JenkinsBuild $jenkinBuild
     */
    private function saveBuild($build, $jenkinsBuild)
    {
        $artifactUrl =  $this->getArtifactUrl($jenkinsBuild);
        $client = $this->getS3Client();

        $job = $build->job;
        $s3Url = $this->getS3Url($build, $jenkinsBuild);
        list ($s3bucket, $s3key) = $this->getS3BucketKey($s3Url);
        echo "..copy:\n.... $artifactUrl\n.... $s3bucket $s3key\n";

        $apk = file_get_contents($artifactUrl);

        $client->putObject([
            'Bucket' => $s3bucket,
            'Key' => $s3key,
            'Body' => $apk,
            'ACL' => 'public-read'
        ]);

        $s3PublicUrl = $client->getObjectUrl($s3bucket, $s3key);
        echo "returning: $s3PublicUrl\n";
        return $s3PublicUrl;
    }

    /**
     *
     * @param Build $build
     */
    private function checkBuildStatus($build){
        try {
            $job = $build->job;
            if ($job){
                $jenkins = $this->getJenkins();
                $jenkinsJob = $jenkins->getJob($job->name());
                $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
                if ($jenkinsBuild){
                    $build->result = $jenkinsBuild->getResult();
                    if (!$jenkinsBuild->isBuilding()){
                        $build->status = Build::STATUS_COMPLETED;
                        if ($build->result == JenkinsBuild::SUCCESS){
                            $build->artifact_url = $this->saveBuild($build, $jenkinsBuild);
                        }
                    }
                    if (!$build->save()){
                        throw new \Exception("Unable to update Build entry, model errors: ".print_r($build->getFirstErrors(),true), 1450216434);
                    }
                    echo "Job=$job->id, Build=$build->build_number, Status=$build->status, Result=$build->result\n";
                }
            }
        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . "\n";
            $build->status = Build::STATUS_COMPLETED;
            $build->result = JenkinsBuild::SUCCESS;
            $build->error = $e->getMessage();
            $build->save();
        }
    }

    /**
     *
     * @param JenkinsJob $job
     * @param array $parameters
     * @param int $timeoutSeconds
     * @param int $checkIntervalSeconds
     */
    private function startNewBuildAndWaitUntilBuilding($job, $params = array(), $timeoutSeconds = 60, $checkIntervalSeconds = 2)
    {
        // If there is currently a build running, wait for it to finish.
        echo "...checking if job is running\n";
        $lastBuild = $job->getLastBuild();
        if ($lastBuild && $lastBuild->isBuilding()){
            $startWait = time();
            echo "There is a current build ".$job->getLastBuild()->getNumber().". Wait for it to complete.\n";
            while ($job->getLastBuild()->isBuilding()){
                sleep($checkIntervalSeconds);
                echo "...waited ". (time() - $startWait)."\n";
                $job->refresh();
            }
        }

        echo "...checking last build\n";
        $lastNumber = ($lastBuild ? $lastBuild->getNumber() : 0);
        $startTime = time();
        echo "...lastNumber=$lastNumber, startTime=$startTime\n";
        $job->launch($params);

        while ( time() < ($startTime + $timeoutSeconds))
        {
            sleep($checkIntervalSeconds);
            $job->refresh();

            $build = $job->getLastBuild();
            if ($build){
                echo "...build=".$build->getNumber().". Is building?\n";
                if ($build->getNumber() > $lastNumber && $build->isBuilding())
                {
                    echo "...is building.  Returning build.\n";
                    return $build;
                }
            }
        }
    }
    /**
     *
     * @param Build $build
     */
    private function startBuild($build)
    {
        try
        {
            $prefix = $this->getPrefix();
            echo "[$prefix] Starting Build of ".$build->jobName()."\n";

            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($build->jobName());

            if ($jenkinsBuild = $this->startNewBuildAndWaitUntilBuilding($jenkinsJob)){
                $build->build_number = $jenkinsBuild->getNumber();
                echo "[$prefix] Started Build $build->build_number\n";
                $build->status = Build::STATUS_ACTIVE;
                $build->save();
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] Exception: " . $e->getMessage() . "\n";
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
                    $this->startBuild($build);
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
    private function startRelease($release)
    {
        try {
            $prefix = $this->getPrefix();
            echo "[$prefix] Starting Build of ".$release->jobName()." for Channel ".$release->channel."\n";
            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($release->jobName());

            if ($jenkinsBuild = $this->startNewBuildAndWaitUntilBuilding($jenkinsJob, array("CHANNEL" => $release->channel))){
                $release->build_number = $jenkinsBuild->getNumber();
                echo "[$prefix] Started Build $release->build_number\n";
                $release->status = Release::STATUS_ACTIVE;
                $release->save();
            }
        } catch (\Exception $e) {
            $prefix = $this->getPrefix();
            echo "[$prefix] Exception: " . $e->getMessage() . "\n";
        }
    }

    /**
     *
     * @param Release $release
     */
    private function checkReleaseStatus($release)
    {
        try {
            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($release->jobName());
            $jenkinsBuild = $jenkinsJob->getBuild($release->build_number);
            if ($jenkinsBuild){
                $release->result = $jenkinsBuild->getResult();
                if (!$jenkinsBuild->isBuilding()){
                    $release->status = Build::STATUS_COMPLETED;
                }
                if (!$release->save()){
                    throw new \Exception("Unable to update Build entry, model errors: ".print_r($release->getFirstErrors(),true), 1452611606);
                }
                echo "Release=$release->id, Build=$release->build_number, Status=$release->status, Result=$release->result\n";
            }
        } catch (\Exception $e) {
            echo "Exception: " . $e->getMessage() . "\n";
            $release->status = Build::STATUS_COMPLETED;
            $release->result = JenkinsBuild::SUCCESS;
            $release->error = $e->getMessage();
            $release->save();
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
                    $this->startRelease($release);
                    break;
                case Release::STATUS_ACTIVE:
                    $this->checkReleaseStatus($release);
                    break;
            }
        }
    }
}
