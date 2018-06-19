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

    public static function getArtifactPath($build, $productionStage)
    {
        $job = $build->job;
        $buildProcess = $job->nameForBuildProcess();
        $jobNumber = (string)$job->id;
        $artifactPath = $productionStage . '/jobs/' . $buildProcess . '_' . $jobNumber;
        return $artifactPath;
    }
}