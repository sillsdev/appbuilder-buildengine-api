<?php

namespace common\components;

use common\models\Project;
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
        $credentials = $result['Credentials'];
        $credentials['Region'] = self::getArtifactsBucketRegion(); 
        return $credentials;
    }

    public function getProjectAccessToken($project, $externalId)
    {
        // https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sts-2011-06-15.html#getfederationtoken
        // AWS limits the name:
        //   The regex used to validate this parameter is a string of characters consisting of
        //   upper- and lower-case alphanumeric characters with no spaces. You can also include
        //   underscores or any of the following characters: =,.@-
        // https://docs.aws.amazon.com/STS/latest/APIReference/API_GetFederationToken.html
        // Max of 32 characters
        $externalIdParts = explode('|', $externalId);
        // Make sure valid characters are used
        $idPart = preg_replace('/[^a-zA-Z0-9_=,.@-]/', '_',end($externalIdParts));
        // Pad out name with randomness
        $random = bin2hex(openssl_random_pseudo_bytes(16));
        // Use max 32 characters
        $tokenName = substr($idPart . "." . $random,0,32);
        $policy = self::getPolicy($project);
        return $this->getFederationToken($tokenName, $policy);
    }

    /**
     * @param Project $project
     * @return string
     */
    public static function getPolicy($project)
    {
        // Note: s3 arns cannot contain region or account id
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
        $path = $project->getS3ProjectPath();
        $pathParts = explode('/', $path, 2);
        $policy = str_replace("BUCKET", $pathParts[0], $policy);
        $policy = str_replace("FOLDER", $pathParts[1], $policy);
        return $policy;
    }
}