<?php
namespace tests\mock\common\components;

use Codeception\Util\Debug;
use common\components\FileUtilsInterface;

class MockFileUtils
{
    private static $fileExistsVar = true;
    public static function setFileExistsVar($value){
        self::$fileExistsVar = $value;
    }
    private $readdirArray1 = [".","..","Helper.groovy"];
    private $readdirArray2 = [".","..","google.groovy","jobs.groovy","keystore.groovy"];
    private $readdirArray = null;
    private $readCount = 0;
    public function file_get_contents($url)
    {
        $retString = "Contents of ".$url;

        // Version code get contents needs to return an integer
        if (strpos($url, 'version') !== false)
        {
            $retString = "42";
        }
        if (strpos($url, 'default-language') !== false)
        {
            $retString = "es-419";
        }
        return $retString;
    }
    public function copy($file, $to_file)
    {
        return true;
    }
    public function opendir($path)
    {
        if (is_null($this->readdirArray))
        {
            $this->readdirArray = $this->readdirArray1;
        } else {
            $this->readdirArray = $this->readdirArray2;
        }
        $this->readCount = 0;
        return $path;
    }
    public function file_exists($path)
    {
        $retVal = self::$fileExistsVar;
        self::$fileExistsVar = true;
        return $retVal;
    }
    public function mkdir ($pathname, $mode = 0777, $recursive = false)
    {
        return true;
    }
    public function readdir($dir_handle)
    {
        $retval = false;
        if (!($this->readCount + 1 > count($this->readdirArray)))
        {
            $retval = $this->readdirArray[$this->readCount];
            $this->readCount += 1;
        }
        return $retval;
    }
    public function is_dir($file)
    {
        return false;
    }
    public function closedir($dir_handle)
    {
        return;
    }
    public function fopen($filename, $mode)
    {
        return $filename;
    }
    public function fwrite($file, $string)
    {
        return;
    }
    public function fclose($file_handle)
    {
        return;
    }
}



