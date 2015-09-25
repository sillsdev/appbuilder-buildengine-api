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
        // TODO: Create a new build if there hasn't been one already started
        $build = $job->getLatestBuild();
        if (!$build || $build->status == Build::STATUS_COMPLETED){
            $build = new Build();
            $build->job_id = $job->id;
            if(!$build->save()){
                throw new BadRequestHttpException("Failed to create build for new job");
            }
        }
    }
    
    private function updateJenkinsJobs()
    {
        $date = date('Y-m-d H:i:s');
        $jenkins = $this->getJenkins();
        if ($jenkins){
            echo "[$date] Telling Jenkins to regenerate Jobs\n";
            $jenkins->getJob("Job-Wrapper-Seed")->launch();
        }
    }

    public function actionSyncScripts()
    {
        $logMsg = 'cron/sync-scripts - ';
        $date = date('Y-m-d H:i:s');
 
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
                echo "[$date] Updated: $jobName\n";
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
                echo "[$date] Removing: $jobName\n";
                $git->rm($scriptFile);
            }
        }

        if ($git->hasChanges())
        {
            echo "[$date] Changes detected...committing...\n";
            $git->commit('cron update scripts');
            $git->push();
            $this->updateJenkinsJobs();
        }
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
        if ($job->isCurrentlyBuilding()){
            $startWait = time();
            echo "There is a current build ".$job->getLastBuild()->getNumber().". Wait for it to complete.\n";
            while ($job->getLastBuild()->isBuilding()){
                sleep($checkIntervalSeconds);
                echo "...waited ". (time() - $startWait)."\n";
                $job->refresh();
            }
        }

        $lastNumber = $job->getLastBuild()->getNumber();
        $startTime = time();
        $job->launch($params);
        
        while ( time() < ($startTime + $timeoutSeconds))
        {
            sleep($checkIntervalSeconds);
            $job->refresh();

            $build = $job->getLastBuild();
            if ($build->getNumber() > $lastNumber && $build->isBuilding())
            {
                return $build;
            }
        }
    }
    /**
     *
     * @param Build $build
     */
    private function startBuild($build)
    {
        $job = $build->job;
        if ($job){
            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($job->name());
            echo "Starting Build of ".$job->name()."\n";
            
            if ($jenkinsBuild = $this->startNewBuildAndWaitUntilBuilding($jenkinsJob)){
                $build->build_number = $jenkinsBuild->getNumber();
                echo "Started Build $build->build_number\n";
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
