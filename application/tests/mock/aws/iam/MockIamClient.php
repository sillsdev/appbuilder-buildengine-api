<?php
namespace tests\mock\aws\iam;

use Codeception\Util\Debug;
use tests\mock\aws\iam\MockIAmUser;
use tests\mock\aws\iam\MockIAmResult;
use Aws\Iam\Exception\IamException;
use Aws\Command;

class MockIamClient
{
    public $parms;
    public static $getGroupParms;
    public static $uploadParms;
    public static $listParms;
    public static $getKeyParms;
    function createUser($parms)
    {
        $this->parms = $parms;
        $metadata = [
            'Path' => $parms['Path'],
            'UserName' => $parms['UserName']
        ];
        return([
            'User' => $metadata
        ]);
    }
    function addUserToGroup($parms)
    {
        $this->parms = $parms;
        return new MockIamResult($parms);
    }
    function removeUserFromGroup($parms)
    {
        $this->parms = $parms;
        return new MockIamResult([]);
    }
    function uploadSSHPublicKey($parms)
    {
        self::$uploadParms = $parms;
        if (($parms['UserName'] == "throwKey") || ($parms['UserName'] == "throwKeyTwice")){
            throw new IamException(
                "Text Exception",
                new Command('foo'),
                [
                    'code'             => 'DuplicateSSHPublicKey'
                ]
            );
        }
        $key = array(
            'UserName' => $parms['UserName'],
            'SSHPublicKey' => array(
                'SSHPublicKeyId' => 'AABBCC',
                'Encoding' => 'SSH',
                'SSHPublicKeyBody' => $parms['SSHPublicKeyBody']
            )
        );
        return $key;
    }
    function getGroup($parms)
    {
        self::$getGroupParms = $parms;
        $group = $parms['GroupName'];
        
    }
    function listSSHPublicKeys($parms)
    {
        self::$listParms = $parms;
        $retVals = [];
        $retVals['UserName'] = $parms['UserName'];
        $keys = [];
        $key = [
            'SSHPublicKeyId' => 'AABBCC'
        ];
        $keys[] = $key;
        $retVals['SSHPublicKeys'] = $keys;
        return $retVals;
    }
    function getSSHPublicKey($parms)
    {
        self::$getKeyParms = $parms;
        if ($parms['UserName'] == "throwKeyTwice"){
            throw new IamException(
                "Text Exception",
                new Command('foo'),
                [
                    'code'             => 'CantSave'
                ]
            );
        }
        $key = array(
            'UserName' => $parms['UserName'],
            'SSHPublicKey' => array(
                'SSHPublicKeyId' => 'AABBCC',
                'Encoding' => 'SSH',
                'SSHPublicKeyBody' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDUUF0zmTHs+/qbQvFY3cDhh8IFzWqgnx0fS+GXMVCyH3M+10Tb5Gqt4hUodWgSszEAZCNg9nYlxmxQI/kkFcmFAYueXoSN6x2Z4lJRDsDItDeOAPcXQkwHbr9WdCymxLXiHCQcLbXLYrTnc0uiyaPXVq0IVFULEAWOIzynjjxd0O34hwc+mANxqFQt3ogvYXDRPcwJZO9gAHsu2igF+0LrgNfhpOXKtOc1qkSKWzX7HXLEfQUSAI1ps9mXwSf6cSAXzRUO3cYHmnf6Ttz0T3azhzhUp3u4ei1///GKzJAP5aQKBsrO4hMSnIxRpmPKRhvScJmYqqusTEuTl6jSvH/b hubbard@swd-hubbard-nx'
            )
        );
        return $key;
    }

}
