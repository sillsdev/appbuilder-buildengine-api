<?php

namespace tests\mock\common\components;

use Codeception\Util\Debug;
use tests\mock\aws\iam\MockIamClient;
use tests\mock\aws\codecommit\MockCodecommitClient;
use Aws\Iam\Exception\IamException;
use Aws\Iam\Exception\NoSuchEntityException;
use yii\web\ServerErrorHttpException;

class MockIAmWrapper
{
    public $groupNameParm;
    public $userNameParm;
    public $removeCalled = 0;

    function getIamClient(){
        return new MockIamClient();
    }
    function getCodeCommitClient(){
        return new MockCodecommitClient();
    }
    public function addUserToIamGroup($userName, $groupName)
    {
        $iamClient = $this->getIamClient();

        $result = $iamClient->addUserToGroup([
            'GroupName' => $groupName,
            'UserName' => $userName
        ]);

        return $result;
    }
    public function removeUserFromIamGroup($userName, $groupName)
    {
        $this->removeCalled += 1;
        $this->userNameParm = $userName;
        $this->groupNameParm = $groupName;
        $iamClient = $this->getIamClient();
        $result = $iamClient->removeUserFromGroup([
            'GroupName' => $groupName,
            'UserName' => $userName
        ]);
        return $result;
    }
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
                $message = sprintf('SAB: Unable to get users existing ssh key(s). code=%s : message=%s', $e->getCode(), $e->getMessage());
                throw new ServerErrorHttpException($message, 1441819828, $e);
            }
            $message = sprintf('SAB: Unable to add ssh key to user. code=%s : message=%s', $e->getCode(), $e->getMessage());
            throw new ServerErrorHttpException($message, 1441809827, $e);
        }
    }
    public function createAwsAccount($user_id)
    {
        $iamClient = $this->getIamClient();
        $user = $iamClient->createUser([
            'Path' => '/sab-codecommit-users/',
            'UserName' => $user_id
        ]);
        return $user;   
    }

}

