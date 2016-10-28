<?php

namespace tests\mock\common\components;

use Codeception\Util\Debug;
use tests\mock\aws\iam\MockIamClient;

class MockIAmWrapper
{
    function getIamClient(){
        return new MockIamClient();
    }

}

