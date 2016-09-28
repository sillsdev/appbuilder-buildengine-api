<?php
namespace frontend\components;

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
/**
 * Job controller utils
 * This class exists to make it possible to test job controller
 */
class JobControllerUtils
{
    public function viewJob($id) {
        $job = Job::findByIdFiltered($id);
        if (!$job) {
            throw new NotFoundHttpException("Job $id not found");
        }
        return $job;
    }
    public function publishBuild($id, $build_id, $channel, $title, $defaultLanguage) {
        $this->validateJob($id);
        $runningRelease = Release::findOne([
            'build_id' => $build_id,
            'status' => ["active", "accepted"]
             ]);
        if($runningRelease){
            throw new ServerErrorHttpException("Release $runningRelease->id already in progress for build $build_id");
        }
        $build = Build::findOneById($id, $build_id);
        if (!$build){
            throw new NotFoundHttpException("Job $id Build $build_id not found");
        }
        $artifactUrl = $build->apk();
        if (is_null($artifactUrl) || ($artifactUrl=="")) {
            throw new ServerErrorHttpException("Artifact URL empty for Job $id Build $build_id");
        }
        $version_code = $build->version_code;

        $verify_result = $this->verifyChannel($id, $channel, $version_code);
        $release = $build->createRelease($channel);
        $release->title = $title;
        $release->defaultLanguage = $defaultLanguage;
        $release->promote_from = $verify_result;
        $release->save();

        return $release;
    }
    /**
     * @param $id
     * @param $build_id
     * @param $release_id
     * @return null|static
     * @throws NotFoundHttpException
     */
    public function lookupRelease($id, $build_id, $release_id)
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
     * $return null if not already released to another channel
     *         else return channel to promote from
     */
    public function verifyChannel($id, $channel, $version_code)
    {
        $retval = null;
        $last_build = null;
        $highestPublishedChannel = Build::CHANNEL_UNPUBLISHED;
        foreach (Build::find()->where([
            'job_id' => $id,
            'status' => Build::STATUS_COMPLETED,
            'result' => "SUCCESS"])->each(50) as $build){
                $last_build = $build;
                if ($build->channel && ($build->version_code)
                       && ($build->channel != Build::CHANNEL_UNPUBLISHED)
                       && ($version_code == $build->version_code)) {
                   $highestPublishedChannel = $this->getHighestPublishedChannel($highestPublishedChannel, $build);
                }
        }
        if (($highestPublishedChannel != $channel)
                && ($highestPublishedChannel != Build::CHANNEL_UNPUBLISHED)) {
            if ($build->isValidChannelTransition($channel, $highestPublishedChannel)) {
                $retval = $highestPublishedChannel;
            } else {
               throw new ServerErrorHttpException("Job $id already released under channel $build->channel for version $version_code", 1453326645);                                       
            }
        }
        return $retval;
    }
    public function validateJob($id)
    {
       if (!$this->viewJob($id)) {
           throw new NotFoundHttpException("Job $id not found");
       }
    }
    private function getHighestPublishedChannel($old, $build)
    {
        $retVal = $old;
        if ( ! $build->isValidChannelTransition($old)) {
            $retVal = $build->channel;
        }
        return $retVal;
    }
}
