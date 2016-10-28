<?php
namespace common\components;

use Aws\CodeCommit\CodeCommitClient;
use Aws\Iam\IamClient;

class IAmWrapper
{
    function getIamClient(){
        $awsKey = \Yii::$app->params['awsKeyId'];
        $awsSecret = \Yii::$app->params['awsSecretKey'];
        $awsRegion = \Yii::$app->params['awsRegion'];
        return IamClient::factory([
            'region' => $awsRegion,
            'version' => '2010-05-08',
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecret,
            ]
        ]);        
    }
    function getCodecommitClient()
    {
        $awsKey = \Yii::$app->params['awsKeyId'];
        $awsSecret = \Yii::$app->params['awsSecretKey'];
        $awsRegion = \Yii::$app->params['awsRegion'];

        return CodeCommitClient::factory([
            'region' => $awsRegion,
            'version' => '2015-04-13',
            'credentials' => [
                'key' => $awsKey,
                'secret' => $awsSecret,
            ]
        ]);
    }
}
