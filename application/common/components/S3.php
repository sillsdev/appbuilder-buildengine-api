<?php

namespace common\components;

use common\models\Build;
use JenkinsApi\Jenkins;
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
            'region' => S3::getArtifactsBucketRegion(),
            'version' => '2006-03-01'
            ]);
        $client->registerStreamWrapper();
        return $client;
    }

    /**
     * Gets the base prefix for the s3 within the bucket
     * 
     * @param Build $build Current build object
     * @return string prefix 
     */
    public function getBasePrefixUrl($build, $productStage) {
        $artifactPath = self::getArtifactPath($build, $productStage);
        $buildNumber = (string)$build->id;
        $repoUrl =  $artifactPath . "/" . $buildNumber . "/" ;
        return $repoUrl;
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
            $filePath =  $this->getBasePrefixUrl($build, 'codebuild-output') . $fileName;
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
        $sourcePrefix = $this->getBasePrefixUrl($build, 'codebuild-output');
        $destPrefix = $this->getBasePrefixUrl($build, self::getAppEnv());
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
        $destPrefix = $this->getBasePrefixUrl($build, self::getAppEnv());
        $fileS3Key = $destPrefix . $fileName;

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
        $s3Folder = $this->getBasePrefixUrl($build, 'codebuild-output');
        $s3Bucket = S3::getArtifactsBucket();
        echo ("Deleting S3 bucket: $s3Bucket key: $s3Folder").PHP_EOL;
        $this->s3Client->deleteMatchingObjects($s3Bucket, $s3Folder);
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
