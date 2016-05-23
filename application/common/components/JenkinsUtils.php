<?php
namespace common\components;

use common\models\Build;

use JenkinsApi\Jenkins;
use JenkinsApi\Item\Build as JenkinsBuild;

class JenkinsUtils
{

    /**
     *
     * @return Jenkins
     */
    public function getJenkins(){
        $jenkinsUrl = \Yii::$app->params['buildEngineJenkinsMasterUrl'];
        $jenkins = new Jenkins($jenkinsUrl);
        return $jenkins;
    }
    /**
     *
     * @return Jenkins for publish url
     */
    public function getPublishJenkins(){
        $jenkinsUrl = \Yii::$app->params['publishJenkinsMasterUrl'];
        $jenkins = new Jenkins($jenkinsUrl);
        return $jenkins;
    }
     /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
    public function getApkArtifactUrl($jenkinsBuild)
    {
       return $this->getArtifactUrl($jenkinsBuild, "/\.apk$/");
    }
    /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
     public function getVersionCodeArtifactUrl($jenkinsBuild)
     {
         return $this->getArtifactUrl($jenkinsBuild, "/version_code.txt/");
     }
     /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
     public function getPackageNameArtifactUrl($jenkinsBuild)
     {
         return $this->getArtifactUrl($jenkinsBuild, "/package_name.txt/");
     }
     /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
     public static function getMetaDataArtifactUrl($jenkinsBuild)
     {
         return self::getArtifactUrl($jenkinsBuild, "/publish.tar.gz/");
     }

    /**
     * Get the artifact url base string from the configured parameters
     * @return string
     */
    public static function getArtifactUrlBase(){
        return \Yii::$app->params['buildEngineArtifactUrlBase'] . "/" . \Yii::$app->params['appEnv'];
    }
    /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @param string $artifactPattern
     * @return string
     */
    private function getArtifactUrl($jenkinsBuild, $artifactPattern)
    {
        $artifacts = $jenkinsBuild->get("artifacts");
        if (!$artifacts) { return null; }
        $artifact = null;
        foreach ($artifacts as $testArtifact) {
            if(preg_match($artifactPattern,$testArtifact->relativePath)) {
                $artifact = $testArtifact;
                break;
            }
        }
        if (!$artifact) {
            echo "getArtifactURL: No artifact matching ".$artifactPattern . PHP_EOL;
            return null;
        }
        $relativePath = $artifact->relativePath;
        $baseUrl = $jenkinsBuild->getJenkins()->getBaseUrl();
        $buildUrl = $jenkinsBuild->getBuildUrl();
        $pieces = explode("job", $buildUrl);
        return $baseUrl."job".$pieces[1]."artifact/".$relativePath;
    }
     /**
     *
     * get build details for logging.
     * @param Build $build
     * @return Array
     */
    public static function getlogBuildDetails($build)
    {
        $jobName = $build->job->name();
        $log = [
            'jobName' => $jobName
        ];
        $log['buildId'] = $build->id;
        $log['buildStatus'] = $build->status;
        $log['buildNumber'] = $build->build_number;
        $log['buildResult'] = $build->result;
        $log['buildArtifactUrl'] = $build->artifact_url;

        echo "Job=$jobName, Id=$build->id, Status=$build->status, Number=$build->build_number, "
                    . "Result=$build->result, ArtifactUrl=$build->artifact_url". PHP_EOL;
        return $log;
    }

}
