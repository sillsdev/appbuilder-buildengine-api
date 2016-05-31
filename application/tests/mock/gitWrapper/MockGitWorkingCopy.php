<?php
namespace tests\mock\gitWrapper;

use Codeception\Util\Debug;

class MockGitWorkingCopy
{
    private static $exceptionOnCheckout = false;
    public static function setExceptionOnCheckout($value)
    {
        self::$exceptionOnCheckout = $value;
    }
    public static $lastAdded;
    public static $lastRemoved;
    public static $lastCommitLine;
    private $gitWrapper;
    private $directory;
    public $envParms = [];
    public $checkoutBranch;
    public $fetchAllCount = 0;
    public $checkoutNewBranchCount = 0;
    public $checkoutCount = 0;
    public $resetCount = 0;
    public $lastResetBranch;
    public function __construct($gitWrapper, $directory)
    {
        $output = new \Codeception\Lib\Console\Output([]);
        $output->writeln('Constructor');
        $this->gitWrapper = $gitWrapper;
        $this->directory = $directory;
    }
    public function config($var, $value)
    {
        $this->envParms[$var] = $value;
        return;
    }
    public function fetchAll()
    {
        $this->fetchAllCount += 1;
        return;
    }
    public function reset($options, $branch)
    {
        $this->resetCount += 1;
        $this->lastResetBranch = $branch;
        return;
    }
    public function checkout($branch)
    {
        if (self::$exceptionOnCheckout)
        {
            self::$exceptionOnCheckout = false;
            throw new \Exception("Exception on checkout", 1450216434);
        }
        $this->checkoutCount += 1;
        $this->checkoutBranch = $branch;
        return;
    }
    public function checkoutNewBranch($branch)
    {
        $this->checkoutNewBranchCount += 1;
        $this->checkoutBranch = $branch;
        return;
    }
    public function add($filename)
    {
        self::$lastAdded = $filename;
        return;
    }
    public function hasChanges()
    {
        return true;
    }
    public function commit($commitString)
    {
        self::$lastCommitLine = $commitString;
        return;
    }
    public function push()
    {
        return;
    }
    public function getStatus($path)
    {
        return true;
    }
    public function rm($filename)
    {
        self::$lastRemoved = $filename;
        return;
    }
}
