<?php
namespace console\components;

use common\models\Project;
use common\components\Appbuilder_logger;
use common\components\JenkinsUtils;
use Aws\CodeCommit\CodeCommitClient;
use Aws\Iam\IamClient;
use Aws\Iam\Exception\IamException;
use Aws\Iam\Exception\NoSuchEntityException;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

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
            $logger = new Appbuilder_logger("ManageReleasesAction");
            $complete = Project::STATUS_COMPLETED;
            foreach (Project::find()->where("status!='$complete'")->each(50) as $project){
                echo "cron/manage-projects: Project=$project->id, ". PHP_EOL;
                $logProjectDetails = $this->getlogProjectDetails($project);
                $logger->appbuilderWarningLog($logProjectDetails);
                switch ($project->status){
                    case Project::STATUS_INITIALIZED:
                        $this->tryCreateRepo($project);
                        break;
                    case Project::STATUS_ACTIVE:
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
    private function tryCreateRepo($project)
    {
        $logger = new Appbuilder_logger("ManageProjectsAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryCreateRepo: Starting Build of project ".$project->project_name. PHP_EOL;

            $project->status = Project::STATUS_ACTIVE;
            $project->save();
            $repoName = $this->constructRepoName($project);
            $user = $this->createAwsAccount($project);
            $this->findOrCreateIamCodeCommitGroup($project);
            $this->addUserToIamGroup($project->user_id, $project->groupName());
            $public_key = $this->addPublicSshKey($project->user_id, $project->publishing_key);
            $publicKeyId = $public_key['SSHPublicKey']['SSHPublicKeyId'];

            /*
             * Only attempt to create repo if one has not already been created for this project.
             * This prevents creating a second repo if the project name has changed
             */
            // TODO: THINK ABOUT HOW WE FIND OUT IF PROJECT REPO CREATED IF NECESSARY
            if ( ! is_null($project->url)) {
                /*
                 * Only need to return data that may have changed
                 */
                return [
                    'aws_public_key_id' => $publicKeyId
                ];
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
            $project->status = Project::STATUS_COMPLETED;
            $project->result = Project::RESULT_FAILURE;
            $project->save();
       }
    }
    /**
     *
     * @param Release $release
     */
    private function checkProjectStatus($release)
    {
        $logger = new Appbuilder_logger("ManageProjectsAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] Check Build of ".$release->jobName()." for Channel ".$release->channel.PHP_EOL;

        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] checkProjectStatus Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            echo "Exception: " . $e->getMessage() . PHP_EOL;
            echo $e->getFile() . PHP_EOL;
            echo $e->getLine() . PHP_EOL;
            $logException = $this->getlogProjectDetails($release);
            $logger->appbuilderExceptionLog($logException, $e);
            $this->failRelease($release);
        }
    }
    private function constructRepoName($project)
    {
        $repoName = $project->app_id.'-'.$project->entityName().'-'.$project->language_code . '-' . $project->project_name;

        $repoName = Utils::lettersNumbersHyphensOnly($repoName);

        return $repoName;
    }
    private function createAwsAccount($project)
    {
        $iamClient = $this->iAmWrapper->getIamClient();

        try {
            $user = $iamClient->createUser([
                'Path' => '/sab-codecommit-users/',
                'UserName' => $project->user_id
            ]);

            return $user;
        } catch (IamException $e) {
            if($e->getAwsErrorCode() == 'EntityAlreadyExists') { // They already have an account - pass back their account
                $user = $iamClient->getUser([
                    'UserName' => $project->user_id
                ]);

                return $user;
            }

            $message = sprintf('SAB: Unable to create account. code=%s : message=%s', $e->getCode(), $e->getMessage());
            \Yii::error($message, 'service');
            throw new ServerErrorHttpException($message, 1437423331, $e);
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
            $result = $iamClient->getGroup([
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
    private function addUserToIamGroup($username, $groupName)
    {
        $iamClient = $this->iAmWrapper->getIamClient();

        $result = $iamClient->addUserToGroup([
            'GroupName' => $groupName,
            'UserName' => $username
        ]);

        return $result;
    }
    private function addPublicSshKey($username, $publicKey)
    {
        $iamClient = $this->iAmWrapper->getIamClient();
        try {
            $result = $iamClient->uploadSSHPublicKey([
                'SSHPublicKeyBody' => $publicKey,
                'UserName' => $username
            ]);

            return $result;
        } catch (IamException $e) {
            if($e->getAwsErrorCode()=='DuplicateSSHPublicKey'){
                try{
                    $keysForRequester = $iamClient->listSSHPublicKeys([
                        'UserName' => $username
                    ]);

                    foreach ($keysForRequester['SSHPublicKeys'] as $requesterKey) {
                        $key = $iamClient->getSSHPublicKey([
                            'UserName' => $username,
                            'SSHPublicKeyId' => $requesterKey['SSHPublicKeyId'],
                            'Encoding' => 'SSH'
                        ]);

                        if ($this->isEqual($key['SSHPublicKey']['SSHPublicKeyBody'], $publicKey)) {
                            return $key;
                        }
                    }

                    $message = sprintf('SAB: Unable to find a matching ssh key for user. code=%s : message=%s', $e->getCode(), $e->getMessage());
                    throw new ServerErrorHttpException($message, 1451819839);
                } catch (IamException $e) {
                    $message = sprintf('SAB: Unable to get users existing ssh key(s). code=%s : message=%s', $e->getCode(), $e->getMessage());
                    throw new ServerErrorHttpException($message, 1441819828, $e);
                }
            }

            $message = sprintf('SAB: Unable to add ssh key to user. code=%s : message=%s', $e->getCode(), $e->getMessage());
            throw new ServerErrorHttpException($message, 1441809827, $e);
        }
    }    
    /**
     * used to determine whether two openSSH formatted keys are equal (without regard to comment portion of key)
     * @param $openSSHKey1 format expected:  type data comment
     * @param $openSSHKey2 format expected:  type data comment
     * @return bool
     */
    private function isEqual($openSSHKey1, $openSSHKey2) {
        list($type1, $data1) = explode(" ", $openSSHKey1);
        list($type2, $data2) = explode(" ", $openSSHKey2);

        if ($type1 == $type2 && $data1 == $data2) {
            return true;
        }

        return false;
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

