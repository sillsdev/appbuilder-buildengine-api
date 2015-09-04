<?php
namespace console\controllers;

use common\models\Job;

use yii\console\Controller;
use common\helpers\Utils;

use GitWrapper\GitWrapper;

class CronController extends Controller 
{

    public function actionSetGitConfig()
    {
        $logMsg = 'cron/set-git-config - ';
         echo "starting cron/set-git-config. \n";
    
        $userName = \Yii::$app->params['buildEngineGitUserName'];
        $userEmail = \Yii::$app->params['buildEngineGitUserEmail'];
        
        require_once 'vendor/autoload.php';
        $wrapper = new GitWrapper();

        $wrapper->git("config --global user.name \"$userName\"");
        $wrapper->git("config --global user.email $userEmail");
      
    }
    
    public function actionCloneRepo()
    {
        $logMsg = 'cron/clone-repo - ';
        echo "starting cron/clone-repo. \n";
        
        $privateKey = \Yii::$app->params['buildEngineRepoPrivateKey'];
        $repoUrl = \Yii::$app->params['buildEngineRepoUrl'];
        $repoLocalPath =\Yii::$app->params['buildEngineRepoLocalPath'];
        
        echo "Private Key: $privateKey. \n";
        echo "Repo Url: $repoUrl. \n";
        
        $wrapper = new GitWrapper();
        
        $wrapper->setEnvVar('HOME', '/data');
        $wrapper->setPrivateKey($privateKey);
        $git = $wrapper->clone($repoUrl, $repoLocalPath);
    }
    
    public function actionSyncScripts()
    {
        $logMsg = 'cron/sync-scripts - ';
        // TODO: use a view to format all of the current jobs and write out
        // to $repoLocalPath.  check for changes and then commit.
    }
 } 