<?php

namespace tests\mock\common\components;

use Codeception\Util\Debug;
use tests\mock\jenkins\MockJenkins;

class MockJenkinsUtils
{
    public function getJenkins()
    {
        $jenkins = new MockJenkins();
        return $jenkins;
    }
    public function getPublishJenkins()
    {
        $jenkins = new MockJenkins();
        return $jenkins;
    }

}
