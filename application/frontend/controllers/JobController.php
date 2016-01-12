<?php
namespace frontend\controllers;

use common\models\Job;
use common\models\Build;
use common\models\Release;

use yii\rest\ActiveController;
use yii\helpers\ArrayHelper;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\AccessControl;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;
use yii\web\BadRequestHttpException;


/**
 * Job controller
 */
class JobController extends ActiveController
{
    public $modelClass = 'common\models\Job';

    public function actionIndexBuilds($id) {
       $builds = Build::findAll(['job_id' => $id]);
       if (!$builds){
           throw new NotFoundHttpException();
       }
       return $builds;
    }

    public function actionViewBuild($id, $build_id) {
       $build = Build::findOne(['id' => $build_id, 'job_id' => $id]);
       if (!$build){
           throw new NotFoundHttpException();
       }
       return $build;
    }

    public function actionNewBuild($id) {
       $job = Job::findById($id);
       if (!$job){
           throw new NotFoundHttpException("Job $id not found", 1443810472);
       }
       $build = $job->createBuild();
       if (!$build){
           throw new ServerErrorHttpException("Could not create Build for Job $id", 1443810508);
       }

       return $build;
    }

    public function actionPublishBuild($id, $build_id) {
        $build = Build::findOne(['id' => $build_id, 'job_id' => $id]);
        if (!$build){
            throw new NotFoundHttpException();
        }

        $channel = \Yii::$app->request->getBodyParam('channel', null);
        $title = \Yii::$app->request->getBodyParam('title', null);
        $defaultLanguage = \Yii::$app->request->getBodyParam('defaultLanguage', null);

        $release = $build->createRelease($channel);
        $release->title = $title;
        $release->defaultLanguage = $defaultLanguage;
        $release->save();

        return $release;
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
