<?php

namespace tests\mock\common\components;

use Codeception\Util\Debug;
use tests\mock\aws\iam\MockIamClient;
use tests\mock\aws\iam\MockCodecommitClient;

class MockIAmWrapper
{
    function getIamClient(){
        return new MockIamClient();
    }
    function getCodeCommitClient(){
        return new MockCodecommitClient();
    }

}

