<?php

namespace common\components;

use common\models\Build;
use common\models\Job;
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
     * @param string $repoUrl
     * @param string $commitId
     * @param string $build
     * @param string $buildSpec Buildspec script to be executed
     * @param string $versionCode
     * @return string Guid part of build ID
     */
    public function startBuild($repoUrl, $commitId, $build, $buildSpec, $versionCode, $codeCommit) {
        $prefix = Utils::getPrefix();
        $job = $build->job;
        $buildProcess = $job->nameForBuildProcess();
        $jobNumber = (string)$job->id;
        $buildNumber = (string)$build->id;
        echo "[$prefix] startBuild CodeBuild Project: " . $buildProcess . " URL: " .$repoUrl . " commitId: " . $commitId . " jobNumber: " . $jobNumber . " buildNumber: " . $buildNumber . " versionCode: " . $versionCode . PHP_EOL;
        $artifacts_bucket = self::getArtifactsBucket();
        $secretsBucket = self::getSecretsBucket();
        $buildApp = self::getCodeBuildProjectName('build_app');
        $buildPath = $this->getBuildPath($job);
        $artifactPath = $this->getArtifactPath($job, 'codebuild-output');
        echo "Artifacts path: " . $artifactPath . PHP_EOL;
        // Leaving all this code together to make it easier to remove when git is no longer supported
        if ($codeCommit) {
            echo "[$prefix] startBuild CodeCommit Project";
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
                    ],
                    [
                        'name' => 'VERSION_CODE',
                        'value' => $versionCode,
                    ],
                    [
                        'name' => 'SECRETS_BUCKET',
                        'value' => $secretsBucket,
                    ]
                ],
                'sourceLocationOverride' => $repoUrl,
                'sourceVersion' => $commitId
            ]);
            $state = $promise->getState();
            echo "state: " . $state . PHP_EOL;
            $result = $promise->wait(true);
            $buildInfo = $result['build'];
            $buildId = $buildInfo['id'];
            $buildGuid = substr($buildId, strrpos($buildId, ':') + 1);
            echo "Build id: " . $buildId . " Guid: " . $buildGuid . PHP_EOL;
            return $buildGuid;
        } else {
            echo "[$prefix] startBuild S3 Project" . PHP_EOL;
            $targets = $build->targets;
            if (is_null($targets)) {
                $targets = 'apk play-listing';
            }
            $environmentArray = [
                // BUILD_NUMBER Must be first for tests
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
                ],
                [
                    'name' => 'VERSION_CODE',
                    'value' => $versionCode,
                ],
                [
                    'name' => 'SECRETS_BUCKET',
                    'value' => $secretsBucket,
                ],
                [
                    'name' => 'PROJECT_S3',
                    'value' => $repoUrl,
                ],
                [
                    'name' => 'TARGETS',
                    'value' => $targets,
                ],
                [
                    'name' => 'SCRIPT_S3',
                    'value' => S3::getBuildScriptPath(),
                ]
            ];
            $adjustedEnvironmentArray = $this->addEnvironmentToArray($environmentArray, $build->environment);
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
                'environmentVariablesOverride' => $adjustedEnvironmentArray,
                'sourceTypeOverride' => 'NO_SOURCE'
            ]);
            $state = $promise->getState();
            echo "state: " . $state . PHP_EOL;
            $result = $promise->wait(true);
            $buildInfo = $result['build'];
            $buildId = $buildInfo['id'];
            $buildGuid = substr($buildId, strrpos($buildId, ':') + 1);
            echo "Build id: " . $buildId . " Guid: " . $buildGuid . PHP_EOL;
            return $buildGuid;
        }
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

        $buildId = $this->getBuildId($guid, $buildProcess);
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
     * This method returns the completion status of the job
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
	    case Job::APP_TYPE_KEYBOARDAPP:
                $retVal = 'keyboard-app-builder';
                break;
            default:
                $retVal = 'unknown';
                break;
        }
        return $retVal;
    }

    /**
     * Starts a publish action
     * @param Release $release
     * @param string $releaseSpec
     * @return false|string
     */
    public function startRelease($release, $releaseSpec)
    {
        echo 'startRelease: ' . PHP_EOL;
        $prefix = Utils::getPrefix();
        $releaseNumber = (string)$release->id;
        $build = $release->build;
        $buildNumber = (string)$build->id;
        $job = $build->job;
        $artifactUrl = $build->apk();
        $artifacts_bucket = self::getArtifactsBucket();
        $artifactPath = $this->getArtifactPath($job, 'codebuild-output', true);
        $secretsBucket = self::getSecretsBucket();
        $publishApp = self::getCodeBuildProjectName('publish_app');
        $promoteFrom = $release->promote_from;
        if (is_null($promoteFrom)) {
            $promoteFrom = "";
        }

        $sourceLocation = $this->getSourceLocation($build);
        $s3Artifacts = $this->getArtifactsLocation($build);
        echo 'Source location: ' . $sourceLocation . PHP_EOL;
        $targets = $release->targets;
        if (is_null($targets)) {
            $targets = 'google-play';
        }

        $environmentArray = [
            // RELEASE_NUMBER Must be first for tests
            [
                'name' => 'RELEASE_NUMBER',
                'value' => $releaseNumber,
            ],
            [
                'name' => 'BUILD_NUMBER',
                'value' => $buildNumber,
            ],
            [
                'name' => 'CHANNEL',
                'value' => $release->channel,
            ],
            [
                'name' => 'PUBLISHER',
                'value' => $job->publisher_id,
            ],
            [
                'name' => 'SECRETS_BUCKET',
                'value' => $secretsBucket,
            ],
            [
                'name' => 'PROMOTE_FROM',
                'value' => $promoteFrom,
            ],
            [
                'name' => 'ARTIFACTS_S3_DIR',
                'value' => $s3Artifacts,
            ],
            [
                'name' => 'TARGETS',
                'value' => $targets,
            ],
            [
                'name' => 'SCRIPT_S3',
                'value' => S3::getBuildScriptPath(),
            ]
        ];
        $adjustedEnvironmentArray = $this->addEnvironmentToArray($environmentArray, $release->environment);
        $promise = $this->codeBuildClient->startBuildAsync([
            'projectName' => $publishApp,
            'artifactsOverride' => [
                'location' => $artifacts_bucket, // output bucket
                'name' => '/',                   // name of output artifact object
                'namespaceType' => 'NONE',
                'packaging' => 'NONE',
                'path' => $artifactPath,         // path to output artifacts
                'type' => 'S3',                  // REQUIRED
            ],
            'buildspecOverride' => $releaseSpec,
            'environmentVariablesOverride' => $adjustedEnvironmentArray,
            'sourceLocationOverride' => $sourceLocation,
        ]);
        $state = $promise->getState();
        echo "state: " . $state . PHP_EOL;
        $result = $promise->wait(true);
        $buildInfo = $result['build'];
        $buildId = $buildInfo['id'];
        $buildGuid = substr($buildId, strrpos($buildId, ':') + 1);
        echo "Build id: " . $buildId . " Guid: " . $buildGuid . PHP_EOL;
        return $buildGuid;
    }
    /**
     * Get the url for the apk file in a format that codebuild accepts for an S3 Source
     * We are using the apk file as a source, even though we're not really using it because
     * codebuild requires a source and if S3 is the type, it must be a zip file.
     *
     * @param Build $build - build object for this operation
     * @return string - Arn format for the apk file
     */
    private function getSourceLocation($build)
    {
        $appEnv = S3::getAppEnv();
        $apkFilename = $build->apkFilename();
        $sourceLocation = S3::getS3Arn($build, $appEnv, $apkFilename);
        return $sourceLocation;
    }
    /**
     * Get the URL for the S3 artifacts folder in the format required by the buildspec
     *
     * @param Build $build - build object for this operation
     * @return string - s3:// url format for s3 artifacts folder
     */
    private function getArtifactsLocation($build)
    {
        $artifactsBucket = self::getArtifactsBucket();
        $artifactFolder = $build->getBasePrefixUrl(self::getAppEnv());
        $artifactsLocation = 's3://' . $artifactsBucket . '/' . $artifactFolder;
        return($artifactsLocation);
    }

    /**
     * This function creates a project in CodeBuild
     *
     * @param string $base_name base project being built, e.g. build_app or publish_app
     * @param string $role_arn Arn for the IAm role
     * @param Array $cache Strings defining the cache parameter of the build
     * @param Array $source Strings defining the source parameter of the build
     *
     */
    public function createProject($base_name, $role_arn, $cache, $source) {
        $project_name = self::getCodeBuildProjectName($base_name);
        $artifacts_bucket = self::getArtifactsBucket();
        echo "Bucket: $artifacts_bucket" . PHP_EOL;
        $result = $this->codeBuildClient->createProject([
            'artifacts' => [ // REQUIRED
                'location' => $artifacts_bucket, // output bucket
                'name' => '/',                   // name of output artifact object
                'namespaceType' => 'NONE',
                'packaging' => 'NONE',
                'path' => 'codebuild-output',         // path to output artifacts
                'type' => 'S3',                  // REQUIRED
            ],
            'cache' => $cache,
            'environment' => [ // REQUIRED
                'computeType' => 'BUILD_GENERAL1_SMALL', // REQUIRED
                'image' => self::getCodeBuildImageRepo() . ":" . self::getCodeBuildImageTag(), // REQUIRED
                'privilegedMode' => false,
                'type' => 'LINUX_CONTAINER', // REQUIRED
            ],
            'name' => $project_name, // REQUIRED
            'serviceRole' => $role_arn,
            'source' => $source,
        ]);
    }

    public static function getConsoleTextUrl($baseName, $guid)
    {
        $projectName = self::getCodeBuildProjectName($baseName);
        $region = getenv('BUILD_ENGINE_ARTIFACTS_BUCKET_REGION') ?: "us-east-1";
        $regionUrl = 'https://console.aws.amazon.com/cloudwatch/home?region=' . $region;
        $taskExtension = '#logEvent:group=/aws/codebuild/' . $projectName . ';stream=' . $guid;
        return $regionUrl . $taskExtension;

    }
    public static function getCodeBuildUrl($baseName, $guid)
    {
        $projectName = self::getCodeBuildProjectName($baseName);
        $region = getenv('BUILD_ENGINE_ARTIFACTS_BUCKET_REGION') ?: "us-east-1";
        $regionUrl = 'https://console.aws.amazon.com/codebuild/home?region=' . $region;
        $taskExtension = '#/builds/' . $projectName . ':' . $guid . '/view/new';
        return $regionUrl . $taskExtension;

    }
    /**
     * Checks to see if the current project exists
     *
     * @param string $baseName - Name of the project to search for
     * @return boolean true if project found
     */
    public function projectExists($baseName)
    {
        $projectName = self::getCodeBuildProjectName($baseName);
        echo "Check project " . $projectName . " exists" . PHP_EOL;
        $retVal = true;
        $result = $this->codeBuildClient->batchGetProjects([
            'names' => [
                $projectName
            ]
        ]);
        $projects = $result['projects'];
        if (count($projects) == 0) {
            $retVal = false;
        }
        return $retVal;
    }

    private function addEnvironmentToArray($environmentVariables, $environment)
    {
        if ($environment == 'null') {
            $environment = null;
        }
        if (!is_null($environment)) {
            try {
                $environmentArray = json_decode($environment);
                foreach ($environmentArray as $key => $value) {
                    $entry = [
                        'name' => $key,
                        'value' => $value,
                    ];
                    $environmentVariables[] = $entry;
                }
            } catch  (\Exception $e) {
                echo ("Exception caught and ignored");
            }
        }
        return $environmentVariables;
    }
}
