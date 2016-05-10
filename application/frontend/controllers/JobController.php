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


/**
 * Job controller
 */
class JobController extends ActiveController
{
    public $modelClass = 'common\models\Job';

    public function actionIndexBuilds($id) {
        $this->validateJob($id);
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
        $job = Job::findByIdFiltered($id);
        if (!$job) {
            throw new NotFoundHttpException("Job $id not found");
        }
        return $job;
    }
    public function actionDeleteJob($id) {
        $job = Job::findByIdFiltered($id);
        if (!$job) {
            throw new NotFoundHttpException("Job $id not found");
        }
        $job->delete();
    }
    public function actionViewBuild($id, $build_id) {
        $this->validateJob($id);
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
        $this->validateJob($id);
        $build = Build::findOneById($id, $build_id);
        if (!$build){
            throw new NotFoundHttpException("Job $id Build $build_id not found");
        }
        $build->delete();
    }
    public function actionPublishBuild($id, $build_id) {
        $this->validateJob($id);
        $build = Build::findOneById($id, $build_id);
        if (!$build){
            throw new NotFoundHttpException("Job $id Build $build_id not found");
        }
        $artifactUrl = $build->artifact_url;
        if (is_null($artifactUrl) || ($artifactUrl=="")) {
            throw new ServerErrorHttpException("Artifact URL empty for Job $id Build $build_id");
        }
        $channel = \Yii::$app->request->getBodyParam('channel', null);
        $title = \Yii::$app->request->getBodyParam('title', null);
        $defaultLanguage = \Yii::$app->request->getBodyParam('defaultLanguage', null);
        $version_code = $build->version_code;

        $this->verifyChannel($id, $channel, $version_code);
        $release = $build->createRelease($channel);
        $release->title = $title;
        $release->defaultLanguage = $defaultLanguage;
        $release->save();

        return $release;
    }

    public function actionViewRelease($id, $build_id, $release_id) {
        $this->validateJob($id);
        $release = $this->lookupRelease($id, $build_id, $release_id);
        return $release;
    }

    public function actionDeleteRelease($id, $build_id, $release_id) {
        $this->validateJob($id);
        $release = $this->lookupRelease($id, $build_id, $release_id);
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

    /**
     * @param $id
     * @param $build_id
     * @param $release_id
     * @return null|static
     * @throws NotFoundHttpException
     */
    private function lookupRelease($id, $build_id, $release_id)
    {
        // Do we need to verify that the job id, build id are correct???
        $build = Build::findOne(['id' => $build_id, 'job_id' => $id]);
        if (!$build) {
            throw new NotFoundHttpException();
        }

        $release = Release::findOne(['id' => $release_id, 'build_id' => $build_id]);
        if (!$release) {
            throw new NotFoundHttpException();
        }
        return $release;
    }
/**
 * @param $id - The job id
 * @param $channel - The channel the build is being released to
 * @param $version_code - The version currently being released
 * @throws ServerErrorHttpException
 * $return true if not already released to another channel
 */
    private function verifyChannel($id, $channel, $version_code)
    {
        $retval = true;
        foreach (Build::find()->where([
            'job_id' => $id,
            'status' => Build::STATUS_COMPLETED,
            'result' => "SUCCESS"])->each(50) as $build){
               if ($build->channel && ($build->version_code)
                       && ($build->channel != Build::CHANNEL_UNPUBLISHED)
                       && ($build->channel != $channel)
                       && ($version_code == $build->version_code)) {
                    throw new ServerErrorHttpException("Job $id already released under channel $build->channel for version $version_code", 1453326645);            
                }
        }
        return $retval;
    }
    private function validateJob($id)
    {
       if (!$this->actionViewJob($id)) {
           throw new NotFoundHttpException("Job $id not found");
       }
    }
}
