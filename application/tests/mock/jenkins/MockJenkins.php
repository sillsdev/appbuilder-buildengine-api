<?php
namespace tests\mock\jenkins;

use Codeception\Util\Debug;
use tests\mock\jenkins\MockJenkinsJob;

class MockJenkins
{
    public function getBaseUrl()
    {
        return("http://127.0.0.1/S3/");
    }
    public function getJob($jobName)
    {
        $job = new MockJenkinsJob($this, false, 3, 1, $jobName);
        return $job;
    }
}

