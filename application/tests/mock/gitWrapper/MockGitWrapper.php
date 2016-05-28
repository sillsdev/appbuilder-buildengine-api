<?php
namespace tests\mock\gitWrapper;

use tests\mock\GitWrapper\MockGitWorkingCopy;

use Codeception\Util\Debug;

class MockGitWrapper
{
    private static $url;
    public static function getTestUrl()
    {
        return self::$url;
    }
    private static $path;
    public static function getTestPath()
    {
        return self::$path;
    }
    private static $privateKey;
    public static function getTestPrivateKey()
    {
        return self::$privateKey;
    }
    private static $workingCopy;
    public static function getTestWorkingCopy()
    {
        return self::$workingCopy;
    }
    public static function resetTest()
    {
        self::$url = null;
        self::$path = null;
        self::$workingCopy = null;
        self::$privateKey = null;
    }
    public function setEnvVar($var, $value)
    {
        return $this;
    }
    public function setPrivateKey($privateKey, $port = 22, $wrapper = null)
    {
        self::$privateKey = $privateKey;
        return;
    }
     /**
     * Hackish, allows us to use "clone" as a method name.
     * Copied this directly from the real code 
     *
     * $throws \BadMethodCallException
     * @throws \GitWrapper\GitException
     */
    public function __call($method, $args)
    {
        if ('clone' == $method) {
            return call_user_func_array(array($this, 'cloneRepository'), $args);
        } else {
            $class = get_called_class();
            $message = "Call to undefined method $class::$method()";
            throw new \BadMethodCallException($message);
        }
    }
    public function cloneRepository($url, $path)
    {
        self::$url = $url;
        self::$path = $path;
        self::$workingCopy = new MockGitWorkingCopy($this, $path);
        return self::$workingCopy;
    }
    public function init($path)
    {
        self::$path = $path;
        self::$workingCopy = new MockGitWorkingCopy($this, $path);
        return self::$workingCopy;        
    }

}
