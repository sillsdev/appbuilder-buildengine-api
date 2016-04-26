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
                'publisher_id', 'in', 'range' => [
                    'wycliffeusa',
                    'kalaammedia',
                    'internetpublishingservice',
                    ]
            ],
            [
                // This should come from another model
                // 'app_id', 'exist', 'targetClass' => 'common\models\App', 'targetAttribute' => 'id',
                // message => \Yii::t('app', 'Invalid App ID'),
                'app_id', 'in', 'range' => ['scriptureappbuilder'],
            ],
            // The currently supported Git Urls are for AWS Codecommit
            [
                'git_url', 'url',
                    'pattern' => '/^ssh:\/\/(\w+@)?git-codecommit\./',
                'message' => \Yii::t('app', 'Git SSH Url is required.')
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
    public function createBuild()
    {
            $build = new Build();
            $build->job_id = $this->id;
            $build->save();

            return $build;
    }

    /**
     * Return the name of the job to use with Jenkins
     * @return string
     */
    public function name()
    {
        return $this->app_id."_".$this->request_id;
    }

    /**
     * Convenience method to find the job by Id
     * @param integer $id
     * @return type Job
     */
    public static function findById($id)
    {
        return self::findOne(['id' => $id]);
    }

    public static function getJobNames()
    {
        $jobs = [];
        foreach (Job::find()->each(50) as $job)
        {
            $jobApp = $job->app_id;
            $request = $job->request_id;
            $jobName = $jobApp."_".$request;
            $jobs[$jobName] = 1;
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
}
