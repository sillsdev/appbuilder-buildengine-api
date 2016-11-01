<?php
namespace tests\mock\aws\iam;

use Codeception\Util\Debug;
use tests\mock\aws\iam\MockIAmUser;
use tests\mock\aws\iam\MockIAmResult;
use Aws\Iam\Exception\IamException;
use Aws\Command;

class MockIamClient
{
    public static $createParms;
    public static $deleteParms;
    function createRepository($parms){
        self::$createParms = $parms;
        $arn = "arn:aws:codecommit:us-east-1:908798193266:".$parms['repositoryName'];
        $ssh = "ssh://APKAIBOUHWAB6ZBENOCA@git-codecommit.us-east-1.amazonaws.com/v1/repos/".$parms['repositoryName'];
        $repoInfo = array(
            'repositoryMetadata' => array(
                'cloneUrlSsh' => $ssh,
                'repositoryName' => $parms['repositoryName'],
                'Arn' => $arn
            )
        );
        return $repoInfo;
    }
    function deleteRepository($parms){
        self::$deleteParms = $parms;
    }
}
