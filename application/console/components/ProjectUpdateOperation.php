<?php
namespace console\components;
use console\components\OperationInterface;
use common\components\Appbuilder_logger;
use common\components\IAmWrapper;
use common\models\Project;
use common\helpers\Utils;
use Aws\Iam\IamClient;
use Aws\Iam\Exception\IamException;
use Aws\Iam\Exception\NoSuchEntityException;
class ProjectUpdateOperation implements OperationInterface
{
    private $id;
    private $parms;
    private $maxRetries = 50;
    private $maxDelay = 30;
    private $alertAfter = 5;
    public $iAmWrapper;  // Public for ut
   
    public function __construct($id, $parms)
    {
        $this->id = $id;
        $this->parms = $parms;
        $this->iAmWrapper = \Yii::$container->get('iAmWrapper');
    }
    public function performOperation()
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] ProjectUpdateOperation ID: " . $this->id . PHP_EOL;
        $project = Project::findByIdFiltered($this->id);;
        if ($project) {
            echo "Found record" . PHP_EOL;
            $parmsArray = explode(',', $this->parms);
            $publishing_key = $parmsArray[0];
            $user_id = $parmsArray[1];
            $this->checkRemoveUserFromGroup($project);
            $this->updateProject($project, $user_id, $publishing_key);
        } else {
            echo "Didn't find record" . PHP_EOL;
        }
    }
    public function getMaximumRetries()
    {
        return $this->maxRetries;
    }
    public function getMaximumDelay()
    {
        return $this->maxDelay;
    }
    public function getAlertAfterAttemptCount()
    {
        return $this->alertAfter;
    }
    /**
     * If the user/group combination associated with the current project is 
     * the only project that exists, then remove the IAM user from the IAM group
     * 
     * @param Project $project
     * @return void
     */
    private function checkRemoveUserFromGroup($project)
    {
        echo "checkRemoveUserFromGroup" . PHP_EOL;
        $projects = Project::find()->where('user_id = :user_id and group_id = :group_id',
        ['user_id'=>$project->user_id, 'group_id'=>$project->group_id])->all();
        $projectCount = count($projects);
        if (count($projects) < 2) {
            // Remove the user from the group
            echo "CheckRemoveUserFromGroup: Removing [{$project->user_id}] from group [{$project->groupName()}]" . PHP_EOL;
            $this->iAmWrapper->removeUserFromIamGroup($project->user_id, $project->groupName());
        }
    }
    private function updateProject($project, $user_id, $publishing_key)
    {
        echo "updateProject" . PHP_EOL;
        $project->user_id = $user_id;
        $project->publishing_key = $publishing_key;
        $user = $this->iAmWrapper->createAwsAccount($user_id);
        $this->iAmWrapper->addUserToIamGroup($project->user_id, $project->groupName());
        $public_key = $this->iAmWrapper->addPublicSshKey($project->user_id, $project->publishing_key);
        $publicKeyId = $public_key['SSHPublicKey']['SSHPublicKeyId'];
        $project->url = $this->adjustUrl($project->url, $publicKeyId);
        $project->save();
    }
    private function adjustUrl($url, $newPublicKeyId)
    {
        $segments = explode('@', $url);
        return str_replace($segments[0], 'ssh://' . $newPublicKeyId, $url);
    }
}