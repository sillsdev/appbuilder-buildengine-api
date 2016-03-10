<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;


use yii\web\Link;
use yii\web\Linkable;
use yii\helpers\Url;

use common\helpers\Utils;

class Build extends BuildBase implements Linkable
{
    
    const STATUS_INITIALIZED = 'initialized';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_COMPLETED = 'completed';

    const CHANNEL_UNPUBLISHED = 'unpublished';
    const CHANNEL_ALPHA = 'alpha';
    const CHANNEL_BETA = 'beta';
    const CHANNEL_PRODUCTION = 'production';

        /**
     * Array of valid status transitions. The key is the starting
     * status and the values are valid options to be changed to.
     * @var array
     */
    public $validStatusTransitions = [
        self::STATUS_INITIALIZED => [
            self::STATUS_ACTIVE,
        ],
        self::STATUS_ACTIVE => [
            self::STATUS_COMPLETED,
            self::STATUS_EXPIRED,
        ],
        self::STATUS_EXPIRED => [
            self::STATUS_ACTIVE,
        ],
    ];

    public function createRelease($channel) {
        $release = new Release();
        $release->build_id = $this->id;
        $release->channel = $channel;
        $release->save();

        return $release;
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
                'status', 'in', 'range' => [ 
                    self::STATUS_ACTIVE, 
                    self::STATUS_COMPLETED,
                    self::STATUS_EXPIRED,
                    self::STATUS_INITIALIZED,
                ],
            ],
            [
                'status', 'default', 'value' => self::STATUS_INITIALIZED,
            ],
            [
                'job_id', 'exist', 'targetClass' => 'common\models\Job', 'targetAttribute' => 'id',
                'message' => \Yii::t('app', 'Invalid Job ID'),
            ],
            [
                'artifact_url', 'url', 
                'pattern' => '/^https:\/\/s3-/',
                'message' => \Yii::t('app', 'Artifact Url must be an https S3 Url.')
            ],            
            [
                'updated', 'default', 'value' => Utils::getDatetime(), 'isEmpty' => function(){
                    // always return true so it get set on every save
                    return true;
                },
            ],
            [
                'channel', 'in', 'range' => [
                    self::CHANNEL_UNPUBLISHED,
                    self::CHANNEL_ALPHA,
                    self::CHANNEL_BETA,
                    self::CHANNEL_PRODUCTION,
                ],
            ],
            [
                'channel', 'default', 'value' => self::CHANNEL_UNPUBLISHED,
            ]

        ]);
    }
    public function fields()
    {
        return [
            'id',
            'job_id',
            'status',
            'result',
            'error' => function(){
                return  (filter_var($this->error, FILTER_VALIDATE_URL))
                    ? "see link" :
                    $this->error;
            },
            'artifact_url',
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
            $links[Link::REL_SELF] = Url::toRoute(['/job/'.$this->job_id.'/build/'.$this->id], true);
            $links['job'] = Url::toRoute(['/job/'.$this->job_id], true);
        }

        if (filter_var($this->error, FILTER_VALIDATE_URL)) {
            $links['error'] = Url::toRoute([sprintf('/job/%s/build/%s/error',$this->job->id, $this->id)], true);
        }

        return $links;
    }
    
    /**
     * Check if the new status is a valid transition from current
     * @param string $current The current status of Build
     * @param string $new The desired status for Build
     * @return bool
     */
    public function isValidStatusTransition($new, $current = null)
    {
        $current = $current ?: $this->status;
        if(in_array($new, $this->validStatusTransitions[$current])){
            return true;
        }

        return false;
    }

    public function jobName()
    {
        return $this->job->name();
    }
    /**
     * Returns the build specified by $build_id.  The inclusion of the $job_id
     * is done for validation purposes since this is also passed into the actions
     * that use this method
     *
     * @param type $job_id
     * @param type $build_id
     * @return type Build
     */
    public static function findOneById($job_id, $build_id)
    {
        $build = Build::findOne(['id' => $build_id, 'job_id' => $job_id]);
        if ($build) {
            if ($build->status == Build::STATUS_EXPIRED) {
                $build = null;
            }
       }
       return $build;
    }

    public static function findOneByBuildId($build_id)
    {
        $build = Build::findOne(['id' => $build_id]);
        if ($build) {
            if ($build->status == Build::STATUS_EXPIRED) {
                $build = null;
            }
       }
       return $build;
    }
    /**
     * Returns array of all non expired builds associated with the specified job
     *
     * @param type $job_id
     * @return type array of Build
     */
    public static function findAllByJobId($job_id)
    {
       $builds = Build::find()->where('job_id = :job_id and status != :status',
               ['job_id'=>$job_id, 'status'=>Build::STATUS_EXPIRED])->all();
       return $builds;
    }

    /**
     * Clears the artifact url for a build
     */
    public function clearArtifactUrl()
    {
        $this->artifact_url = null;
        $this->save();
    }
}
