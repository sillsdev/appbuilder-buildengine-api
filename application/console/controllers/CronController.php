<?php
namespace console\controllers;

use common\models\Job;
use common\models\Build;

use yii\console\Controller;
use common\helpers\Utils;
use yii\web\BadRequestHttpException;

use GitWrapper\GitWrapper;
use JenkinsKhan\Jenkins;

class CronController extends Controller 
{
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

    private function doReplacements($subject, $patterns)
    {
        foreach ($patterns as $pattern => $replacement )
        {
            $subject = preg_replace($pattern, $replacement, $subject);
        }
        return $subject;
    }

    private function createBuild($jobId)
    {
        $build = new Build();
        $build->job_id = $jobId;
        if(!$build->save()){
            throw new BadRequestHttpException("Failed to create build for new job");
        }
    }
    
    private function updateJenkinsJobs()
    {
        $jenkins = $this->getJenkins();
        if ($jenkins){
            $jenkins->launchJob("Job-Wrapper-Seed");
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
                $this->createBuild($job->id);
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
    
    
    private function checkBuildStatus($build){
         $job = Job::findById($build->job_id);
         if ($job){
            $jenkins = $this->getJenkins();
            $jenkinsJob = $jenkins->getJob($job->name());
            foreach ($jenkinsJob->getBuilds() as $jenkinsBuild){
                $build->build_number = $jenkinsBuild->getNumber();
                $build->build_result = $jenkinsBuild->getResult();
                if ($build->build_result == "SUCCESS"){
                    //$build->artifact_url = "$job->artifact_base_url/$job->name/$build->build_number/";
                }
            }
         }
    }
    
    private function startBuild($build)
    {
        $job = Job::findById($build->job_id);
        if ($job){
            $jenkins = $this->getJenkins();
            $jenkins->launchJob($job->name());
            
            $job->status = Build::STATUS_REQUEST_IN_PROGRESS;
            $job->save();
        }
    }
    
    public function actionManageBuilds()
    {
        foreach (Build::find()->each(50) as $build){
            switch ($build->status){
                case Build::STATUS_INITIALIZED:
                    $this->startBuild($build);
                    break;
            }
        }
        
    }
 }
