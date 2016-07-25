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
        $jenkinsUrl = $this->getJenkinsBaseUrl();
        $jenkins = new Jenkins($jenkinsUrl);
        return $jenkins;
    }
    public function getJenkinsBaseUrl(){
        return \Yii::$app->params['buildEngineJenkinsMasterUrl'];
    }
    /**
     *
     * @return Jenkins for publish url
     */
    public function getPublishJenkins(){
        $jenkinsUrl = $this->getPublishJenkinsBaseUrl();
        $jenkins = new Jenkins($jenkinsUrl);
        return $jenkins;
    }
    public function getPublishJenkinsBaseUrl() {
        return \Yii::$app->params['publishJenkinsMasterUrl'];
    }
    public function getArtifactUrls($jenkinsBuild) {

        $jenkinsArtifacts = $jenkinsBuild->get("artifacts");
        if (!$jenkinsArtifacts) { return null; }
        $artifactUrls = array();
        $artifactRelativePaths = array();
        foreach ($jenkinsArtifacts as $testArtifact) {
            $relativePath = explode("output/", $testArtifact->relativePath)[1];
            array_push($artifactRelativePaths, $relativePath);
            $artifactUrl = $this->getArtifactUrlFromRelativePath($jenkinsBuild, $testArtifact->relativePath);
            array_push($artifactUrls, $artifactUrl);
        }
        return array($artifactUrls, $artifactRelativePaths);
    }

    public function getArtifactUrlFromRelativePath($jenkinsBuild, $relativePath) {
        $baseUrl = $jenkinsBuild->getJenkins()->getBaseUrl();
        $buildUrl = $jenkinsBuild->getBuildUrl();
        $pieces = explode("job", $buildUrl);
        return $baseUrl."job".$pieces[1]."artifact/".$relativePath;

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
     public function getMetaDataArtifactUrl($jenkinsBuild)
     {
         return $this->getArtifactUrl($jenkinsBuild, "/publish.tar.gz/");
     }
    /**
     * Extract the Artifact Url from the Jenkins Build information.
     * @param JenkinsBuild $jenkinsBuild
     * @return string
     */
    public function getAboutArtifactUrl($jenkinsBuild)
    {
        return $this->getArtifactUrl($jenkinsBuild, "/about.txt/");
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
        if (!is_null($build->artifact_url_base)) {
            $log['buildArtifactUrlBase'] = $build->artifact_url_base;
        }
        if (!is_null($build->artifact_files)) {
            $log['buildArtifactFiles'] = $build->artifact_files;
        }

        echo "Job=$jobName, Id=$build->id, Status=$build->status, Number=$build->build_number, "
                    . "Result=$build->result, ArtifactUrlBase=$build->artifact_url_base, ArtifactFiles=$build->artifact_files". PHP_EOL;
        return $log;
    }

}
