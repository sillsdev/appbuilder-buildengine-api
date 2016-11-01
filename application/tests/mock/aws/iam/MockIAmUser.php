<?php
namespace tests\mock\aws\iam;

use Codeception\Util\Debug;

class MockIamUser
{
    public $parms = ["test", "test"];
    public function __construct($parms) {
        $this->parms = $parms;
    }
}