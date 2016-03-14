<?php

namespace common\components;

use common\models\Build;
use common\components\JenkinsUtils;

use yii\web\ServerErrorHttpException;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class S3 {
    /**
     * Configure and get the S3 Client
     * @return \Aws\S3\S3Client
     */
    private static function getS3Client()
    {
        $client = new \Aws\S3\S3Client([
            'region' => 'us-west-2',
            'version' => '2006-03-01'
            ]);
        $client->registerStreamWrapper();
        return $client;
    }

    public static function saveBuildToS3($build, $artifactUrl, $versionCodeArtifactUrl)
    {
        $client = self::getS3Client();
        $apkS3Url = self::getS3Url($build, $artifactUrl);
        list ($apkS3bucket, $apkS3key) = self::getS3BucketKey($apkS3Url);
        echo "..copy:" .PHP_EOL .".... $artifactUrl" .PHP_EOL .".... $apkS3bucket $apkS3Url" .PHP_EOL;

        $apk = file_get_contents($artifactUrl);

        $client->putObject([
            'Bucket' => $apkS3bucket,
            'Key' => $apkS3key,
            'Body' => $apk,
            'ACL' => 'public-read'
        ]);

        $apkPublicUrl = $client->getObjectUrl($apkS3bucket, $apkS3key);

        $versionS3Url = self::getS3Url($build, $versionCodeArtifactUrl);
        list ($versionCodeS3bucket, $versionCodeS3key) = self::getS3BucketKey($versionS3Url);

        $versionCode = file_get_contents($versionCodeArtifactUrl);

        $client->putObject([
            'Bucket' => $versionCodeS3bucket,
            'Key' => $versionCodeS3key,
            'Body' => $versionCode,
            'ACL' => 'public-read'
        ]);

        return [$apkPublicUrl, $versionCode];
    }

    /**
     * Get the S3 Url to use to archive a build
     * @param Build $build
     * @param string $artifactUrl
     * @return string S3Url
     */
    public static function getS3Url($build, $artifactUrl)
    {
        $job = $build->job;
        return JenkinsUtils::getArtifactUrlBase()."/jobs/".$job->name()."/".$build->build_number."/".basename($artifactUrl);
    }

    /**
     * Get the S3 Bucket and Key to use to archive a build
     * @param string s3Url
     * @return [string,string] Bucket, Key
     */
    private static function getS3BucketKey($s3Url)
    {
        $pattern = '/s3:\/\/([^\/]*)\/(.*)$/';
        if (preg_match($pattern, $s3Url, $matches)){
            $bucket = $matches[1];
            $key = $matches[2];
            return [$bucket, $key];
        }

        throw new ServerErrorHttpException("Failed to match $s3Url", 1444051300);
    }
    /**
     * Remove the artifacts for this build saved in S3
     *
     * @param Build $build
     */
    public static function removeS3Artifacts($build) {
        $parts = parse_url($build->artifact_url);
        $apkpath = $parts['path'];
        $path = substr($apkpath, 0, strrpos( $apkpath, '/') + 1);
        $bucket = substr($path,1, strpos( $path, '/', 1) - 1);
        $key = substr($path, strpos($path, '/', 1) + 1);
        $s3 = self::getS3Client();
        $s3->deleteMatchingObjects($bucket, $key);
        echo "Deleted S3 bucket $bucket key $key " . PHP_EOL;
    }
}
