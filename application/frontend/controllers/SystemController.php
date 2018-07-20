<?php
namespace frontend\controllers;

use Yii;
use common\models\EmailQueue;
use yii\helpers\ArrayHelper;
use yii\rest\Controller;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\AccessControl;
use yii\web\ForbiddenHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * System controller
 */
class SystemController extends Controller
{
    public function actionCheck()
    {
        /**
         * Check for DB connection
         */
        try{
            EmailQueue::find()->all();
            return [ ];
        } catch (\Exception $e) {
            throw new ServerErrorHttpException("Unable to connect to db, error code ".$e->getCode(),$e->getCode());
        }
    }

    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'authenticator' => [
                'class' => CompositeAuth::className(),
                'authMethods' => [
                    HttpBearerAuth::className(), // Use header ... Authorization: Bearer abc123
                ],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'], // Any logged in user
                    ],
                ],
                'denyCallback' => function($rule, $action){
                    if(\Yii::$app->user->isGuest){
                        throw new UnauthorizedHttpException();
                    } else {
                        throw new ForbiddenHttpException();
                    }
                },
            ]
        ]);
    }

}
