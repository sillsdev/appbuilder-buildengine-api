<?php
namespace frontend\controllers;

use common\components\STS;
use common\models\OperationQueue;
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
    private $sts;
    
    public function __construct($id, $module, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->sts = new STS();
    }
    public $modelClass = 'common\models\Project';

    public function actionNewProject() {
        $storage_type = \Yii::$app->request->getBodyParam('storage_type', null);
        $project = new Project();
        $project->load(\Yii::$app->request->post(), '');

        if ($storage_type === "s3") {
            // Need to save to initialize id field.  Need to make sure that
            // the status is marked complete so that manage projects won't
            // happen to catch it here and try to create codecreate repository
            $project->status = Project::STATUS_COMPLETED; // TODO: Remove when git is no longer supported
            $project->save();
            $project->setS3Project();
        }
        $project->save();
        return $project;
    }
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
    public function actionModifyProject($id) {
        $project = Project::findByIdFiltered($id);
        if (!$project) {
            throw new NotFoundHttpException("Project $id not found");
        }
        $publishing_key = \Yii::$app->request->getBodyParam('publishing_key', null);
        $user_id = \Yii::$app->request->getBodyParam('user_id', null);
        $url = $project->url;
        if (($publishing_key == null) || ($user_id == null))
        {
            throw new BadRequestHttpException("Publishing key or user id not set");
        }
        if ($url == null)
        {
            throw new BadRequestHttpException("Attempting to modify project with no url");
        }
        // Start modify operation
        $task = OperationQueue::UPDATEPROJECT;
        $project_id = $project->id;
        $parms = $publishing_key . ',' . $user_id;
        OperationQueue::findOrCreate($task, $project_id, $parms);
        return $project;
    }
    public function actionCreateToken($id) {
        $name = \Yii::$app->request->getBodyParam('name', null);
        $readOnly = \Yii::$app->request->getBodyParam('read_only', false);

        $project = Project::findByIdFiltered($id);
        if (!$project->isS3Project()) {
            throw new BadRequestHttpException("Attempting to get token for wrong project type");
        }

        return $this->sts->getProjectAccessToken($project, $name, $readOnly);
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

