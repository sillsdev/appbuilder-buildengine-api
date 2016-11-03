<?php
namespace frontend\controllers;

use common\models\Project;

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

//use frontend\components\JobControllerUtils;
/**
 * Job controller
 */
class ProjectController extends ActiveController
{
//    private $jcUtils;
    
    public function __construct($id, $module, $config = [])
    {
//        $this->jcUtils = new JobControllerUtils;
        parent::__construct($id, $module, $config);
    }
    public $modelClass = 'common\models\Project';

    public function actionIndexProjects() {
        $clientId = Project::getCurrentClientId();
        $projects = Project::findAllByClientId($clientId);
        if (!$projects) {
            throw new NotFoundHttpException();
        }
        return $projects;
    }
    public function actionViewProject($id) {
        $project = Project::findByIdFiltered($id);
        if (!$project) {
            throw new NotFoundHttpException("Project $id not found");
        }
        return $project;
    }
    public function actionDeleteProject($id) {
        $project = Project::findByIdFiltered($id);
        if (!$project) {
            throw new NotFoundHttpException("Project $id not found");
        }
        $project->status = Project::STATUS_DELETE_PENDING;
        $project->save();
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

