<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;


use yii\web\Link;
use yii\web\Linkable;
use yii\helpers\Url;

use common\helpers\Utils;

class Job extends JobBase implements Linkable
{
    const APP_TYPE_SCRIPTUREAPP = 'scriptureappbuilder';
    const APP_TYPE_READINGAPP = 'readingappbuilder';
    const APP_TYPE_DICTIONARYAPP = 'dictionaryappbuilder';

    public function __construct($config = array()) {
        parent::__construct($config);
    }

    public function scenarios()
    {
        return ArrayHelper::merge(parent::scenarios(),[

        ]);
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(),[
            [
                ['created','updated'],'default', 'value' => Utils::getDatetime(),
            ],
            [
                'updated', 'default', 'value' => Utils::getDatetime(), 'isEmpty' => function(){
                    // always return true so it get set on every save
                    return true;
                },
            ],
            [
                ['request_id'], 'unique',
            ],
            [
                // This should come from another model
                // 'app_id', 'exist', 'targetClass' => 'common\models\App', 'targetAttribute' => 'id',
                // message => \Yii::t('app', 'Invalid App ID'),
                'app_id', 'in', 'range' => ['scriptureappbuilder', 'readingappbuilder', 'dictionaryappbuilder'],
            ],
            // The currently supported Git Urls are for AWS Codecommit
            //[
            //    'git_url', 'url',
            //        'pattern' => '/^ssh:\/\/(\w+@)?git-codecommit\./',
            //    'message' => \Yii::t('app', 'Git SSH Url is required.')
            //],
            [
                ['client_id'],'default', 'value' => function() {
                    return self::getCurrentClientId();
                },
            ],
        ]);
    }

    public function fields()
    {
        return [
            'id',
            'request_id',
            'git_url',
            'app_id',
            'publisher_id',
            'created' => function(){
                return Utils::getIso8601($this->created);
            },
            'updated' => function(){
                return Utils::getIso8601($this->updated);
            },
        ];
    }

    public function getLinks()
    {
        $links = [];
        if($this->id){
            $links[Link::REL_SELF] = Url::toRoute(['/job/'.$this->id], true);
        }

        return $links;
    }

    /**
     * Get the most recent build (by date created)
     * @return Build
     */
    public function getLatestBuild()
    {
        return Build::find()
                    ->where(['job_id' => $this->id])
                    ->orderBy('created DESC')
                    ->one();
    }

    /**
     *
     * @return Build
     */
    public function createBuild($targets, $environment)
    {
            $build = new Build();
            $build->job_id = $this->id;
            $build->targets = $targets;
            $build->environment = $environment;
            $build->save();

            return $build;
    }

    /**
     * Return the name of the job
     * @return string
     */
    public function name()
    {
        $client = $this->getLinkedClient();
        if (!is_null($client)) {
            return $this->app_id."_".$client->prefix."_".$this->id;
        } else {
            return $this->app_id."_".$this->id;
        }
    }

    /**
     * Returns array of all jobs associated with the specified client
     *
     * @param integer $client_id
     * @return array array of Build
     */
    public static function findAllByClientId($client_id)
    {
        if ($client_id) {
            $jobs = Job::find()->where('client_id = :client_id',
                ['client_id'=>$client_id])->all();
            return $jobs;
        } else {
            /* No way I could find to find records where the 
             * field is equal to null.  Always returned nothing
             */
            $jobs = [];
            foreach (Job::find()->each(50) as $job)
            {
                if ($job->client_id == null) {
                    $jobs[] = $job;
                }
            }
            return $jobs;
        }
    }
    /**
     * Return the nae of the job to use when publishing
     * @return String
     */
    public function nameForPublish()
    {
        return "publish_".$this->name();
    }

    /**
     * Return the nae of the job to use when publishing
     * @return String
     */
    public function nameForBuild()
    {
        return "build_".$this->name();
    }

    /**
     * Returns the name of the code build process to run
     * @return String
     */
    public function nameForBuildProcess()
    {
        return "build_".$this->app_id;
    }
    /**
     * Convenience method to find the job by Id
     * @param integer $id
     * @return Job Job
     */
    public static function findById($id)
    {
        return self::findOne(['id' => $id]);
    }
    public static function findByIdFiltered($id)
    {
        $job = self::findById($id);
        if (!is_null($job)){
            if ($job->client_id != self::getCurrentClientId()) {
                $job = null;
            }
        }
        return $job;
    }
    /**
     * Create an entry containing the name of all 
     * build and publish jobs that are valid based on the
     * current database contents
     * @return array of Strings
     */
    public static function getJobNames()
    {
        $jobs = [];
        foreach (Job::find()->each(50) as $job)
        {
            $jobs[$job->nameForBuild()] = 1;
            $jobs[$job->nameForPublish()] = 1;
        }
        return $jobs;
    }

    /**
     * Check for dependent builds and delete them prior to deleting
     * record
     */
    public function beforeDelete() {
        foreach (Build::findAllByJobId($this->id) as $build)
        {
            if (!$build->delete()){
                return false;
            }
        }
        return parent::beforeDelete();
    }
    public static function getCurrentClientId() {
        $cid = "";
        $user = Utils::getCurrentUser();
        if (!is_null($user)) {
            $cid = $user->getClientId();
        }
        return $cid;
    }
    public function getLinkedClient()
     {
        if (is_null($this->client_id)) {
            return null;
        }
        return Client::findOne(['id' => $this->client_id]);
     }
     public static function recordCount() {
         $jobs = Job::find()->all();
         $count = count($jobs);
         return $count;
     }
}
