<?php
namespace common\components;

use common\components\AWSCommon;
use Aws\CodeCommit\CodeCommitClient;
use Aws\Iam\IamClient;
use yii\helpers\Json;

class IAmWrapper extends AWSCommon
{
    public $iamClient;
    private $fileUtil;
    public function __construct() {
        try {
            // Injected if Unit Test
            $this->iamClient = \Yii::$container->get('iAmClient');
        } catch (\Exception $e) {
            // Get real S3 client
            $this->iamClient = self::getIamClientNoCredentials();
        }
        $this->fileUtil = \Yii::$container->get('fileUtils');
    }
    function getIamClientNoCredentials(){
        $awsRegion = \Yii::$app->params['awsRegion'];
        return IamClient::factory([
            'region' => $awsRegion,
            'version' => '2010-05-08',
        ]);
    }

    function getIamClient(){
        $awsKey = \Yii::$app->params['awsKeyId'];
        $awsSecret = \Yii::$app->params['awsSecretKey'];
        $awsRegion = \Yii::$app->params['awsRegion'];
        return IamClient::factory([
            'region' => $awsRegion,
            'version' => '2010-05-08',
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecret,
            ]
        ]);        
    }
    function getCodecommitClient()
    {
        $awsKey = \Yii::$app->params['awsKeyId'];
        $awsSecret = \Yii::$app->params['awsSecretKey'];
        $awsRegion = \Yii::$app->params['awsRegion'];

        return CodeCommitClient::factory([
            'region' => $awsRegion,
            'version' => '2015-04-13',
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecret,
            ]
        ]);
    }

    /**
     * Determines whether the role for the specified project
     * and the current production stage exists
     *
     * @param string $projectName - base project name, i.e. build_app or publish_app
     * @return boolean - true if role associated with base project exists for this production stage
     */
    public function doesRoleExist($projectName)
    {
        try
        {
            $fullRoleName = self::getRoleName($projectName);
            echo "Check role " . $fullRoleName . " exists" . PHP_EOL;
            $result = $this->iamClient->getRole([
                'RoleName' => $fullRoleName, // REQUIRED
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    /**
     * This method returns the role arn
     * @param string $projectName - base project name, i.e. build_app or publish_app
     * @return string arn for role
     */
    public function getRoleArn($projectName)
    {
        try
        {
            $fullRoleName = self::getRoleName($projectName);
            $result = $this->iamClient->getRole([
                'RoleName' => $fullRoleName,
            ]);
            $role = $result['Role'];
            $roleArn = $role['Arn'];
            echo 'Role Arn is ' . $roleArn . PHP_EOL;
            return $roleArn;
        } catch (\Exception $e) {
            return "";
        }
    }
    /**
     * Creates the appropriate role for the specified project for the current production stage
     *
     * @param string $projectName - base project name, i.e. build_app or publish_app
     * @return string arn for the role that was created
     */
    public function createRole($projectName)
    {
        $trustPolicyDocument = [
            'Version' => "2012-10-17",
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Principal' => [
                    'Service' => 'codebuild.amazonaws.com'
                    ],
                    'Action' => 'sts:AssumeRole'
                ]
            ]
        ];
        $fullRoleName = self::getRoleName($projectName);
        echo "create Role " . $fullRoleName . PHP_EOL;
        $result = $this->iamClient->createRole([
            'AssumeRolePolicyDocument' => Json::encode($trustPolicyDocument),
            'Path' => '/',
            'RoleName' => $fullRoleName,
        ]);
        $role = $result['Role'];
        $roleArn = $role['Arn'];
        return $roleArn;
    }

    public function attachRolePolicy($projectName, $policyName)
    {
        $fullRoleName = self::getRoleName($projectName);
        $policyArn = self::getPolicyArn($policyName);
        echo 'Attaching ' . $policyArn . ' to ' . $fullRoleName . PHP_EOL;
        $result = $this->iamClient->attachRolePolicy([
            'PolicyArn' => $policyArn,
            'RoleName' => $fullRoleName,
        ]);
    }
    /**
     * gets the iam arn of a specific iam policy
     *
     * @param string base policy name, e.g. s3-appbuild-secrets
     * @return string arn for policy
     */
    public static function getPolicyArn($basePolicyName) {
        $prefix = 'arn:aws:iam::';
        $userId = self::getAWSUserAccount();
        $productStage = self::getAppEnv();
        $arn = $prefix . $userId . ":policy/" . $basePolicyName . "-" . $productStage;
        return $arn;
    }

}
