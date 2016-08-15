<?php
namespace tests\mock\console\controllers;

use Codeception\Util\Debug;

class MockCronController
{
    public $lastParms = [];
    function getViewPath()
    {
        return "/tmp/test/viewPath";
    }
    function renderPartial($path, $parms)
    {
        $this->lastParms = $parms;
        return "this should be a script";
    }
}

