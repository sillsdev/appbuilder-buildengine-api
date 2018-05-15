<?php

namespace common\components;

use common\models\Build;
use JenkinsApi\Jenkins;
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

    public static function getArtifactsBucket()
    {
        return \Yii::$app->params['buildEngineArtifactsBucket'];
    }

    public static function getAppEnv()
    {
        return \Yii::$app->params['appEnv'];
    }

    public static function getArtifactsBucketRegion()
    {
        return \Yii::$app->params['buildEngineArtifactsBucketRegion'];
    }

    /**
     * Configure and get the S3 Client
     * @return \Aws\S3\S3Client
     */
    public static function getS3Client()
    {
        $client = new \Aws\S3\S3Client([
            'region' => S3::getArtifactsBucketRegion(),
            'version' => '2006-03-01'
            ]);
        $client->registerStreamWrapper();
        return $client;
    }

    /**
     * @param string $jobName
     * @param string $buildNumber
     * @param Jenkins $jenkins
     * @return array
     */
    public function saveConsoleTextToS3($jobName, $buildNumber, $jenkins)
    {
        $errorUrl = $jenkins->getBaseUrl().sprintf('job/%s/%s/consoleText', $jobName, $buildNumber);
        $s3bucket = S3::getArtifactsBucket();
        $s3key = self::getS3KeyBaseByNameNumber($jobName, $buildNumber).basename($errorUrl);
        $consoleOutput = $this->fileUtil->file_get_contents($errorUrl);

        $this->s3Client->putObject([
            'Bucket' => $s3bucket,
            'Key' => $s3key,
            'Body' => $consoleOutput,
            'ACL' => 'public-read',
            'ContentType' => "text/plain"
        ]);

        $publicUrl = $this->s3Client->getObjectUrl($s3bucket, $s3key);
        return array($publicUrl, $s3key);
    }

    /***
     * @param Build $build
     * @param array $artifactUrls
     * @param array $artifactRelativePaths
     * @param array $extraContent
     * @throws ServerErrorHttpException
     */
    public function saveBuildToS3($build, $artifactUrls, $artifactRelativePaths, $extraContent) {
        $baseS3Key = self::getS3KeyBase($build);
        $baseS3Bucket = S3::getArtifactsBucket();
        $publicBaseUrl = $this->s3Client->getObjectUrl($baseS3Bucket, $baseS3Key);
        $build->beginArtifacts($publicBaseUrl);

        // There maybe nulled out entries in the arrays (so for ($i=0; $i<count(); ++i) won't work
        // Using array_map allows the parallel arrays to be itererated at the same time
        foreach (array_map(null, $artifactUrls, $artifactRelativePaths) as list($url, $relativePath)) {
            if (!is_null($url)) {
                $fileS3Bucket = S3::getArtifactsBucket();
                $fileS3Key =  self::getS3Key($build, $relativePath);

                echo "..copy:" .PHP_EOL .".... $url" .PHP_EOL;
                echo "... Bucket: $fileS3Bucket".PHP_EOL;
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
                $fileS3Key = self::getS3KeyBase($build) . $filename;
                $this->s3Client->putObject([
                    'Bucket' => S3::getArtifactsBucket(),
                    'Key' => $fileS3Key,
                    'Body' => $content,
                    'ACL' => 'public-read',
                    'ContentType' => $this->getFileType($filename)
                ]);
                $build->handleArtifact($fileS3Key, $content);
            }
        }
        $jenkinsUtils = \Yii::$container->get('jenkinsUtils');
        $jenkins = $jenkinsUtils->getJenkins();
        list(, $s3Key) = $this->saveConsoleTextToS3($build->jobName(), $build->build_number, $jenkins);
        $build->handleArtifact($s3Key, null);

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
     * Get the S3 Key to use to archive a build
     * @param Build $build
     * @param string $relativePath
     * @return string S3Key
     */
    public static function getS3Key($build, $relativePath)
    {
        $job = $build->job;
        return self::getS3KeyByNameNumber($job->nameForBuild(), $build->build_number, $relativePath);
    }

    /**
     * Get the S3 Key to use to archive a build
     * @param Build $build
     * @return string S3Key
     */
    public static function getS3KeyBase($build) {
        $job = $build->job;
        return self::getS3KeyBaseByNameNumber($job->nameForBuild(), $build->build_number);
    }

    private static function getS3KeyBaseByNameNumber($name, $number) {
        return self::getAppEnv()."/jobs/".$name."/".$number."/";
    }

    private static function getS3KeyByNameNumber($name, $number, $relativePath) {
        return self::getS3KeyBaseByNameNumber($name, $number) .$relativePath;
    }
    
    /**
     * Removes any S3 job folder that doesn't have a corresponding
     * record in the db
     *
     * @param array $jobNames - Array of all of the job names
     */
    public function removeS3FoldersWithoutJobRecord($jobNames)
    {
        $logInfo = ["Checking for S3 files to delete"];
        $bucket = S3::getArtifactsBucket();
        echo "ArtifactsBucket $bucket".PHP_EOL;

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
