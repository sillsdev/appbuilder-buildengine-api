<?php

namespace common\components;

class FileUtils
{
    public function file_get_contents($url)
    {
        return file_get_contents($url);
    }
    public function copy($file, $to_file)
    {
        return copy($file, $to_file);
    }
    public function opendir($path)
    {
        return opendir($path);
    }
    public function file_exists($path)
    {
        return file_exists($path);
    }
    public function mkdir ($pathname, $mode = 0777, $recursive = false)
    {
        return mkdir($pathname, $mode, $recursive);
    }
    public function readdir($dir_handle)
    {
        return readdir($dir_handle);
    }
    public function is_dir($file)
    {
        return is_dir($file);
    }
    public function closedir($dir_handle)
    {
        return closedir($dir_handle);
    }
    public function fopen($filename, $mode)
    {
        return fopen($filename, $mode);
    }
    public function fwrite($file, $string)
    {
        return fwrite($file, $string);
    }
    public function fclose($file_handle)
    {
        return fclose($file_handle);
    }
}
