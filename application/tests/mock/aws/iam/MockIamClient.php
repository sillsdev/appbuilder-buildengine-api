<?php
namespace tests\mock\aws\iam;

use Codeception\Util\Debug;
use tests\mock\aws\iam\MockIAmUser;
use tests\mock\aws\iam\MockIAmResult;
use Aws\Iam\Exception\IamException;

class MockIamClient
{
    public $parms;
    public static $uploadParms;
    function createUser($parms)
    {
        return new MockIamUser($parms);
    }
    function addUserToGroup($parms)
    {
        $this->parms = $parms;
        return new MockIamResult($parms);
    }
    function uploadSSHPublicKey($parms)
    {
        self::$uploadParms = $parms;
        if ($parms['UserName'] == "throw"){
            throw new IamException(
                "Text Exception",
                null,
                [
                    'code'             => 'DuplicateSSHPublicKey'
                ]
            );
        }
    }
}
