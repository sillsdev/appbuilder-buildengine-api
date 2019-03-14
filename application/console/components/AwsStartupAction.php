<?php

namespace console\components;

use console\components\ActionCommon;

use common\components\Appbuilder_logger;
use common\components\CodeBuild;
use common\components\IAmWrapper;
use common\components\S3;

use common\helpers\Utils;

class AwsStartupAction extends ActionCommon
{
    /**
     * This method checks to see if the code build projects for build and publish exist
     * and builds them if they don't
     */
    public function performAction()
    {
        // Note:  In the future, we will need to change how Role is managed so that we
        //        conditionally add/remove role policies.  This will work for now.

        $logger = new Appbuilder_logger("AwsStartupAction");
        try {
            $projectName = 'build_app';
            $prefix = Utils::getPrefix();
            echo "[$prefix] AwsStartupAction: create CodeBuild project: $projectName" . PHP_EOL;
            $cacheLocation = S3::getArtifactsBucket() . '/codebuild-cache';
            $cache =  [
                'location' => $cacheLocation,
                'type' => 'S3',
            ];
            $source = [
                'buildspec' => 'version: 0.2',
                'gitCloneDepth' => 1,
                'location' => 'https://git-codecommit.us-east-1.amazonaws.com/v1/repos/sample',
                'type' => 'CODECOMMIT',
            ];
            $this->createProject($projectName, $cache, $source, $logger);

            // Build publish role if necessary
            // Copy default file
            $projectName = 'publish_app';
            $prefix = 'arn:aws:s3:::';
            $bucket = S3::getArtifactsBucket();
            $baseUrl = 'default/default.zip';
            $defaultLocation = $prefix . $bucket . "/" . $baseUrl;
            echo "Location: $defaultLocation" . PHP_EOL;
            echo "[$prefix] AwsStartupAction: create CodeBuild project: $projectName" . PHP_EOL;
            $this->copyDefaultProjectFolder($logger);
            $cache = [
                'type' => 'NO_CACHE',
            ];
            $source = [
                'buildspec' => 'version: 0.2',
                'gitCloneDepth' => 1,
                'location' =>  $defaultLocation,
                'type' => 'S3',
            ];
            $this->createProject($projectName, $cache, $source, $logger);

            $this ->copyBuildScriptFolder($logger);

        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] createCodeBuildProject: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = [
                'problem' => 'Failed to create CodeBuild Project',
                'projectName' => $projectName
            ];
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
    private function createProject($projectName, $cache, $source, $logger)
    {
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] AwsStartupAction: create CodeBuild project: $projectName" . PHP_EOL;
            $codeBuild = new CodeBuild();
            $iamWrapper = new IAmWrapper();
            if (!$codeBuild->projectExists($projectName))
            {
                echo "  Creating build project " . $projectName . PHP_EOL;

                $roleArn = $iamWrapper->getRoleArn($projectName);
                $codeBuild->createProject($projectName, $roleArn, $cache, $source);
                echo "  Project created" . PHP_EOL;
            }

        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] createCodeBuildProject: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = [
                'problem' => 'Failed to create CodeBuild Project',
                'projectName' => $projectName
            ];
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
    private function copyDefaultProjectFolder($logger)
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] AwsStartupAction: copyDefaultProjectFolder" . PHP_EOL;
        $sourceFolder = '/data/console/views/cron/scripts/project_default';
        $bucket = S3::getArtifactsBucket();
        $this->copyFolder($logger, $sourceFolder, $bucket);
    }
    private function copyBuildScriptFolder($logger)
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] AwsStartupAction: copyBuildScriptFolder" . PHP_EOL;
        $sourceFolder = '/data/console/views/cron/scripts/upload';
        $bucket = S3::getProjectsBucket();
        $this->copyFolder($logger, $sourceFolder, $bucket);
    }
    private function copyFolder($logger, $sourceFolder, $bucket)
    {
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] AwsStartupAction: copyFolder" . PHP_EOL;
            $s3 = new S3();
            $s3->uploadFolder($sourceFolder, $bucket);
            echo "  Copy completed" . PHP_EOL;
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] copyFolder: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = [
                'problem' => 'Failed to copy folder'
            ];
            $logger->appbuilderExceptionLog($logException, $e);
        }
    }
}
