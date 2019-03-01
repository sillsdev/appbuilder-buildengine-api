<?php
namespace console\controllers;

use common\models\Build;
use common\models\EmailQueue;
use common\models\Job;
use common\components\S3;
use common\components\Appbuilder_logger;
use common\components\EmailUtils;
use common\components\FileUtils;
use common\components\IAmWrapper;

use console\components\AwsStartupAction;
use console\components\RemoveExpiredBuildsAction;
use console\components\ManageBuildsAction;
use console\components\ManageReleasesAction;
use console\components\ManageProjectsAction;
use console\components\OperationsQueueAction;
use console\components\DevelopmentAction;
use console\components\S3MaintenanceAction;
use console\components\SetInitialVersionCodeAction;

use yii\console\Controller;
use common\helpers\Utils;
use GitWrapper\GitWrapper;

class CronController extends Controller
{
    public function __construct($id, $module, $config = [])
    {
        \Yii::$container->set('fileUtils', 'common\components\FileUtils');
        \Yii::$container->set('gitWrapper', 'GitWrapper\GitWrapper');
        \Yii::$container->set('iAmWrapper', 'common\components\IAmWrapper');
        parent::__construct($id, $module, $config);
    }

    public function actionAwsStartup()
    {
        $awsStartup = new AwsStartupAction();
        $awsStartup->performAction();
    }

    /**
     * Manage the state of the builds and process the current state
     * until the status is complete.
     */
    public function actionManageBuilds()
    {
        $manageBuildsAction = new ManageBuildsAction($this);
        $manageBuildsAction->performAction();
    }

    /**
     * Manage the state of the releases and process the current state
     * until the status is complete.
     */
    public function actionManageReleases()
    {
        $manageReleasesAction = new ManageReleasesAction($this);
        $manageReleasesAction->performAction();
    }
    /**
     * Send queued emails
    */
    public function actionSendEmails($verbose=false)
    {
        $emailCount = EmailQueue::find()->count();

        if($emailCount && is_numeric($emailCount)){
            echo "cron/send-emails - Count: " . $emailCount . ". ". PHP_EOL;
        }

        list($emails_sent, $errors) = EmailUtils::sendEmailQueue();
        if (count($emails_sent) > 0) {
            echo "... sent=".count($emails_sent). PHP_EOL;
        }
        if ($errors && count($errors) > 0) {
            echo '... errors='.count($errors).' messages=['.join(',',$errors).']' . PHP_EOL;
        }
    }

    /**
     * Remove expired builds from S3
    */
    public function actionRemoveExpiredBuilds()
    {
        $removeExpiredBuildsAction = new RemoveExpiredBuildsAction();
        $removeExpiredBuildsAction->performAction();
    }

    /**
     * Delete orphaned files on S3
     */
    public function actionS3Maintenance()
    {
        $s3MaintenanceAction = new S3MaintenanceAction();
        $s3MaintenanceAction->performAction();
    }
    /**
     * Process operation_queue
     * This function will iterate up to the configured limit and for each iteration
     * it will process the next job in queue
     */
    public function actionOperationQueue($verbose=false)
    {
        $operationsQueueAction = new OperationsQueueAction($verbose);
        $operationsQueueAction->performAction();
    }
    /**
     * Test email action. Requires email adddress as parameter (Dev only)
     */
    public function actionTestEmail($sendToAddress)
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::TESTEMAIL, $sendToAddress);
        $developmentAction->performAction();
    }

    /**
     * Test AWS Build Status function (Dev Only)
     * Requires build guid as parameter
     */
    public function actionTestBuildStatus($buildGuid)
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::TESTAWSSTAT, $buildGuid);
        $developmentAction->performAction();
    }
    /**
     * Get Configuration (Dev only)
     */
    public function actionGetConfig()
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::GETCONFIG);
        $developmentAction->performAction();
    }
    /**
     * Return all the builds. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    public function actionGetBuilds()
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::GETBUILDS);
        $developmentAction->performAction();
    }
    /**
     * Return the builds that have not completed. (Dev only)
     * Note: This should only be used during developement for diagnosis.
     */
    public function actionGetBuildsRemaining()
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::GETREMAINING);
        $developmentAction->performAction();
    }
    /**
     * Get completed build information. (Dev only)
     * Note: This should only be used during development for diagnosis.
     */
    public function actionGetBuildsCompleted()
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::GETCOMPLETED);
        $developmentAction->performAction();
    }
    /**
     * Force the completed successful builds to upload the builds to S3. (Dev only)
     * Note: This should only be used during development to test whether
     *       S3 configuration is correct.
     */
    public function actionForceUploadBuilds()
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::FORCEUPLOAD);
        $developmentAction->performAction();
    }
    /**
     * Delete job.  Job ID required as parameter
     */
    public function actionDeleteJob($jobIdToDelete)
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::DELETEJOB, $jobIdToDelete);
        $developmentAction->performAction();
    }
    /**
     * Set initial version code. Job ID and the initial version code required as parameters
     * Example: ./yii cron/set-initial-version-code 1, 33
     */
    public function actionSetInitialVersionCode($jobId, $initialVersionCode)
    {
        $developmentAction = new SetInitialVersionCodeAction($jobId, $initialVersionCode);
        $developmentAction->performAction();
    }
    public function actionCheckCount()
    {
        $count = Job::recordCount();
        echo "Count:[$count]".PHP_EOL;
    }
    /**
     * Return information for project token. (Dev only)
     * Example: ./yii cron/project-token 1
     * Note: This should only be used during developement for diagnosis.
     */
    public function actionProjectToken($projectId)
    {
        $developmentAction = new DevelopmentAction(DevelopmentAction::GETPROJECTTOKEN, $projectId);
        $developmentAction->performAction();
    }

    /**
     * Manage the state of the creation of a project repo
     * until the status is complete.
     */
    public function actionManageProjects()
    {
        $manageProjectsAction = new ManageProjectsAction();
        $manageProjectsAction->performAction();
    }

}
