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

}