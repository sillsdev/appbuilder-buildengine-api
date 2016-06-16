<?php

namespace console\components;

use console\components\OperationInterface;
use common\models\Job;
use common\models\Build;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\JenkinsUtils;

use common\helpers\Utils;

use JenkinsApi\Item\Build as JenkinsBuild;

class CopyToS3Operation implements OperationInterface
{
    private $build_id;
    private $maxRetries = 50;
    private $maxDelay = 30;
    private $alertAfter = 5;
    private $jenkinsUtils;
    
    public function __construct($id)
    {
        $this->build_id = $id;
        $this->jenkinsUtils = \Yii::$container->get('jenkinsUtils');
    }
    public function performOperation()
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] CopyToS3Operation ID: " .$this->build_id . PHP_EOL;
        $build = Build::findOneByBuildId($this->build_id);
        if ($build) {
            $job = $build->job;
            if ($job){
                $jenkins = $this->jenkinsUtils->getJenkins();
                $jenkinsJob = $jenkins->getJob($job->nameForBuild());
                $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
                if ($jenkinsBuild){
                    $this->saveBuild($build, $jenkinsBuild);
                    $build->status = Build::STATUS_COMPLETED;
                    if (!$build->save()){
                        throw new \Exception("Unable to update Build entry, model errors: ".print_r($build->getFirstErrors(),true), 1450216434);
                    }
                }    
            }
        }
    }
    public function getMaximumRetries()
    {
        return $this->maxRetries;
    }
    public function getMaximumDelay()
    {
        return $this->maxDelay;
    }
    public function getAlertAfterAttemptCount()
    {
        return $this->alertAfter;
    }

    private function getExtraContent($artifactRelativePaths) {
        $hasPlayListing = false;
        $defaultLanguage = null;
        $extraContent = array();

        foreach ($artifactRelativePaths as $path) {
            if (preg_match("/play-listing/", $path)) {
                $hasPlayListing = true;
                // For now, the first language that has an icon will be the default-language
                if (preg_match("/play-listing\/([^\/]*)\/images\/icon.png$/", $path, $matches)) {
                    $defaultLanguage = $matches[1];
                }
            }
        }

        if ($hasPlayListing) {
            $file = \Yii::getAlias("@common") . "/preview/playlisting/index.html";

            $extraContent["play-listing/index.html"] = file_get_contents($file);

            // Note: I tried using array_map/array_filter, but it changed the json
            // serialization from an array to a hash where the indexes were the old
            // positions in the array.
            $playRelativePaths = array();
            foreach ($artifactRelativePaths as $path) {
                if (0 === strpos($path, "play-listing/")) {
                    array_push($playRelativePaths, substr($path, strlen("play-listing/")));
                }
            }
            $manifest = [ "files" => $playRelativePaths ];
            if (!empty($defaultLanguage)) {
                $manifest["default-language"] = $defaultLanguage;
            }
            $extraContent["play-listing/manifest.json"] = json_encode($manifest, JSON_UNESCAPED_SLASHES);
        }

        return $extraContent;
    }

    /**
     * Save the build to S3.
     * @param Build $build
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
    private function saveBuild($build, $jenkinsBuild) {
        # Get list of artifacts from Jenkins build
        list($artifactUrls, $artifactRelativePaths) = $this->jenkinsUtils->getArtifactUrls($jenkinsBuild);

        $extraContent = $this->getExtraContent($artifactRelativePaths);

        # Save to S3
        $s3 = new S3();
        $s3->saveBuildToS3($build, $artifactUrls, $extraContent);

        # Log
        $logger = new Appbuilder_logger("CopyToS3Operation");
        $log = JenkinsUtils::getlogBuildDetails($build);

//        $log['NOTE:']='save the build to S3 and return $apkPublicUrl and $versionCode';
//        $log['jenkins_ArtifactUrl'] = $artifactUrl;
//        $log['baseUrl'] = $baseUrl;
//        $log['files'] = $fileList;
//        $log['version'] = $versionCode;
        $logger->appbuilderWarningLog($log);
    }
}
