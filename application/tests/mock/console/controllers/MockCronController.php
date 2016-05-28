<?php
namespace tests\mock\console\controllers;

use Codeception\Util\Debug;

class MockCronController
{
    function getViewPath()
    {
        return "/tmp/test/viewPath";
    }
    function renderPartial($path, $parms)
    {
        return "this should be a script";
    }
}

