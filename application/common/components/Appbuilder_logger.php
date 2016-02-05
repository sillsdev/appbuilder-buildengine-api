<?php

namespace common\components;

//use common\models\Job;
use common\models\Build;
use common\models\Release;

use JenkinsApi\Jenkins;
use JenkinsApi\Item\Build as JenkinsBuild;
//use JenkinsApi\Item\Job as JenkinsJob;

use Yii;
use yii\log\Logger;

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

class Appbuilder_logger {
    
    var $area = "";
    var $functionName = '';
    
    /**
     * @param string $area
     */
    public function __construct($area) {
         $this->area = $area;
    }
    
    function echo_stackTrace(\Exception $e){
        $trace = explode("\n", $e->getTraceAsString());
        $length = count($trace);
         for ($i = 0; $i < $length; $i++)
        {
            echo "$trace[$i]\n";
        }
    }
    
    function get_stackTraceDetails($e){
        $trace = $e->getTrace();
        $traceDetails = [
            'stackTrace' => 'first lines of trace',
            'stackTrace0' => $trace[0],
            'stackTrace1' => $trace[1],
            'stackTrace2' => $trace[2],
            'stackTrace3' => $trace[3],
            'stackTrace4' => $trace[4]
        ];
        return $traceDetails;
    }
    
    /**
    * Returns the calling function through a backtrace
    */
    function get_calling_function() {
      // a funciton x has called a function y which called this
      // see stackoverflow.com/questions/190421
      $backtrace = debug_backtrace();
      $caller = $backtrace[3];
      $this->functionName = $caller['function'];
      $r = $this->functionName . '()';
      if (isset($caller['class'])) {
        $r .= ' in ' . $caller['class'];
      }
      if (isset($caller['object'])) {
        $r .= ' (' . get_class($caller['object']) . ')';
      }
      return $r;
    }
    
    private static function getPrefix()
    {
        return date('Y-m-d H:i:s');
    }
    
    
    /**
      *
      * Creates an error log to be submitted to logentries.com
    */
    public function appbuilderErrorLog($log)
    {
        $this->outputToLogger($log, Logger::LEVEL_ERROR);
    }
    
    /**
      *
      * Creates an error log to be submitted to logentries.com
     * 
     * @param array $log
     * @param \Exception $e
    */
    public function appbuilderExceptionLog($log, $e)
    {
        $this->echo_stackTrace($e);
        $log = $this->get_stackTraceDetails($e);
        $log['LogType'] = 'EXCEPTION';
        $log['LINE NUMBER:'] = $e->getLine();
        $log['MESSAGE:'] = $e->getMessage();
        $this->outputToLogger($log, Logger::LEVEL_ERROR);
    }
    
    /**
      *
      * Creates an warning log to be submitted to logentries.com
    */
    public function appbuilderWarningLog($log)
    {
        $this->outputToLogger($log, Logger::LEVEL_WARNING);
    }
    
    /**
      *
      * Creates an info log to be submitted to logentries.com
    */
    public function appbuilderInfoLog($log)
    {
        $this->outputToLogger($log, Logger::LEVEL_INFO);
    }
    /**
    *
    * Creates a log to be submitted to logentries.com
    */
    public function outputToLogger($log, $level)
    {
        $jenkinsUrl = \Yii::$app->params['buildEngineJenkinsMasterUrl'];
        $prefix = self::getPrefix();
        $callingFunction = $this->get_calling_function();
        echo "\n callingFunction is:\n $callingFunction\n\n";
        $logPrefix = [
            'date' => $prefix,
            'jenkinsUrl' => $jenkinsUrl,
            'functionAndClass' => $callingFunction
        ];
        $category = "$this->area" . '-' . "$this->functionName";
        $mergedLog = array_merge($logPrefix, $log);
        \Yii::getLogger()->log($mergedLog, $level, $category);
    }
}
