<?php

namespace console\components;

use console\components\ActionCommon;

use common\components\Appbuilder_logger;
use common\components\CodeBuild;
use common\components\IAmWrapper;

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
            $codeBuild = new CodeBuild();
            $iamWrapper = new IAmWrapper();
            // Build build role if necessary
            $buildProjectName = CodeBuild::getCodeBuildProjectName($projectName);
            if (!$codeBuild->projectExists($buildProjectName))
            {
                echo "  Creating build project " . $buildProjectName . PHP_EOL;
                // Project doesn't exist, build it
                if (!$iamWrapper->doesRoleExist($projectName))
                {
                    echo "  Creating role for " . $buildProjectName . PHP_EOL;
                    // Role doesn't exist make it
                    $roleArn = $iamWrapper->createRole($projectName);
                    $iamWrapper->attachRolePolicy($projectName, 's3-appbuilder-secrets');
                    $iamWrapper->attachRolePolicy($projectName, 's3-appbuilder-artifacts');
                    $iamWrapper->attachRolePolicy($projectName, 'codecommit-projects');
                    $iamWrapper->attachRolePolicy($projectName, 'codebuild-basepolicy-build_app');
                }
                // Create the codebuild project
                $cache =  [
                    'location' => CodeBuild::getArtifactsBucket() . '/codebuild-cache',
                    'type' => 'S3',
                ];
                $source = [
                    'buildspec' => 'version: 0.2',
                    'gitCloneDepth' => 1,
                    'location' => 'https://git-codecommit.us-east-1.amazonaws.com/v1/repos/sample',
                    'type' => 'CODECOMMIT',
                ];
                $roleArn = $iamWrapper->getRoleArn($projectName);
                $codeBuild->createProject($projectName, $roleArn, $cache, $source);
                echo "  Project created" . PHP_EOL;
            }
            // Build publish role if necessary
            $projectName = 'publish_app';
            echo "[$prefix] AwsStartupAction: create CodeBuild project: $projectName" . PHP_EOL;
            $publishProjectName = CodeBuild::getCodeBuildProjectName($projectName);
            if (!$codeBuild->projectExists($publishProjectName))
            {
                echo "  Creating build project " . $buildProjectName . PHP_EOL;

                // Project doesn't exist, build it
                if (!$iamWrapper->doesRoleExist($projectName))
                {
                    // Role doesn't exist make it
                    echo "  Creating role for " . $buildProjectName . PHP_EOL;
                    $roleArn = $iamWrapper->createRole($projectName);
                    $iamWrapper->attachRolePolicy($projectName, 's3-appbuilder-secrets');
                    $iamWrapper->attachRolePolicy($projectName, 's3-appbuilder-artifacts');
                    $iamWrapper->attachRolePolicy($projectName, 'codebuild-basepolicy-publish_app');
                }
                // Create the codebuild project
                $cache = [
                    'type' => 'NO_CACHE',
                ];
                $source = [
                    'buildspec' => 'version: 0.2',
                    'gitCloneDepth' => 1,
                    'location' =>  'arn:aws:s3:::prd-aps-artifacts/prd/jobs/build_scriptureappbuilder_1/1/sample.apk',
                    'type' => 'S3',
                ];
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
}
