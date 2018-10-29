<?php
namespace tests\mock\common\components;

use Codeception\Util\Debug;
use common\components\FileUtilsInterface;

class MockAppbuilder_logger
{
    var $area = "";
    var $functionName = '';
    
    /**
     * @param string $area
     */
    public function __construct($area) {
         $this->area = $area;
    }
    public function appbuilderErrorLog($log)
    {
    }
    public function appbuilderExceptionLog($log, $e)
    {
    }
    public function appbuilderWarningLog($log)
    {
    }
    public function appbuilderInfoLog($log)
    {
    }
    public function outputToLogger($log, $level, $logtype)
    {
    }
}