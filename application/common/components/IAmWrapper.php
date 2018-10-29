<?php
namespace common\components;

use common\components\AWSCommon;
use Aws\CodeCommit\CodeCommitClient;
use Aws\Iam\IamClient;
use Aws\Iam\Exception\IamException;
use Aws\Iam\Exception\NoSuchEntityException;
use yii\helpers\Json;
use yii\web\ServerErrorHttpException;

class IAmWrapper extends AWSCommon
{
    public $iamClient;
    private $fileUtil;
    private $ut;
    public function __construct() {
        try {
            // Injected if Unit Test
            $this->iamClient = \Yii::$container->get('iAmClient');
            $this->ut = true;
        } catch (\Exception $e) {
            // Get real S3 client
            $this->iamClient = self::getIamClientNoCredentials();
            $this->ut = false;
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
        if ($this->ut == true) {
            return $this->iamClient;
        }
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
    /**
     * gets the iam user if it exists or creates one if it does not
     *
     * @param string $user_id - Project user id
     * @return User User from Iam;
     */
    public function createAwsAccount($user_id)
    {
        $iamClient = $this->getIamClient();

        try {
            $user = $iamClient->createUser([
                'Path' => '/sab-codecommit-users/',
                'UserName' => $user_id
            ]);

            return $user;
        } catch (IamException $e) {
            if($e->getAwsErrorCode() == 'EntityAlreadyExists') { // They already have an account - pass back their account
                $user = $iamClient->getUser([
                    'UserName' => $user_id
                ]);

                return $user;
            }

            $message = sprintf('SAB: Unable to create account. code=%s : message=%s', $e->getCode(), $e->getMessage());
            \Yii::error($message, 'service');
            throw new ServerErrorHttpException($message, 1437423331, $e);
        }
    }

    /**
     * adds a user to the specified IAM Group
     *
     * @param string $userName
     * @param string $groupName
     * @return IAM always returns empty array so that is what is being returned
     */
    public function addUserToIamGroup($userName, $groupName)
    {
        $iamClient = $this->getIamClient();

        $result = $iamClient->addUserToGroup([
            'GroupName' => $groupName,
            'UserName' => $userName
        ]);

        return $result;
    }
    /**
     * removes a user to the specified IAM Group
     *
     * @param string $userName
     * @param string $groupName
     * @return IAM always returns empty array so that is what is being returned
     */
    public function removeUserFromIamGroup($userName, $groupName)
    {
        $iamClient = $this->getIamClient();

        $result = $iamClient->removeUserFromGroup([
            'GroupName' => $groupName,
            'UserName' => $userName
        ]);

        return $result;
    }

    /**
     * adds the public key for a user to IAM
     *
     * @param string $username - The name of the user associated with the key
     * @param string $publicKey - The ssh key for the user
     * @return SSHPublicKey - See AWS API documentation
     */
    public function addPublicSshKey($username, $publicKey)
    {
        $iamClient = $this->getIamClient();
        try {
            $result = $iamClient->uploadSSHPublicKey([
                'SSHPublicKeyBody' => $publicKey,
                'UserName' => $username
            ]);

            return $result;
        } catch (IamException $e) {
            if($e->getAwsErrorCode()=='DuplicateSSHPublicKey'){
                try{
                    $keysForRequester = $iamClient->listSSHPublicKeys([
                        'UserName' => $username
                    ]);

                    foreach ($keysForRequester['SSHPublicKeys'] as $requesterKey) {
                        $key = $iamClient->getSSHPublicKey([
                            'UserName' => $username,
                            'SSHPublicKeyId' => $requesterKey['SSHPublicKeyId'],
                            'Encoding' => 'SSH'
                        ]);

                        if ($this->isEqual($key['SSHPublicKey']['SSHPublicKeyBody'], $publicKey)) {
                            return $key;
                        }
                    }

                    $message = sprintf('SAB: Unable to find a matching ssh key for user. code=%s : message=%s', $e->getCode(), $e->getMessage());
                    throw new ServerErrorHttpException($message, 1451819839);
                } catch (IamException $e) {
                    $message = sprintf('SAB: Unable to get users existing ssh key(s). code=%s : message=%s', $e->getCode(), $e->getMessage());
                    throw new ServerErrorHttpException($message, 1441819828, $e);
                }
            }

            $message = sprintf('SAB: Unable to add ssh key to user. code=%s : message=%s', $e->getCode(), $e->getMessage());
            throw new ServerErrorHttpException($message, 1441809827, $e);
        }
    }
   /**
     * used to determine whether two openSSH formatted keys are equal (without regard to comment portion of key)
     * @param $openSSHKey1 format expected:  type data comment
     * @param $openSSHKey2 format expected:  type data comment
     * @return bool
     */
    private function isEqual($openSSHKey1, $openSSHKey2) {
        list($type1, $data1) = explode(" ", $openSSHKey1);
        list($type2, $data2) = explode(" ", $openSSHKey2);

        if ($type1 == $type2 && $data1 == $data2) {
            return true;
        }

        return false;
    }
}
