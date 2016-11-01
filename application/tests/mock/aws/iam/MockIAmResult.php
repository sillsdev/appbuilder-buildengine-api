<?php
namespace tests\mock\aws\iam;

use Codeception\Util\Debug;

class MockIamResult
{
    public $parms = ["test", "test"];
    public function __construct($parms) {
        $this->parms = $parms;
    }
}