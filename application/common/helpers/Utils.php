<?php

namespace common\helpers;

use yii\base\Security;


class Utils
{

    const DT_Format = 'Y-m-d H:i:s';

    public static function getDatetime($timestamp=false)
    {
        $timestamp = $timestamp ?: time();

        return date(self::DT_Format,$timestamp);
    }

    public static function getIso8601($timestamp=false)
    {
        $timestamp = $timestamp ?: time();
        return date('c', strtotime($timestamp));
    }

    public static function generateRandomString($length=32)
    {
        $security = new Security();
        return $security->generateRandomString($length);
    }

    public static function isArrayEntryTruthy($array, $key)
    {
        return (is_array($array) && isset($array[$key]) && $array[$key]);
    }

    /**
     * Check if user is logged in and if so return the identity model
     * @return null|\common\models\User
     */
    public static function getCurrentUser()
    {
        try {
            if(\Yii::$app->user && !\Yii::$app->user->isGuest){
                return \Yii::$app->user->identity;
            }
        } catch (\Exception $e) {
        }

        return null;
    }
    public static function getPrefix()
    {
        return date('Y-m-d H:i:s');
    }
    /**
     * Convert spaces to hyphens and remove all other non letter/number characters
     * @param $string
     * @return mixed
     */
    public static function lettersNumbersHyphensOnly($string)
    {
        /**
         * Convert spaces to hyphens first
         */
        $string = str_replace(" ","-",$string);

        /**
         * Remove all other non letters/numbers
         */
        $string = preg_replace("/[^a-zA-Z0-9\-]/","",$string);

        return $string;
    }
    /**
     * Delete a folder and all files and subfolders it contains
     * @param $dir Path to directory
     */
    public static function deleteDir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir."/".$object))
                    self::deleteDir($dir."/".$object);
                else
                    unlink($dir."/".$object);
                }
            }
            rmdir($dir);
        }
    }
    public static function createGUID()
    {
        if (function_exists('com_create_guid') === true)
        {
            return trim(com_create_guid(), '{}');
        }
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }
}