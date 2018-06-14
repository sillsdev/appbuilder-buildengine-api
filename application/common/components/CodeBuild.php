<?php

namespace common\components;

use common\models\Build;
use common\models\Job;
use JenkinsApi\Jenkins;
use yii\web\ServerErrorHttpException;
use common\components\AWSCommon;
use common\helpers\Utils;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class CodeBuild extends AWSCommon {
    const PHASE_COMPLETED = 'COMPLETED';
    const STATUS_SUCCEEDED = 'SUCCEEDED';
    const STATUS_FAILED = 'FAILED';
    const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    const STATUS_STOPPED = 'STOPPED';
    const STATUS_TIMED_OUT = 'TIMED_OUT';
    const STATUS_FAULT = 'FAULT';

    public $codeBuildClient;
    private $fileUtil;
    public function __construct() {
        try {
            // Injected if Unit Test
            $this->codeBuildClient = \Yii::$container->get('codeBuildClient'); 
        } catch (\Exception $e) {
            // Get real S3 client
            $this->codeBuildClient = self::getCodeBuildClient();
        }
        $this->fileUtil = \Yii::$container->get('fileUtils');
    }
    
    /**
     * Configure and get the CodeBuild Client
     * @return \Aws\CodeBuild\CodeBuildClient
     */
    public static function getCodeBuildClient() {
        $client = new \Aws\CodeBuild\CodeBuildClient([
            'region' => CodeBuild::getArtifactsBucketRegion(),
            'version' => '2016-10-06'
            ]);
        return $client;
    }

    /**
     * Start a build for the function
     * 
     * @param string $repoHttpUrl
     * @param string $commitId
     * @param string $build
     * @param string $buildProcess Name of CodeBuild project
     * @param string $jobNumber
     * @param string $buildNumber
     * @param string $buildSpec Buildspec script to be executed
     * @return string Guid part of build ID
     */
    public function startBuild($repoHttpUrl, $commitId, $build, $buildSpec) {
        $prefix = Utils::getPrefix();
        $job = $build->job;
        $buildProcess = $job->nameForBuildProcess();
        $jobNumber = (string)$job->id;
        $buildNumber = (string)$build->id;
        echo "[$prefix] startBuild CodeBuild Project: " . $buildProcess . " URL: " .$repoHttpUrl . " commitId: " . $commitId . " jobNumber: " . $jobNumber . " buildNumber: " . $buildNumber . PHP_EOL;
        $artifacts_bucket = self::getArtifactsBucket();
        $productionStage = self::getAppEnv();
        $buildApp = 'build_app';
        $buildPath = $this->getBuildPath($job);
        $artifactPath = $productionStage . '/jobs/' . $buildProcess . '_' . $jobNumber;
        echo "Artifacts path: " . $artifactPath . PHP_EOL;
        $promise = $this->codeBuildClient->startBuildAsync([
            'projectName' => $buildApp,
            'artifactsOverride' => [
                'location' => $artifacts_bucket, // output bucket
                'name' => '/',                   // name of output artifact object
                'namespaceType' => 'NONE',
                'packaging' => 'NONE',
                'path' => $artifactPath,         // path to output artifacts
                'type' => 'S3',                  // REQUIRED
            ],
            'buildspecOverride' => $buildSpec,
            'environmentVariablesOverride' => [
                [
                    'name' => 'BUILD_NUMBER',
                    'value' => $buildNumber,
                ],
                [
                    'name' => 'APP_BUILDER_SCRIPT_PATH',
                    'value' => $buildPath,
                ],
                [
                    'name' => 'PUBLISHER',
                    'value' => $job->publisher_id,
                ]
            ],
            'sourceLocationOverride' => $repoHttpUrl,
            'sourceVersion' => $commitId     
        ]);
        $state = $promise->getState();
        echo "state: " . $state . PHP_EOL;
        $result = $promise->wait(true);
        $buildInfo = $result['build'];
        $buildArn = $buildInfo['arn'];
        $buildId = $buildInfo['id'];
        $buildGuid = substr($buildId, strrpos($buildId, ':') + 1);
        echo "Build id: " . $buildId . " Guid: " . $buildGuid . PHP_EOL;
        return $buildGuid;
    }

    /**
     * This method returns the build status object
     * 
     * @param string $guid - Code Build GUID for the build
     * @param string $buildProcess - Name of code build project (e.g. build_scriptureappbuilder)
     * @return AWS/Result Result object on the status of the build
     */
    public function getBuildStatus($guid, $buildProcess) {
        $prefix = Utils::getPrefix();
        echo "[$prefix] getBuildStatus CodeBuild Project: " . $buildProcess . " BuildGuid: " . $guid . PHP_EOL;

        $buildId = $this->getBuildId($guid, 'build_app');
        $result = $this->codeBuildClient->batchGetBuilds([
            'ids' => [
                $buildId
            ]
        ]);
        $builds = $result['builds'];
        try {
        $statusOfSelectedBuild = $builds[0];
//        var_dump($statusOfSelectedBuild);
        
        return $statusOfSelectedBuild;
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] getBuildStatus: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            var_dump($result);
        }
    }

    /**
     * This method returns a boolean of the completion status of the job
     * based upon the build status object passed in
     */
    public function isBuildComplete($buildStatus) {
        $complete = $buildStatus['buildComplete'];
        return $complete;
    }
    /**
     * This method returns the status of the build
     * @param AWS/Result Return value from getBuildStatus 
     */
    public function getStatus($buildStatus) {
        $status = $buildStatus['buildStatus'];
        return $status;
    }
    /**
     * Recreate the build id
     * 
     * @param string $guid Build GUID
     * @param string $buildProcess CodeBuild Project Name (e.g. scriptureappbuilder)
     * @return string CodeBuild build arn
     */
    private function getBuildId($guid, $buildProcess) {
        $buildId = $buildProcess . ':' . $guid;
        echo "getBuildId arn: " . $buildId . PHP_EOL;
        return $buildId;
    }
    /**
     * Returns the name of the shell command to be run
     * 
     * @param $job Job associated with this build
     * @return string Name of the task to be run
     */
    private function getBuildPath($job) {
        $app_id = $job->app_id;
        $retVal = 'unknown';
        switch ($app_id) {
            case Job::APP_TYPE_SCRIPTUREAPP:
                $retVal = 'scripture-app-builder';
                break;
            case Job::APP_TYPE_READINGAPP:
                $retVal = 'reading-app-builder';
                break;
            case Job::APP_TYPE_DICTIONARYAPP:
                $retVal = 'dictionary-app-builder';
                break;
            default:
                $retVal = 'unknown';
                break;
        }
        return $retVal;
    }
}