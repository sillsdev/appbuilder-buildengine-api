<?php
namespace frontend\controllers;

use common\models\Job;
use common\models\Build;

use yii\rest\ActiveController;

/**
 * Job controller
 */
class JobController extends ActiveController
{    
    public $modelClass = 'common\models\Job';
    
    public function actionLatestBuild($id) {
        $job = Job::findById($id);
        return $job->getLatestBuild();
    }
    
    public function actionViewBuild($id, $build_id) {
       $build = Build::findOne(['id' => $build_id, 'job_id' => $id]);
       if (!$build){
           throw new \yii\web\NotFoundHttpException();
       }
       return $build;
    }
}
