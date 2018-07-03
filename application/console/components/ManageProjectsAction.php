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
                        $this->createCodeBuildProject($project);
                        $this->tryCreateRepo($project);
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
    private function tryCreateRepo($project)
    {
        $logger = new Appbuilder_logger("ManageProjectsAction");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] tryCreateRepo: Starting creation of project ".$project->project_name. PHP_EOL;

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
    private function tryDeleteRepo($project)
    {
        $logger = new Appbuilder_logger("ManageProjectsAction");
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

    /**
     * This method checks to see if the code build projects for build and publish exist
     * and builds them if they don't
     */
    private function createCodeBuildProject($project)
    {
        $logger = new Appbuilder_logger("createCodeBuildProject");
        try {
            $prefix = Utils::getPrefix();
            echo "[$prefix] createCodeBuildProject: create project Build of ". $project->project_name . PHP_EOL;
            $codeBuild = new CodeBuild();
            $iamWrapper = new IAmWrapper();
            // Build build role if necessary
            $projectName = 'build_app';
            $buildProjectName = CodeBuild::getCodeBuildProjectName($projectName);
            if (!$codeBuild->projectExists($buildProjectName))
            {
                echo "  Creating build project " . $buildProjectName . PHP_EOL;
                // Project doesn't exist, build it
                if (!$iamWrapper->doesRoleExist($projectName))
                {
                    echo "  Creating role for " . $buildProjectName . PHP_EOL;
                    // Role doesn't exist make it
                    $roleArn = $iamWrapper->createRole($projectName);
                    $iamWrapper->attachRolePolicy($projectName, 's3-appbuilder-secrets');
                    $iamWrapper->attachRolePolicy($projectName, 's3-appbuilder-artifacts');
                    $iamWrapper->attachRolePolicy($projectName, 'codecommit-projects');
                    $iamWrapper->attachRolePolicy($projectName, 'codebuild-basepolicy-build_app');
                }
                // Create the codebuild project
                $cache =  [
                    'location' => CodeBuild::getArtifactsBucket() . '/codebuild-cache',
                    'type' => 'S3',
                ];
                $source = [
                    'buildspec' => 'version: 0.2',
                    'gitCloneDepth' => 1,
                    'location' => 'https://git-codecommit.us-east-1.amazonaws.com/v1/repos/sample',
                    'type' => 'CODECOMMIT',
                ];
                $roleArn = $iamWrapper->getRoleArn($projectName);
                $codeBuild->createProject($projectName, $roleArn, $cache, $source);
                echo "  Project created" . PHP_EOL;
            }
             // Build publish role if necessary
            $projectName = 'publish_app';
            $publishProjectName = CodeBuild::getCodeBuildProjectName($projectName);
            if (!$codeBuild->projectExists($publishProjectName))
            {
                echo "  Creating build project " . $buildProjectName . PHP_EOL;

                // Project doesn't exist, build it
                if (!$iamWrapper->doesRoleExist($projectName))
                {
                    // Role doesn't exist make it
                    echo "  Creating role for " . $buildProjectName . PHP_EOL;
                    $roleArn = $iamWrapper->createRole($projectName);
                    $iamWrapper->attachRolePolicy($projectName, 's3-appbuilder-secrets');
                    $iamWrapper->attachRolePolicy($projectName, 's3-appbuilder-artifacts');
                    $iamWrapper->attachRolePolicy($projectName, 'codebuild-basepolicy-publish_app');
                }
                // Create the codebuild project
                $cache = [
                    'type' => 'NO_CACHE',
                ];
                $source = [
                    'buildspec' => 'version: 0.2',
                    'gitCloneDepth' => 1,
                    'location' =>  'arn:aws:s3:::prd-aps-artifacts/prd/jobs/build_scriptureappbuilder_1/1/sample.apk',
                    'type' => 'S3',
                ];
                $roleArn = $iamWrapper->getRoleArn($projectName);
                $codeBuild->createProject($projectName, $roleArn, $cache, $source);
                echo "  Project created" . PHP_EOL;
            }

        } catch (\Exception $e) {
            $prefix = Utils::getPrefix();
            echo "[$prefix] createCodeBuildProject: Exception:" . PHP_EOL . (string)$e . PHP_EOL;
            $logException = $this->getlogProjectDetails($project);
            $logger->appbuilderExceptionLog($logException, $e);
            $project->error = "File: ".$e->getFile()." Line: ".$e->getLine()." ".$e->getMessage();
            $project->status = Project::STATUS_COMPLETED;
            $project->result = Project::RESULT_FAILURE;
            $project->save();
        }
    }
}

