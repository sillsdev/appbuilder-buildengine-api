<?php
namespace console\components;


use common\models\Job;
use common\models\Build;
use common\models\Release;
use common\models\OperationQueue;
use common\components\JenkinsUtils;

use common\helpers\Utils;
use yii\web\ServerErrorHttpException;

class SyncScriptsAction
{
    public static function performAction(
            $cronController, $viewPath, $git,
            $repoLocalPath, $scriptDir, $appBuilderGitSshUser)
    {
        $prefix = Utils::getPrefix();

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
        self::recurse_copy($utilitiesSourceDir, $utilitiesDestDir, $git);
        foreach (array_keys($apps) as $app) {
            $appSourceDir = $dataScriptDir.DIRECTORY_SEPARATOR.$app;
            $appDestDir = $localScriptDir . DIRECTORY_SEPARATOR .$app;
            self::recurse_copy($appSourceDir, $appDestDir, $git);
        }
        foreach (Job::find()->each(50) as $job)
        {
            list($updatesString, $added, $updated) = self::createJobScripts($job, $jobs, $git, $gitSubstPatterns, $cronController, $localScriptDir, $prefix);
            $changesString = $changesString . $updatesString;
            $totalAdded = $totalAdded + $added;
            $totalUpdated = $totalUpdated + $updated;
        }

        // Remove Scripts that are not in the database
        $globFileName = "*_*.groovy";
        foreach (glob($localScriptDir . DIRECTORY_SEPARATOR .  $globFileName) as $scriptFile)
        {
            list($removedString, $removed) = self::removeScriptIfNoJobRecord($scriptFile, $apps, $jobs, $git, $prefix);
            $changesString = $changesString . $removedString;
            $totalRemoved = $totalRemoved + $removed;
        }
        $commitString = "cron add=" . $totalAdded . " update =" . $totalUpdated . " delete=" . $totalRemoved . PHP_EOL;
        $commitString = $commitString . $changesString;
        echo $commitString;
        self::applyUpdates($git, $commitString, $prefix);
    }
    private static function recurse_copy($src,$dst, $git) {
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
                    self::recurse_copy($srcFile,$dstFile, $git);
                }
                else {
                    copy($srcFile,$dstFile);
                    $git->add($dstFile);
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
    private static function doReplacements($subject, $patterns)
    {
        foreach ($patterns as $pattern => $replacement )
        {
            $subject = preg_replace($pattern, $replacement, $subject);
        }
        return $subject;
    }

    private static function applyUpdates($git, $commitString, $prefix)
    {
        echo "applyUpdates starting". PHP_EOL;
        if ($git->hasChanges())
        {
            echo "[$prefix] Changes detected...committing..." . PHP_EOL;
            $git->commit($commitString);
            $git->push();
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
    private static function createBuild($job)
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
     * @param GitWrapper $git - The git repository
     * @param String $prefix - Time stamp prefix for log messages
     * @return String, Int - String for cron commit and number of records removed
     */
    private static function removeScriptIfNoJobRecord($scriptFile, $apps, $jobs, &$git, $prefix)
    {
        $removed = 0;
        $retString = "";
        $jobName = basename($scriptFile, ".groovy");
        list($app_id, $request_id) = explode("_", $jobName);
        if (array_key_exists($app_id, $apps))
        {
            if (!array_key_exists($jobName, $jobs))
            {
                echo "[$prefix] Removing: $jobName" . PHP_EOL;
                $git->rm($scriptFile);
                $removed++;
                $retString = $retString."remove: ".$jobName.PHP_EOL;
            }
        }
        return[$retString, $removed];
    }
    private static function createJobScripts($job, &$jobs, &$git, $gitSubstPatterns, $cronController, $localScriptDir, $prefix)
    {
        $added = 0;
        $updated = 0;
        $retString = "";
        $artifactUrlBase = JenkinsUtils::getArtifactUrlBase();
        $publisherName = $job->publisher_id;
        $buildJobName = $job->name();
        $gitUrl = self::doReplacements($job->git_url, $gitSubstPatterns);

        $script = $cronController->renderPartial("scripts/$job->app_id", [
            'publisherName' => $publisherName,
            'buildJobName' => $buildJobName,
            'publishJobName' => Release::jobNameForBuild($buildJobName),
            'gitUrl' => $gitUrl,
            'artifactUrlBase' => $artifactUrlBase,
        ]);

        $file = $localScriptDir . DIRECTORY_SEPARATOR . $buildJobName . ".groovy";
        $file_exists = file_exists($file);
        $handle = fopen($file, "w");
        fwrite($handle, $script);
        fclose($handle);
        if ($git->getStatus($file))
        {
            if ($file_exists) {
                echo "[$prefix] Updated: $buildJobName" . PHP_EOL;
                $retString = $retString."update: ".$buildJobName.PHP_EOL;
                $updated++;
            } else {
                echo "[$prefix] Added: $buildJobName" . PHP_EOL;
                $retString = $retString."update: ".$buildJobName.PHP_EOL;
                $added++;
            }
            $git->add($file);
            self::createBuild($job);
        }

        $jobs[$buildJobName] = 1;
        return [$retString, $added, $updated];
    }
}