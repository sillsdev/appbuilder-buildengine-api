<?php
namespace tests\mock\common\components;

use Codeception\Util\Debug;
use common\components\FileUtilsInterface;

class MockFileUtils
{
    public function file_get_contents($url)
    {
        $retString = "Contents of ".$url;

        // Version code get contents needs to return an integer
        if (strpos($url, 'version') !== false)
        {
            $retString = "42";
        }
        return $retString;
    }
}



