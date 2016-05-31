<?php
namespace console\components;

use GitWrapper\GitWrapper;

use common\models\Job;
use common\models\Build;
use common\models\OperationQueue;
use common\components\JenkinsUtils;

use common\helpers\Utils;
use yii\web\ServerErrorHttpException;

class SyncScriptsAction
{
    private $cronController;
    private $git;
    private $prefix;
    private $fileUtil;

    public function __construct($cronController)
    {
        $this->cronController = $cronController;
        $this->fileUtil = \Yii::$container->get('fileUtils');
    }

    public function __destruct()
    {
        $this->cronController = null;
        $this->git = null;
    }
    public function performAction()
    {
        $this->prefix = Utils::getPrefix();

        $this->git = $this->getRepo();
        $viewPath = $this->cronController->getViewPath();
        $repoLocalPath = \Yii::$app->params['buildEngineRepoLocalPath'];
        $scriptDir = \Yii::$app->params['buildEngineRepoScriptDir'];
        $appBuilderGitSshUser = \Yii::$app->params['appBuilderGitSshUser'];

        // When using Codecommit, the user portion in the url has to be changed
        // to the User associated with the AppBuilder SSH Key
        $gitSubstPatterns = [ '/ssh:\/\/([0-9A-Za-z]*)@git-codecommit/' => "ssh://$appBuilderGitSshUser@git-codecommit",
                              '/ssh:\/\/git-codecommit/' => "ssh://$appBuilderGitSshUser@git-codecommit" ];

        $jobs = [];
        // TODO: Apps should be pulled from a database?
        $apps = ['scriptureappbuilder' => 1];
        $localScriptDir = $repoLocalPath . DIRECTORY_SEPARATOR . $scriptDir;
        $dataScriptDir = $viewPath.DIRECTORY_SEPARATOR."scripts";
        $utilitiesSourceDir = $dataScriptDir.DIRECTORY_SEPARATOR."utilities";
        $utilitiesDestDir = $localScriptDir . DIRECTORY_SEPARATOR . "utilities";
        $changesString = "";
        $totalAdded = 0;
        $totalUpdated = 0;
        $totalRemoved = 0;
        $this->recurse_copy($utilitiesSourceDir, $utilitiesDestDir);
        foreach (array_keys($apps) as $app) {
            $appSourceDir = $dataScriptDir.DIRECTORY_SEPARATOR.$app;
            $appDestDir = $localScriptDir . DIRECTORY_SEPARATOR .$app;
            $this->recurse_copy($appSourceDir, $appDestDir);
        }
        foreach (Job::find()->each(50) as $job)
        {
            list($updatesString, $added, $updated) = $this->createBuildScript($job, $gitSubstPatterns, $localScriptDir);
            $changesString = $changesString . $updatesString;
            $totalAdded = $totalAdded + $added;
            $totalUpdated = $totalUpdated + $updated;
            list($updatesString2, $added2, $updated2) = $this->createPublishScript($job, $gitSubstPatterns, $localScriptDir);
            $changesString = $changesString . $updatesString2;
            $totalAdded = $totalAdded + $added2;
            $totalUpdated = $totalUpdated + $updated2;

            $jobs[$job->name()] = 1;
        }

        // Remove Scripts that are not in the database
        $globFileName = "*_*.groovy";
        foreach (glob($localScriptDir . DIRECTORY_SEPARATOR .  $globFileName) as $scriptFile)
        {
            list($removedString, $removed) = $this->removeScriptIfNoJobRecord($scriptFile, $jobs);
            $changesString = $changesString . $removedString;
            $totalRemoved = $totalRemoved + $removed;
        }
        $commitString = "cron add=" . $totalAdded . " update =" . $totalUpdated . " delete=" . $totalRemoved . PHP_EOL;
        $commitString = $commitString . $changesString;
        echo "[$this->prefix] $commitString";
        $this->applyUpdates($commitString);
    }
    private function recurse_copy($src,$dst) {
        $dir = $this->fileUtil->opendir($src);
        if (!$this->fileUtil->file_exists($dst)) {
            if ($this->fileUtil->mkdir($dst, 0777, true)){
                echo "failed to mkdir $dst ". PHP_EOL;
            }
        }
        while(false !== ( $file = $this->fileUtil->readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                $srcFile = $src .DIRECTORY_SEPARATOR. $file;
                $dstFile = $dst .DIRECTORY_SEPARATOR. $file;
                if ( is_dir($srcFile) ) {
                    $this->recurse_copy($srcFile,$dstFile);
                }
                else {
                    $this->fileUtil->copy($srcFile,$dstFile);
                    $this->git->add($dstFile);
                }
            }
        }
        $this->fileUtil->closedir($dir);
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

    private function applyUpdates($commitString)
    {
        if ($this->git->hasChanges())
        {
            echo "[$this->prefix] Changes detected...committing..." . PHP_EOL;
            $this->git->commit($commitString);
            $this->git->push();
            $count = Job::recordCount();
            if ($count > 0) {
                $task = OperationQueue::UPDATEJOBS;
                OperationQueue::findOrCreate($task, null, null);
            }
        }
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
    /**
     * This function deletes the script file if the job record has been removed
     * from the job table in the database.
     * 
     * @param String $scriptFile - Name of current scriptfile
     * @param Array $jobs - All of the jobs in the database
     * @return String, Int - String for cron commit and number of records removed
     */
    private function removeScriptIfNoJobRecord($scriptFile, $jobs)
    {
        $removed = 0;
        $retString = "";
        $fileName = basename($scriptFile, ".groovy");

        list($type, $jobName) = explode("_", $fileName, 2);
        if (!array_key_exists($jobName, $jobs))
        {
            echo "[$this->prefix] Removing: $fileName" . PHP_EOL;
            $this->git->rm($scriptFile);
            $removed++;
            $retString = $retString."remove: ".$fileName.PHP_EOL;
        }

        return[$retString, $removed];
    }
    private function createBuildScript($job, $gitSubstPatterns, $localScriptDir)
    {
        return $this->createJobScripts($job, $gitSubstPatterns, $localScriptDir, "build");
    }
    private function createPublishScript($job, $gitSubstPatterns, $localScriptDir)
    {
        return $this->createJobScripts($job, $gitSubstPatterns, $localScriptDir, "publish");
    }

    /**
     * @param Job $job
     * @param String $gitSubstPatterns
     * @param String $localScriptDir
     * @param String $type
     * @return array
     */
    private function createJobScripts($job, $gitSubstPatterns, $localScriptDir, $type)
    {
        $added = 0;
        $updated = 0;
        $retString = "";
        $artifactUrlBase = JenkinsUtils::getArtifactUrlBase();
        $publisherName = $job->publisher_id;
        $jobName = $job->name();
        $buildJobName = $job->nameForBuild();
        $publishJobName = $job->nameForPublish();
        $gitUrl = $this->doReplacements($job->git_url, $gitSubstPatterns);

        $script = $this->cronController->renderPartial("scripts/$job->app_id"."_".$type, [
            'publisherName' => $publisherName,
            'buildJobName' => $buildJobName,
            'publishJobName' => $publishJobName,
            'gitUrl' => $gitUrl,
            'artifactUrlBase' => $artifactUrlBase,
        ]);

        $fileName = $type . "_" . $jobName . ".groovy";
        $filePath = $localScriptDir . DIRECTORY_SEPARATOR . $fileName;
        $fileExists = $this->fileUtil->file_exists($filePath);
        $handle = $this->fileUtil->fopen($filePath, "w");
        $this->fileUtil->fwrite($handle, $script);
        $this->fileUtil->fclose($handle);
        if ($this->git->getStatus($filePath))
        {
            if ($fileExists) {
                echo "[$this->prefix] Updated:" . $fileName . PHP_EOL;
                $retString = $retString."update: ".$fileName . PHP_EOL;
                $updated++;
            } else {
                echo "[$this->prefix] Added: " . $fileName . PHP_EOL;
                $retString = $retString."add: ".$fileName . PHP_EOL;
                $added++;
            }
            $this->git->add($filePath);
        }
        return [$retString, $added, $updated];
    }
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

        // Verify buildEngineRepoUrl is a SSH Url
        if (is_null($repoUrl) || !preg_match('/^ssh:\/\//', $repoUrl)) {
            throw new ServerErrorHttpException("BUILD_ENGINE_REPO_URL must be SSH Url: $repoUrl", 1456850613);
        }

        // If buildEngineRepoUrl is CodeCommit, insert the userId
        if (preg_match('/^ssh:\/\/git-codecommit/', $repoUrl)) {
            // If using CodeCommit, GitSshUser is required
            $sshUser = \Yii::$app->params['buildEngineGitSshUser'];
            if (is_null($sshUser)) {
                throw new ServerErrorHttpException("BUILD_ENGINE_GIT_SSH_USER must be set if using codecommit: $repoUrl", 1456850614);
            }
            $repoUrl = "ssh://" . $sshUser . "@" . substr($repoUrl, 6);
        }

        require_once __DIR__ . '/../../vendor/autoload.php';
        $wrapper = \Yii::$container->get('gitWrapper');

        $wrapper->setEnvVar('HOME', '/data');
        $wrapper->setPrivateKey($privateKey);
        $git = null;
        if (!$this->fileUtil->file_exists($repoLocalPath))
        {
            $git = $wrapper->clone($repoUrl, $repoLocalPath);
            $git->config('push.default', 'simple');
        } else {
            $git = $wrapper->init($repoLocalPath);
            $git->fetchAll();
            try {
                $git->reset("--hard", "origin/$repoBranch");
            } catch (\Exception $e) {
                echo "origin/$repoBranch doesn't exist yet. \n";
            }
        }
        // Set afterwards in case the configuration changes after
        // the repo has been cloned (i.e. services has been restarted
        // with different configuration).
        $userName = \Yii::$app->params['buildEngineGitUserName'];
        $userEmail = \Yii::$app->params['buildEngineGitUserEmail'];

        $git->config('user.name', $userName);
        $git->config('user.email', $userEmail);

        // Check to see if empty repo
        try {
            $git->checkout($repoBranch);

        } catch (\Exception $e) {
            echo "$repoBranch doesn't exist.  Trying to create it. \n";
            $git->checkoutNewBranch($repoBranch);
        }

        return $git;
    }
}
