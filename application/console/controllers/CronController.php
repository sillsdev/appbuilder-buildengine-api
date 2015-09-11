<?php
namespace console\controllers;

use common\models\Job;

use yii\console\Controller;
use common\helpers\Utils;

use GitWrapper\GitWrapper;

class CronController extends Controller 
{
    public function getRepo()
    {
        $privateKey = \Yii::$app->params['buildEngineRepoPrivateKey'];
        $repoUrl = \Yii::$app->params['buildEngineRepoUrl'];
        $repoLocalPath =\Yii::$app->params['buildEngineRepoLocalPath'];

        require_once 'vendor/autoload.php';
        $wrapper = new GitWrapper();

        $wrapper->setEnvVar('HOME', '/data');
        $wrapper->setPrivateKey($privateKey);
        $git = null;
        if (!file_exists($repoLocalPath))
        {
            $git = $wrapper->clone($repoUrl, $repoLocalPath);
        } else {
            $git = $wrapper->init($repoLocalPath);
            $git->pull();
        }

        $userName = \Yii::$app->params['buildEngineGitUserName'];
        $userEmail = \Yii::$app->params['buildEngineGitUserEmail'];

        $git->config('user.name', $userName);
        $git->config('user.email', $userEmail);
        return $git;
    }

    public function actionGetRepo()
    {
        $logMsg = 'cron/get-repo - ';
        echo "starting cron/get-repo. \n";

        $git = $this->getRepo();
    }

    public function doReplacements($subject, $patterns)
    {
        foreach ($patterns as $pattern => $replacement )
        {
            $subject = preg_replace($pattern, $replacement, $subject);
        }
        return $subject;
    }

    public function actionSyncScripts()
    {
        $logMsg = 'cron/sync-scripts - ';

        $repoLocalPath = \Yii::$app->params['buildEngineRepoLocalPath'];
        $scriptDir = \Yii::$app->params['buildEngineRepoScriptDir'];

        // When using Codecommit, the user portion in the url has to be changed
        // to the User associated with the public key in AWS.
        $buildAgentCodecommitSshUser = \Yii::$app->params['buildEngineBuildAgentCodecommitGitSshUser'];
        $gitSubstPatterns = [ '/([0-9A-Za-z]*)@git-codecommit/' => "$buildAgentCodecommitSshUser@git-codecommit" ];

        $git = $this->getRepo();

        foreach (Job::find()->each(50) as $job)
        {
            $jobName = $job->app_id . "_" . $job->request_id;
            $gitUrl = $this->doReplacements($job->git_url, $gitSubstPatterns);

            $script = $this->renderPartial("scripts/$job->app_id", [
                'jobName' => $jobName,
                'gitUrl' => $gitUrl,
            ]);

            $file = $repoLocalPath . DIRECTORY_SEPARATOR . $scriptDir . DIRECTORY_SEPARATOR . $jobName . ".groovy";
            $handle = fopen($file, "w");
            fwrite($handle, $script);
            fclose($handle);
            $git->add($file);
        }

        if ($git->hasChanges())
        {
            $git->commit('cron update scripts');
            $git->push();
        }
    }
 }
