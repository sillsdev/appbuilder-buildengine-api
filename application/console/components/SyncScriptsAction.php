<?php
namespace console\components;

use GitWrapper\GitWrapper;

use common\models\Job;
use common\models\Build;
use common\models\Release;
use common\models\OperationQueue;
use common\components\JenkinsUtils;

use common\helpers\Utils;
use yii\web\ServerErrorHttpException;

class SyncScriptsAction
{
    private $cronController;
    private $git;
    private $prefix;

    public function __construct($cronController)
    {
        $this->cronController = $cronController;
    }

    public function __destruct()
    {
        $this->cronController = null;
        $this->git = null;
    }
    public function performAction()
    {
        $prefix = Utils::getPrefix();

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
            list($updatesString, $added, $updated) = $this->createBuildScript($job, $jobs, $gitSubstPatterns, $localScriptDir);
            $changesString = $changesString . $updatesString;
            $totalAdded = $totalAdded + $added;
            $totalUpdated = $totalUpdated + $updated;
            list($updatesString2, $added2, $updated2) = $this->createPublishScript($job, $jobs, $gitSubstPatterns, $localScriptDir);
            $changesString = $changesString . $updatesString2;
            $totalAdded = $totalAdded + $added2;
            $totalUpdated = $totalUpdated + $updated2;
        }

        // Remove Scripts that are not in the database
        $globFileName = "*_*.groovy";
        foreach (glob($localScriptDir . DIRECTORY_SEPARATOR .  $globFileName) as $scriptFile)
        {
            list($removedString, $removed) = $this->removeScriptIfNoJobRecord($scriptFile, $apps, $jobs);
            $changesString = $changesString . $removedString;
            $totalRemoved = $totalRemoved + $removed;
        }
        $commitString = "cron add=" . $totalAdded . " update =" . $totalUpdated . " delete=" . $totalRemoved . PHP_EOL;
        $commitString = $commitString . $changesString;
        echo $commitString;
        $this->applyUpdates($commitString);
    }
    private function recurse_copy($src,$dst) {
        echo "recurse_copy src ".$src.PHP_EOL;
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
                    $this->recurse_copy($srcFile,$dstFile);
                }
                else {
                    copy($srcFile,$dstFile);
                    $this->git->add($dstFile);
                }
            }
        }
        closedir($dir);
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
        echo "applyUpdates starting". PHP_EOL;
        if ($this->git->hasChanges())
        {
            echo "[$this->prefix] Changes detected...committing..." . PHP_EOL;
            $this->git->commit($commitString);
            $this->git->push();
            $task = OperationQueue::UPDATEJOBS;
            OperationQueue::findOrCreate($task, null, null);
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
     * @param Array $apps - List of supported apps
     * @param Array $jobs - All of the jobs in the database
     * @return String, Int - String for cron commit and number of records removed
     */
    private function removeScriptIfNoJobRecord($scriptFile, $apps, $jobs)
    {
        $removed = 0;
        $retString = "";
        $fileName = basename($scriptFile, ".groovy");
        list($app_id, $request_id) = explode("_", $fileName);
        $jobName = $app_id."_".$request_id;
        if (array_key_exists($app_id, $apps))
        {
            if (!array_key_exists($jobName, $jobs))
            {
                echo "[$this->prefix] Removing: $fileName" . PHP_EOL;
                $this->git->rm($scriptFile);
                $removed++;
                $retString = $retString."remove: ".$fileName.PHP_EOL;
            }
        }
        return[$retString, $removed];
    }
    private function createBuildScript($job, &$jobs, $gitSubstPatterns, $localScriptDir)
    {
        list($updatesString, $added, $updated, $buildJobName) = $this->createJobScripts($job, $jobs, $gitSubstPatterns, $localScriptDir, "_build");
        $changed = $added + $updated;
        if ($changed > 0)
        {
            $this->createBuild($job);
        }

        $jobs[$buildJobName] = 1;
        return [$updatesString, $added, $updated];
    }
    private function createPublishScript($job, &$jobs, $gitSubstPatterns, $localScriptDir)
    {
        list($updatesString, $added, $updated, $buildJobName) = $this->createJobScripts($job, $jobs, $gitSubstPatterns, $localScriptDir, "_publish");
        return [$updatesString, $added, $updated];
    }
    private function createJobScripts($job, &$jobs, $gitSubstPatterns, $localScriptDir, $extension)
    {
        $added = 0;
        $updated = 0;
        $retString = "";
        $artifactUrlBase = JenkinsUtils::getArtifactUrlBase();
        $publisherName = $job->publisher_id;
        $buildJobName = $job->name();
        $gitUrl = $this->doReplacements($job->git_url, $gitSubstPatterns);

        $script = $this->cronController->renderPartial("scripts/$job->app_id".$extension, [
            'publisherName' => $publisherName,
            'buildJobName' => $buildJobName,
            'publishJobName' => Release::jobNameForBuild($buildJobName),
            'gitUrl' => $gitUrl,
            'artifactUrlBase' => $artifactUrlBase,
        ]);

        $file = $localScriptDir . DIRECTORY_SEPARATOR . $buildJobName . $extension . ".groovy";
        $file_exists = file_exists($file);
        $handle = fopen($file, "w");
        fwrite($handle, $script);
        fclose($handle);
        if ($this->git->getStatus($file))
        {
            if ($file_exists) {
                echo "[$this->prefix] Updated: $buildJobName" . $extension . PHP_EOL;
                $retString = $retString."update: ".$buildJobName.$extension.PHP_EOL;
                $updated++;
            } else {
                echo "[$this->prefix] Added: $buildJobName" .$extension . PHP_EOL;
                $retString = $retString."update: ".$buildJobName.$extension.PHP_EOL;
                $added++;
            }
            $this->git->add($file);
        }
        return [$retString, $added, $updated, $buildJobName];
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

        echo "1) RepoUrl: $repoUrl\n";

        // If buildEngineRepoUrl is CodeCommit, insert the userId
        if (preg_match('/^ssh:\/\/git-codecommit/', $repoUrl)) {
            // If using CodeCommit, GitSshUser is required
            $sshUser = \Yii::$app->params['buildEngineGitSshUser'];
            if (is_null($sshUser)) {
                throw new ServerErrorHttpException("BUILD_ENGINE_GIT_SSH_USER must be set if using codecommit: $repoUrl", 1456850614);
            }
            $repoUrl = "ssh://" . $sshUser . "@" . substr($repoUrl, 6);
        }

        echo "2) RepoUrl: $repoUrl\n";

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
