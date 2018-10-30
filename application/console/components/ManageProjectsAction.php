<?php
namespace console\components;

use common\models\Project;
use common\components\Appbuilder_logger;
use common\components\CodeBuild;
use common\components\IAmWrapper;
use Aws\CodeCommit\CodeCommitClient;
use Aws\Iam\IamClient;
use Aws\Iam\Exception\IamException;
use Aws\Iam\Exception\NoSuchEntityException;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\web\ServerErrorHttpException;

use console\components\ActionCommon;

use common\helpers\Utils;
use yii\web\NotFoundHttpException;

class ManageProjectsAction extends ActionCommon
{
    private $iAmWrapper;
    public function __construct()
    {
        $this->iAmWrapper = \Yii::$container->get('iAmWrapper');
    }
    public function performAction()
    {
        $tokenSemaphore = sem_get(30);
        $tokenValue = shm_attach(31, 100);

        if (!$this->try_lock($tokenSemaphore, $tokenValue)){
            $prefix = Utils::getPrefix();
            echo "[$prefix] ManageProjectsAction: Semaphore Blocked" . PHP_EOL;
            return;
        }
        try {
            $logger = new Appbuilder_logger("ManageProjectsAction");
            $complete = Project::STATUS_COMPLETED;
            foreach (Project::find()->where("status!='$complete'")->each(50) as $project){
                echo "cron/manage-projects: Project=$project->id, ". PHP_EOL;
                $logProjectDetails = $this->getlogProjectDetails($project);
                $logger->appbuilderWarningLog($logProjectDetails);
                switch ($project->status){
                    case Project::STATUS_INITIALIZED:
                        $this->tryCreateRepo($project, $logger);
                        break;
                    case Project::STATUS_DELETE_PENDING:
                        $this->tryDeleteRepo($project);
                        break;
                }
            }
        }
        catch (\Exception $e) {
            echo "Caught exception".PHP_EOL;
            echo $e->getMessage() .PHP_EOL;
            echo $e->getFile() . PHP_EOL;
            echo $e->getLine() . PHP_EOL;
            $logger = new Appbuilder_logger("ManageProjectsAction");
            $logException = [
            'problem' => 'Caught exception',
                ];
            $logger->appbuilderExceptionLog($logException, $e);
         }
        finally {
            $this->release($tokenSemaphore, $tokenValue);
        }
    }
    /*===============================================  logging ============================================*/
    /**
     *
     * get release details for logging.
     * @param Release $release
     * @return Array
     */
    public function getlogProjectDetails($project)
    {
        $projectName = $project->project_name;
        $log = [
            'projectName' => $projectName,
            'projectId' => $project->id
        ];
        $log['Project-Status'] = $project->status;
        $log['Project-Result'] = $project->result;

        echo "Project=$project->id, Status=$project->status, Result=$project->result". PHP_EOL;

        return $log;
    }
    /**
     *
     * @param Release $release
     */
    private function tryCreateRepo($project, $logger)
    {
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryCreateRepo: Starting creation of project ".$project->project_name. PHP_EOL;

            $project->status = Project::STATUS_ACTIVE;
            $project->save();
            $repoName = $project->repoName();
            $user = $this->iAmWrapper->createAwsAccount($project->user_id);
            $this->findOrCreateIamCodeCommitGroup($project);
            $this->iAmWrapper->addUserToIamGroup($project->user_id, $project->groupName());
            $public_key = $this->iAmWrapper->addPublicSshKey($project->user_id, $project->publishing_key);
            $publicKeyId = $public_key['SSHPublicKey']['SSHPublicKeyId'];

            /*
             * Only attempt to create repo if one has not already been created for this project.
             * This prevents creating a second repo if the project name has changed
             */
            // TODO: THINK ABOUT HOW WE FIND OUT IF PROJECT REPO CREATED IF NECESSARY
            if ( ! is_null($project->url)) {
                if (!($project->url == "")){
                    return;
                }
            }
            $repo = $this->createRepo($repoName);
            $repoSshUrl = $this->addUserToSshUrl($repo['repositoryMetadata']['cloneUrlSsh'], $publicKeyId);
            echo "Username: ".$user['User']['UserName'].PHP_EOL;
            echo "RepoName: ".$repo['repositoryMetadata']['repositoryName'].PHP_EOL;
            echo "RepoUrl:  ".$repoSshUrl.PHP_EOL;
            echo "Arn:      ".$repo['repositoryMetadata']['Arn'].PHP_EOL;
            echo "Key ID:   ".$publicKeyId.PHP_EOL;
            $project->url = $repoSshUrl;
            $project->status = Project::STATUS_COMPLETED;
            $project->result = Project::RESULT_SUCCESS;
            $project->save();
            
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryCreateRepo: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = $this->getlogProjectDetails($project);
            $logger->appbuilderExceptionLog($logException, $e);
            $project->error = "File: ".$e->getFile()." Line: ".$e->getLine()." ".$e->getMessage();
            $project->status = Project::STATUS_COMPLETED;
            $project->result = Project::RESULT_FAILURE;
            $project->save();
       }
    }
    private function tryDeleteRepo($project, $logger)
    {
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryDeleteRepo: Starting Delete of project ".$project->project_name. PHP_EOL;
            if (! is_null($project->url)){
                $project->status = Project::STATUS_DELETING;
                $project->save();
                $repoName = $this->constructRepoName($project);
                $CCClient = $this->iAmWrapper->getCodecommitClient();
                $CCClient->deleteRepository([
                    'repositoryName' => $repoName,
                ]);
            }
            $project->delete();
            echo "[$prefix] tryDeleteRepo: Delete of project complete ". PHP_EOL;
        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryDeleteRepo: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = $this->getlogProjectDetails($project);
            $logger->appbuilderExceptionLog($logException, $e);
            $project->error = "File: ".$e->getFile()." Line: ".$e->getLine()." ".$e->getMessage();
            $project->status = Project::STATUS_COMPLETED;
            $project->result = Project::RESULT_FAILURE;
            $project->save();
       }
    }
    /**
     * Create IAM Group for organization for access management to repo
     * @param string $groupName
     * @param string $entityCode
     * @return array
     * @throws Aws\Iam\Exception\LimitExceededException
     * @throws Aws\Iam\Exception\EntityAlreadyExistsException
     * @throws Aws\Iam\Exception\NoSuchEntityException
     * @throws Aws\Iam\Exception\ServiceFailureException
     * @throws Aws\Iam\Exception\MalformedPolicyDocumentException
     */
    private function findOrCreateIamCodeCommitGroup($project)
    {
        $iamClient = $this->iAmWrapper->getIamClient();
        $groupName = $project->groupName();
        $entityCode = $project->entityName();
        try {
            $iamClient->getGroup([
                'GroupName' => $groupName,
            ]);
        } catch (NoSuchEntityException $e) {
            $this->createIamCodeCommitGroup($iamClient, $groupName, $entityCode, $project->app_id);
        } catch (IamException $e) {
            if ($e->getAwsErrorCode() == "NoSuchEntity") {
                $this->createIamCodeCommitGroup($iamClient, $groupName, $entityCode, $project->app_id);
            }
        }
    }
    private function createIamCodeCommitGroup($iamClient, $groupName, $entityCode, $app_id){
        /**
         * Group doesn't exist yet, so create it and attach policy
         */
        $awsUserId = \Yii::$app->params['awsUserId'];
        $awsRegion = \Yii::$app->params['awsRegion'];
        $result = $iamClient->createGroup([
            'Path' => '/',
            'GroupName' => $groupName,
        ]);
        $policy = [
            'Version' => '2012-10-17',
            'Statement' => [
                [
                    'Effect' => 'Allow',
                    'Action' => [
                        'codecommit:GitPull',
                        'codecommit:GitPush'
                    ],
                    'Resource' => [
                        'arn:aws:codecommit:'.$awsRegion.':'.$awsUserId.':'.$app_id.'-'.$entityCode.'-*'
                    ]
                ]
            ]
        ];
        $result = $iamClient->putGroupPolicy([
            'GroupName' => $groupName,
            'PolicyName' => $groupName,
            'PolicyDocument' => Json::encode($policy),
        ]);     
    }

    /**
     * @param $repoName
     * @return \Guzzle\Service\Resource\Model
     * @throws ServerErrorHttpException
     * @internal param $data
     */
    private function createRepo($repoName)
    {
        $CCClient = $this->iAmWrapper->getCodecommitClient();

        try {
            $result = $CCClient->createRepository([
                'repositoryDescription' => 'Scripture App Builder Repository for '.$repoName,
                'repositoryName' => $repoName,
            ]);


            return $result;
        } catch (IamException $e) {
            if($e->getAwsErrorCode()=='RepositoryNameExists') {

                $message = sprintf(
                    'SAB: Unable to create new repo - name already exists: %s. code=%s : message=%s',
                    $repoName,
                    $e->getCode(),
                    $e->getMessage()
                );
                throw new ServerErrorHttpException($message, 1437423427, $e);
            }

            throw $e;
        }
    }
    private function addUserToSshUrl($sshUrl, $user)
    {
        return str_replace('ssh://', 'ssh://' . $user . '@', $sshUrl);
    }
}

