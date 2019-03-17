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

class STS extends AWSCommon
{
    public $stsClient;

    public function __construct()
    {
        try {
            // Injected if Unit Test
            $this->stsClient = \Yii::$container->get('stsClient');
        } catch (\Exception $e) {
            // Get real STS client
            $this->stsClient = self::getStsClient();
        }
     }

    /**
     * Configure and get the S3 Client
     * @return \Aws\Sts\StsClient
     */
    public static function getStsClient()
    {
        $client = new \Aws\Sts\StsClient([
            'region' => S3::getArtifactsBucketRegion(),
            'version' => '2011-06-15'
        ]);
        return $client;
    }

    /**
     * @param string $name - name of federated user
     * @param string $policy - IAM policy in json format
     * @return array - array of credentials needed for using AWS resources
     */
    public function getFederationToken($name, $policy)
    {
        $result = $this->stsClient->GetFederationToken([
            'Name' => $name,
            'Policy' => $policy
        ]);

        return $result['Credentials'];
    }

    public function getProjectAccessToken($project, $name)
    {
        $policy = self::getPolicy($project);
        return $this->getFederationToken($name, $policy);
    }

    public static function getPolicy($project)
    {
        $policy = '{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": "s3:ListBucket",
            "Resource": "arn:aws:s3:::BUCKET",
            "Condition": {
                "StringLike": {
                    "s3:prefix": [
                        "FOLDER/",
                        "FOLDER/*"
                    ]
                }
            }
        },
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:GetObjectAcl",
                "s3:PutObjectAcl",
                "s3:GetObjectTagging",
                "s3:PutObjectTagging",
                "s3:DeleteObject", 
                "s3:DeleteObjectVersion",
                "s3:PutLifeCycleConfiguration"
            ],
            "Resource": [
                "arn:aws:s3:::BUCKET/FOLDER",
                "arn:aws:s3:::BUCKET/FOLDER/*"
            ]
        }
    ]
}';

        $policy = str_replace("BUCKET", $project->getS3Bucket(), $policy);
        $policy = str_replace("FOLDER", $project->getS3Folder(), $policy);
        return $policy;
    }
}