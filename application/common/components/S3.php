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
    public $s3Client;
    private $fileUtil;
    public function __construct() {
        try {
            // Injected if Unit Test
            $this->s3Client = \Yii::$container->get('s3Client'); 
        } catch (\Exception $e) {
            // Get real S3 client
            $this->s3Client = self::getS3Client();
        }
        $this->fileUtil = \Yii::$container->get('fileUtils');
    }

    /**
     * Configure and get the S3 Client
     * @return \Aws\S3\S3Client
     */
    public static function getS3Client()
    {
        $client = new \Aws\S3\S3Client([
            'region' => 'us-west-2',
            'version' => '2006-03-01'
            ]);
        $client->registerStreamWrapper();
        return $client;
    }
    public function saveErrorToS3($jobName, $buildNumber, $jenkins)
    {
        $errorUrl = $jenkins->getBaseUrl().sprintf('job/%s/%s/consoleText', $jobName, $buildNumber);
        $s3Url = self::getS3UrlBaseByNameNumber($jobName, $buildNumber).basename($errorUrl);
        list ($s3bucket, $s3key) = self::getS3BucketKey($s3Url);
        $consoleOutput = $this->fileUtil->file_get_contents($errorUrl);

        $this->s3Client->putObject([
            'Bucket' => $s3bucket,
            'Key' => $s3key,
            'Body' => $consoleOutput,
            'ACL' => 'public-read'
        ]);

        $publicUrl = $this->s3Client->getObjectUrl($s3bucket, $s3key);
        return $publicUrl;
    }

    /***
     * @param Build $build
     * @param array $artifactUrls
     * @param Array $extraContent
     * @throws ServerErrorHttpException
     */
    public function saveBuildToS3($build, $artifactUrls, $extraContent) {
        $s3baseUrl = self::getS3UrlBase($build);
        list ($baseS3Bucket, $baseS3Key) =  self::getS3BucketKey($s3baseUrl);
        $publicBaseUrl = $this->s3Client->getObjectUrl($baseS3Bucket, $baseS3Key);
        $build->beginArtifacts($publicBaseUrl);

        foreach ($artifactUrls as $url) {
            if (!is_null($url)) {
                $s3url =  self::getS3Url($build, $url);
                list ($fileS3Bucket, $fileS3Key) =  self::getS3BucketKey($s3url);

                echo "..copy:" .PHP_EOL .".... $url" .PHP_EOL .".... $fileS3Bucket $s3url" .PHP_EOL;
                echo "... Key: $fileS3Key ".PHP_EOL;

                $file = $this->fileUtil->file_get_contents($url);

                $this->s3Client->putObject([
                    'Bucket' => $fileS3Bucket,
                    'Key' => $fileS3Key,
                    'Body' => $file,
                    'ACL' => 'public-read',
                    'ContentType' => $this->getFileType($url)
                ]);

                $build->handleArtifact($fileS3Key, $file);
            }
        }

        if (!empty($extraContent)) {
            foreach ($extraContent as $filename => $content) {
                $s3url = self::getS3UrlBase($build) . $filename;
                list ($fileS3Bucket, $fileS3Key) = self::getS3BucketKey($s3url);
                $this->s3Client->putObject([
                    'Bucket' => $fileS3Bucket,
                    'Key' => $fileS3Key,
                    'Body' => $content,
                    'ACL' => 'public-read',
                    'ContentType' => $this->getFileType($filename)
                ]);
                $build->handleArtifact($fileS3Key, $content);
            }
        }
    }
    private function getFileType($fileName) {
        $info = pathinfo($fileName);
        switch ($info['extension']) {
            case "html":
                $contentType = "text/html";
                break;
            case "png":
                $contentType = "image/png";
                 break;
            case "jpg":
            case "jpeg":
               $contentType = "image/jpeg";
                break;
            case "txt":
                $contentType = "text/plain";
                break;
            default:
                $contentType = "application/octet-stream";
                break;
        }
        return $contentType;
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
        return self::getS3UrlByNameNumber($job->nameForBuild(), $build->build_number, $artifactUrl);
    }

    /**
     * Get the S3 Url to use to archive a build
     * @param Build $build
     * @return string S3Url
     */
    public static function getS3UrlBase($build) {
        $job = $build->job;
        return self::getS3UrlBaseByNameNumber($job->nameForBuild(), $build->build_number);
    }

    private static function getS3UrlBaseByNameNumber($name, $number) {
        return JenkinsUtils::getArtifactUrlBase()."/jobs/".$name."/".$number."/";
    }

    private static function getS3UrlByNameNumber($name, $number, $artifactUrl) {
        return self::getS3UrlBaseByNameNumber($name, $number) . self::getArtifactOutputFile($artifactUrl);
    }

    public static function getArtifactOutputFile($artifactUrl) {
        $parts = explode("artifact/output/", $artifactUrl);
        return $parts[1];
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
     * Removes any S3 job folder that doesn't have a corresponding
     * record in the db
     *
     * @param type $jobNames - Array of all of the job names
     */
    public function removeS3FoldersWithoutJobRecord($jobNames)
    {
        $logInfo = ["Checking for S3 files to delete"];
        // Strip s3:// off of the url base to get the bucket
        $urlBase = \Yii::$app->params['buildEngineArtifactUrlBase'];
        echo "URLBase $urlBase".PHP_EOL;
        $startPos = strpos($urlBase, '//') + 2;
        $bucket = substr($urlBase, $startPos, strlen($urlBase) - $startPos);

        // Create a list of all of the files in S3 in this bucket.
        $prefix = \Yii::$app->params['appEnv']."/jobs/";
        $s3FolderArray = self::getS3JobArray($bucket, $prefix);
        // Now check and see if a record exists in the job table for
        // those S3 folders and delete the S3 folder if not
        foreach ($s3FolderArray as $key => $value) {
            if (!array_key_exists($key, $jobNames))
            {
                $folderKey = $prefix.$key."/";
                echo ("Deleting S3 bucket: $bucket key: $folderKey").PHP_EOL;
                $this->s3Client->deleteMatchingObjects($bucket, $folderKey);
                $logString = "Deleted S3 bucket: $bucket key: $folderKey".PHP_EOL;
                $logInfo[] = $logString;
            }
        }
        return $logInfo;
    }

    private function getS3JobArray($bucket, $prefix)
    {
        echo "Bucket: $bucket Prefix: $prefix".PHP_EOL;
        $prefixLength = strlen($prefix);
        $results = $this->s3Client->getPaginator('ListObjects', [
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);
        // Create an array of just the jobs associated with those files
        $s3FolderArray = array();
        foreach ($results as $result) {
            foreach ($result['Contents'] as $object) {
                $key = $object['Key'];
                $build = substr($key, $prefixLength, strpos($key, '/', $prefixLength) - $prefixLength);
                if (!array_key_exists($build, $s3FolderArray))
                {
                    $s3FolderArray[$build] = 1;
                }
            }
        }
        return $s3FolderArray;
    }
    /**
     * Remove the artifacts for this build saved in S3
     *
     * @param Build $build
     */
    public function removeS3Artifacts($build) {
        $parts = parse_url($build->artifact_url_base);
        $path = $parts['path'];
        $bucket = substr($path,1, strpos( $path, '/', 1) - 1);
        $key = substr($path, strpos($path, '/', 1) + 1);
        $this->s3Client->deleteMatchingObjects($bucket, $key);
        echo "Deleted S3 bucket $bucket key $key " . PHP_EOL;
    }
}
