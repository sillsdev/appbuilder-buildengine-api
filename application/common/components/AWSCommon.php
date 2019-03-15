<?php

namespace common\components;

class AWSCommon
{
    public static function getArtifactsBucketRegion()
    {
        return \Yii::$app->params['buildEngineArtifactsBucketRegion'];
    }

    public static function getArtifactsBucket()
    {
        return \Yii::$app->params['buildEngineArtifactsBucket'];
    }

    public static function getAWSUserAccount()
    {
        return \YII::$app->params['awsUserId'];
    }

    public static function getAppEnv()
    {
        return \Yii::$app->params['appEnv'];
    }

    public static function getSecretsBucket()
    {
        return \Yii::$app->params['buildEngineSecretsBucket'];
    }

    public static function  getProjectsBucket()
    {
        return \Yii::$app->params['buildEngineProjectsBucket'];
    }

    public static function getCodeBuildImageTag()
    {
        return \Yii::$app->params['codeBuildImageTag'];
    }
    public static function getCodeBuildImage()
    {
        return \Yii::$app->params['codeBuildImage'];
    }

    public static function getBuildScriptPath()
    {
        $s3path = "s3://". AWSCommon::getProjectsBucket() .'/default';

        return $s3path;

    }
    public static function getArtifactPath($build, $productionStage)
    {
        $job = $build->job;
        $buildProcess = $job->nameForBuildProcess();
        $jobNumber = (string)$job->id;
        $artifactPath = $productionStage . '/jobs/' . $buildProcess . '_' . $jobNumber;
        return $artifactPath;
    }
    /**
     * Gets the base prefix for the s3 within the bucket
     *
     * @param Build $build Current build object
     * @param string $productStage - stg or prd
     * @return string prefix
     */
    public static function getBasePrefixUrl($build, $productStage) {
        $artifactPath = self::getArtifactPath($build, $productStage);
        $buildNumber = (string)$build->id;
        $repoUrl =  $artifactPath . "/" . $buildNumber;
        return $repoUrl;
    }
    /**
     *  Get the project name which is the prd or stg plus build_app or publish_app
     *
     * @param string $baseName build_app or publish_app
     * @return string app name
     */
    public static function getCodeBuildProjectName($baseName)
    {
        return ($baseName . "-" . self::getAppEnv());
    }

    public static function getRoleName($baseName)
    {
        return 'codebuild-' . $baseName . '-service-role-' . self::getAppEnv();
    }
}