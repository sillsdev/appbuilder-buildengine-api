<?php

namespace common\components;

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
            echo "$trace[$i]" . PHP_EOL;
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
        $this->outputToLogger($log, Logger::LEVEL_ERROR, "ERROR-");
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
        $logExceptionDetails = $this->get_stackTraceDetails($e);
        $logExceptionDetails['LINE NUMBER:'] = $e->getLine();
        $logExceptionDetails['MESSAGE:'] = $e->getMessage();
        $mergedLog = array_merge($logExceptionDetails, $log);
        $this->outputToLogger($mergedLog, Logger::LEVEL_ERROR, "EXCEPTION-");
    }
    
    /**
      *
      * Creates an warning log to be submitted to logentries.com
    */
    public function appbuilderWarningLog($log)
    {
        $this->outputToLogger($log, Logger::LEVEL_WARNING, "WARNING-");
    }
    
    /**
      *
      * Creates an info log to be submitted to logentries.com
    */
    public function appbuilderInfoLog($log)
    {
        //NOTE: rsyslog is not configured to log INFO level to avoid to many logs
        $this->outputToLogger($log, Logger::LEVEL_WARNING, "INFO-");
    }
    /**
    *
    * Creates a log to be submitted to logentries.com
    */
    public function outputToLogger($log, $level, $logtype)
    {
        $jenkinsUrl = \Yii::$app->params['buildEngineJenkinsMasterUrl'];
        $prefix = self::getPrefix();
        $callingFunction = $this->get_calling_function();
        echo PHP_EOL . " callingFunction is:" .PHP_EOL ." $callingFunction" . PHP_EOL . PHP_EOL;
        $logPrefix = [
            'date' => $prefix,
            'jenkinsUrl' => $jenkinsUrl,
            'functionAndClass' => $callingFunction
        ];
        $category = "$logtype" . "$this->area" . '-' . "$this->functionName";
        $mergedLog = array_merge($logPrefix, $log);
        \Yii::getLogger()->log($mergedLog, $level, $category);
    }
}
