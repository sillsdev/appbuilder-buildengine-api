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
use yii\web\Response;
use yii\web\BadRequestHttpException;

use frontend\components\JobControllerUtils;
/**
 * Job controller
 */
class JobController extends ActiveController
{
    private $jcUtils;
    
    public function __construct($id, $module, $config = [])
    {
        $this->jcUtils = new JobControllerUtils;
        parent::__construct($id, $module, $config);
    }
    public $modelClass = 'common\models\Job';

    public function actionIndexBuilds($id) {
        $this->jcUtils->validateJob($id);
        $builds = Build::findAllActiveByJobId($id);
        if (!$builds){
            throw new NotFoundHttpException();
        }
        return $builds;
    }

    public function actionIndexJobs() {
        $clientId = Job::getCurrentClientId();
        $jobs = Job::findAllByClientId($clientId);
        if (!$jobs) {
            throw new NotFoundHttpException();
        }
        return $jobs;
    }
    public function actionViewJob($id) {
        return $this->jcUtils->viewJob($id);
    }
    public function actionDeleteJob($id) {
        $job = Job::findByIdFiltered($id);
        if (!$job) {
            throw new NotFoundHttpException("Job $id not found");
        }
        $job->delete();
    }
    public function actionViewBuild($id, $build_id) {
        $this->jcUtils->validateJob($id);
        $build = Build::findOneById($id, $build_id);
        if (!$build){
            throw new NotFoundHttpException("Job $id Build $build_id not found");
        }
        return $build;
    }

    public function actionNewBuild($id) {
       $job = Job::findByIdFiltered($id);
       if (!$job){
           throw new NotFoundHttpException("Job $id not found", 1443810472);
       }
       $build = $job->createBuild();
       if (!$build){
           throw new ServerErrorHttpException("Could not create Build for Job $id", 1443810508);
       }

       return $build;
    }
    public function actionDeleteBuild($id, $build_id) {
        $this->jcUtils->validateJob($id);
        $build = Build::findOneById($id, $build_id);
        if (!$build){
            throw new NotFoundHttpException("Job $id Build $build_id not found");
        }
        $build->delete();
    }
    public function actionPublishBuild($id, $build_id) {
        $channel = \Yii::$app->request->getBodyParam('channel', null);
        $title = \Yii::$app->request->getBodyParam('title', null);
        $defaultLanguage = \Yii::$app->request->getBodyParam('defaultLanguage', null);
        $release = $this->jcUtils->publishBuild($id, $build_id, $channel, $title, $defaultLanguage);
        return $release;
    }

    public function actionViewRelease($id, $build_id, $release_id) {
        $this->jcUtils->validateJob($id);
        $release = $this->jcUtils->lookupRelease($id, $build_id, $release_id);
        return $release;
    }

    public function actionDeleteRelease($id, $build_id, $release_id) {
        $this->jcUtils->validateJob($id);
        $release = $this->jcUtils->lookupRelease($id, $build_id, $release_id);
        $release->delete();
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
