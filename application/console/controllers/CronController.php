<?php
namespace console\controllers;

use common\models\Job;
use common\models\Build;

use yii\console\Controller;
use common\helpers\Utils;
use yii\web\BadRequestHttpException;

use GitWrapper\GitWrapper;
use JenkinsApi\Jenkins;


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
        $repoLocalPath =\Yii::$app->params['buildEngineRepoLocalPath'];

        require_once __DIR__ . '/../../vendor/autoload.php';
        $wrapper = new GitWrapper();

        $wrapper->setEnvVar('HOME', '/data');
        $wrapper->setPrivateKey($privateKey);
        $git = null;
        if (!file_exists($repoLocalPath))
        {
            $git = $wrapper->clone($repoUrl, $repoLocalPath);
        } else {
            $git = $wrapper->init($repoLocalPath);
            $git->fetchAll();
            $git->reset("--hard", "origin/master");
        }

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


    public function actionGetRepo()
    {
        $logMsg = 'cron/get-repo - ';
        echo "starting cron/get-repo. \n";

        $git = $this->getRepo();
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
     *
     * @param Job $job
     * @throws BadRequestHttpException
     */
    private function createBuild($job)
    {
            if(!$job->createBuild()){
                throw new BadRequestHttpException("Failed to create build for new job");
            }
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

    public function actionSyncScripts()
    {
        $logMsg = 'cron/sync-scripts - ';
        $prefix = $this->getPrefix();
 
        $repoLocalPath = \Yii::$app->params['buildEngineRepoLocalPath'];
        $scriptDir = \Yii::$app->params['buildEngineRepoScriptDir'];

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
            $jobName = $job->name();
            $gitUrl = $this->doReplacements($job->git_url, $gitSubstPatterns);
            $artifactUrlBase = $job->artifact_url_base;
            
            $script = $this->renderPartial("scripts/$job->app_id", [
                'publisherName' => $publisherName,
                'jobName' => $jobName,
                'gitUrl' => $gitUrl,
                'artifactUrlBase' => $artifactUrlBase,
            ]);

            $file = $localScriptDir . DIRECTORY_SEPARATOR . $jobName . ".groovy";
            $handle = fopen($file, "w");
            fwrite($handle, $script);
            fclose($handle);
            if ($git->getStatus($file))
            {
                echo "[$prefix] Updated: $jobName\n";
                $git->add($file);
                $this->createBuild($job);
            }

            $jobs[$jobName] = 1;
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
     *
     * @param \JenkinsApi\Item\Build $jenkinsBuild
     * @return string
     */
    private function getArtifactUrl($jenkinsBuild)
    {
        $artifact = $jenkinsBuild->get("artifacts")[0];

        $relativePath = $artifact->relativePath;
        return $jenkinsBuild->getBuildUrl()."artifact/$relativePath";
    }

    public function actionGetBuildInfo()
    {
        $jenkins = $this->getJenkins();
        foreach (Build::find()->each(50) as $build){
            if ($build->status == Build::STATUS_COMPLETED
                && $build->build_result == \JenkinsApi\Item\Build::SUCCESS)
            {
                $jobName = $build->job->name();
                $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                $artifactUrl = $this->getArtifactUrl($jenkinsBuild);

                echo "Job=$jobName, BuildNumber=$build->build_number, Url=$artifactUrl\n";
            }
        }
    }

	public function actionUploadBuilds()
    {
        $jenkins = $this->getJenkins();
        foreach (Build::find()->each(50) as $build){
            if ($build->status == Build::STATUS_COMPLETED
                && $build->build_result == \JenkinsApi\Item\Build::SUCCESS)
            {
                $jobName = $build->job->name();
                $jenkinsBuild = $jenkins->getBuild($jobName, $build->build_number);
                echo "Attempting to save Build: Job=$jobName, BuildNumber=$build->build_number\n";
                $this->saveBuild($build, $jenkinsBuild);
            }
        }        
    }

    
    /**
     *
     * @return \Aws\S3\S3Client
     */
    private function getS3Client()
    {
        $client = new \Aws\S3\S3Client([
            'region' => 'us-east-1',
            'version' => '2006-03-01'
            ]);
        $client->registerStreamWrapper();
        return $client;
    }

    /**
     *
     * @param Build $build
     * @param \JenkinsApi\Item\Build $jenkinBuild
     */
    private function saveBuild($build, $jenkinsBuild)
    {
        $artifactUrl =  $this->getArtifactUrl($jenkinsBuild);
        $client = $this->getS3Client();

        $job = $build->job;
        $s3Url = $job->artifact_url_base."/jobs/".$job->name()."/$build->build_number/".basename($artifactUrl);
        echo "..copy:\n.... $artifactUrl\n.... $s3Url\n";

        $apk = file_get_contents($artifactUrl);
        $client->putObject([
            'Bucket' => "gtis-appbuilder",
            'Key' => "development/jobs/".$job->name()."/$build->build_number/".basename($artifactUrl),
            'Body' => $apk,
            'ACL' => 'public-read'
        ]);
    }

    /**
     *
     * @param Build $build
     */
    private function checkBuildStatus($build){
        $job = $build->job;
        if ($job){
            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($job->name());
            $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
            if ($jenkinsBuild){
                $build->build_result = $jenkinsBuild->getResult();
                if (!$jenkinsBuild->isBuilding()){
                    $build->status = Build::STATUS_COMPLETED;
                    if ($build->build_result == \JenkinsApi\Item\Build::SUCCESS){
                        $this->saveBuild($build, $jenkinsBuild);
                    }
                }
                $build->save();
                echo "Job=$job->id, Build=$build->build_number, Result=$build->build_result\n";
            }
        }
    }
    
    /**
     *
     * @param \JenkinsApi\Item\Job $job
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
        $prefix = $this->getPrefix();
        $job = $build->job;
        if ($job){
            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($job->name());
            echo "[$prefix] Starting Build of ".$job->name()."\n";
            
            if ($jenkinsBuild = $this->startNewBuildAndWaitUntilBuilding($jenkinsJob)){
                $build->build_number = $jenkinsBuild->getNumber();
                echo "[$prefix] Started Build $build->build_number\n";
                $build->status = Build::STATUS_ACTIVE;
                $build->save();
            }
        }           
    }
    
    public function actionManageBuilds()
    {
        foreach (Build::find()->each(50) as $build){
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
 }
