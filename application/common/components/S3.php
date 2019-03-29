<?php

namespace common\components;

use common\models\Build;
use yii\web\ServerErrorHttpException;
use common\components\AWSCommon;
use Aws\Exception\AwsException;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class S3 extends AWSCommon{
    public $s3Client;
    private $fileUtil;
    private $ut;
    public function __construct() {
        try {
            // Injected if Unit Test
            $this->s3Client = \Yii::$container->get('s3Client');
            $this->ut = true;
        } catch (\Exception $e) {
            // Get real S3 client
            $this->s3Client = self::getS3Client();
            $this->ut = false;
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
            'region' => S3::getArtifactsBucketRegion(),
            'version' => '2006-03-01'
            ]);
        $client->registerStreamWrapper();
        return $client;
    }
    public function getS3ClientWithCredentials(){
        if ($this->ut == true) {
            return $this->s3Client;
        }
        $awsKey = \Yii::$app->params['awsKeyId'];
        $awsSecret = \Yii::$app->params['awsSecretKey'];
        $client = new \Aws\S3\S3Client([
            'region' => S3::getArtifactsBucketRegion(),
            'version' => '2006-03-01',
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecret,
            ]
        ]);
        return $client;
    }

    /**
     * gets the s3 arn of a specific file
     *
     * @param Build $build Current build object
     * @param string $productStage - stg or prd
     * @param string $filename - Name of s3 file that arn is requested for
     * @return string prefix 
     */
    public static function getS3Arn($build, $productStage, $filename) {
        $prefix = 'arn:aws:s3:::';
        $bucket = self::getArtifactsBucket();
        $baseUrl = self::getBasePrefixUrl($build, $productStage);
        $arn = $prefix . $bucket . "/" . $baseUrl . "/";
        if (!empty($filename)) {
            $arn = $arn . $filename;
        }
        return $arn;
    }
    /**
     * This function reads a file from the build output
     * 
     * @param Build $build Current build object
     * @param string $fileName Name of the file without path
     * @return string Contains the contents of the file
     */
    public function readS3File($build, $fileName){
        $fileContents = "";
        try {
            $filePath =  self::getBasePrefixUrl($build, 'codebuild-output') . "/" . $fileName;
            $result = $this->s3Client->getObject([
                'Bucket' => self::getArtifactsBucket(),
                'Key' => $filePath,
            ]);
            $fileContents = $result['Body'];
        } catch (AwsException $e) {
            // There is not a good way to check for file exists.  If file doesn't exist,
            // it will be caught here and an empty string returned.
            echo "readS3File: exception caught. Type: " . $e->getAwsErrorType() . PHP_EOL;
        }
        return $fileContents;
    }

    /**
     * copyS3Folder copies the files from where they have been saved encrypted in s3 by codebuild
     * to the final unencrypted artifacts folder. 
     * NOTE: This move is required because the initial codebuild version encrypts the files
     * with a key and there is no option to build them without encryption.
     * 
     * @param Build $build - The build associated with the successful build
     */
    public function copyS3Folder($build){
        $artifactsBucket = self::getArtifactsBucket();
        $sourcePrefix = self::getBasePrefixUrl($build, 'codebuild-output') . "/";
        $destPrefix = self::getBasePrefixUrl($build, self::getAppEnv()) . "/";
        $baseS3Key = self::getS3KeyBase($build);
        $publicBaseUrl = $this->s3Client->getObjectUrl($artifactsBucket, $destPrefix);
        $build->beginArtifacts($publicBaseUrl);
        $result = $this->s3Client->listObjectsV2([
            'Bucket' => $artifactsBucket,
            'Prefix' => $sourcePrefix, 
        ]);
        $fileCount = $result['KeyCount'];
        $files = $result['Contents'];
        foreach ($files as $file) {
            $this->copyS3File($file, $sourcePrefix, $destPrefix, $build);
        }
    }
    /**
     * This method copies a single file from the encrypted source archive to
     * the unencrypted destination archive
     * 
     * @param AWS/File $file - AWS object for source file
     * @param string $sourcePrefix - The AWS path to the source folder
     * @param string $destPrefix - The AWS path to the destination folder
     * @param Build $build - Successful build associated with the copy
     */
    public function copyS3File($file, $sourcePrefix, $destPrefix, $build) {
        $artifactsBucket = self::getArtifactsBucket();
        $fileContents="";
        $fileNameWithPrefix = $file['Key'];
        $fileName = substr($fileNameWithPrefix, strlen($sourcePrefix));
        switch ($fileName) {
            case 'manifest.txt':
                return;
            case 'play-listing/default-language.txt':
                return;
            //case: 'version.json': FUTURE: get versionCode from version.json
            case 'version_code.txt':
                $fileContents = (string)$this->readS3File($build, $fileName);
                break;
            default:
                echo $fileName . PHP_EOL;
                break;
        }
        $sourceFile = $artifactsBucket . '/' . $fileNameWithPrefix;
        $destinationFile = $destPrefix . $fileName;
        $contentType = $this->getFileType($fileName);
        $return = $this->s3Client->copyObject([
            'Bucket' => $artifactsBucket,
            'CopySource' => $sourceFile,
            'Key' => $destinationFile,
            'ACL' => 'public-read',
            'ContentType' => $this->getFileType($fileName),
            'MetadataDirective' => 'REPLACE',
            ]);
        $build->handleArtifact($destinationFile, $fileContents);
    }

    public function writeFileToS3($fileContents, $fileName, $build) {
        $fileS3Bucket = self::getArtifactsBucket();
        $destPrefix = self::getBasePrefixUrl($build, self::getAppEnv());
        $fileS3Key = $destPrefix . "/" . $fileName;

        $this->s3Client->putObject([
            'Bucket' => $fileS3Bucket,
            'Key' => $fileS3Key,
            'Body' => $fileContents,
            'ACL' => 'public-read',
            'ContentType' => $this->getFileType($fileName)
        ]);

        $build->handleArtifact($fileS3Key, $fileContents);
    }
    public function removeCodeBuildFolder($build) {
        $s3Folder = self::getBasePrefixUrl($build, 'codebuild-output') . "/";
        $s3Bucket = S3::getArtifactsBucket();
        echo ("Deleting S3 bucket: $s3Bucket key: $s3Folder").PHP_EOL;
        $this->s3Client->deleteMatchingObjects($s3Bucket, $s3Folder);
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
            case "log":
                $contentType = "text/plain";
                break;
            case "json":
                $contentType = "application/json";
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
        return self::getS3KeyBaseByNameNumber($job->nameForBuild(), $build->id);
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
    private function getS3ProjectArray($bucket, $prefix)
    {
        echo "Bucket: $bucket Prefix: $prefix".PHP_EOL;
        $client = $this->getS3ClientWithCredentials();
        $prefixLength = strlen($prefix);
        $results = $client->getPaginator('ListObjects', [
            'Bucket' => $bucket,
            'Prefix' => $prefix
        ]);
        // Create an array of just the projects associated with those files
        $s3FolderArray = array();
        foreach ($results as $result) {
            $contents = $result['Contents'];
            if ($contents == null) {
                continue;
            }
            foreach ($contents as $object) {
                $key = $object['Key'];
                $build = substr($key, $prefixLength + 1, strpos($key, '/', $prefixLength + 1) - ($prefixLength + 1));
                if (!(is_null($build) || empty($build)))
                {
                    if (!array_key_exists($build, $s3FolderArray))
                    {
                        $s3FolderArray[$build] = 1;
                    }
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
    /**
     * Copy a folder to s3
     */
    public function uploadFolder($folderName, $bucket, $keyPrefix = null) {
        $client = $this->getS3ClientWithCredentials();
        $client->uploadDirectory($folderName, $bucket, $keyPrefix);
    }

    public function doesObjectExist($bucket, $key, $project) {
        $client = $this->getS3ClientWithCredentials();
        $exists = false;
        $s3FolderArray = $this->getS3ProjectArray($bucket, $key);
        if (array_key_exists($project, $s3FolderArray))
        {
            $exists = true;
        }
        return $exists;
    }
}
