<?php

namespace common\components;

use common\components\OperationInterface;
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
    
    public function __construct($id)
    {
        $this->build_id = $id;
    }
    public function performOperation()
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] CopyToS3Operation ID: " .$this->build_id . PHP_EOL;
        $build = Build::findOneByBuildId($this->build_id);
        if ($build) {
            $job = $build->job;
            if ($job){
                $jenkins = JenkinsUtils::getJenkins();
                $jenkinsJob = $jenkins->getJob($job->name());
                $jenkinsBuild = $jenkinsJob->getBuild($build->build_number);
                if ($jenkinsBuild){
                    list($build->artifact_url, $build->version_code) = $this->saveBuild($build, $jenkinsBuild);
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

    /**
     * Save the build to S3.
     * @param Build $build
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
    private function saveBuild($build, $jenkinsBuild)
    {
        $logger = new Appbuilder_logger("OperationQueue");
        $artifactUrl =  JenkinsUtils::getApkArtifactUrl($jenkinsBuild);
        $versionCodeArtifactUrl = JenkinsUtils::getVersionCodeArtifactUrl($jenkinsBuild);
        list($apkPublicUrl, $versionCode) = S3::saveBuildToS3($build, $artifactUrl, $versionCodeArtifactUrl);
        $log = JenkinsUtils::getlogBuildDetails($build);
        $log['NOTE:']='save the build to S3 and return $apkPublicUrl and $versionCode';
        $log['jenkins_ArtifactUrl'] = $artifactUrl;
        $log['apkPublicUrl'] = $apkPublicUrl;
        $log['version'] = $versionCode;
        $logger->appbuilderWarningLog($log);
        echo "returning: $apkPublicUrl version: $versionCode". PHP_EOL;

        return [$apkPublicUrl, $versionCode];
    }
}
