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
    const STATUS_REQUEST_IN_PROGRESS = 'request_in_progress';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_COMPLETED = 'completed';

        /**
     * Array of valid status transitions. The key is the starting
     * status and the values are valid options to be changed to.
     * @var array
     */
    public $validStatusTransitions = [
        self::STATUS_INITIALIZED => [
            self::STATUS_REQUEST_IN_PROGRESS
        ],
        self::STATUS_REQUEST_IN_PROGRESS => [
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
                    self::STATUS_REQUEST_IN_PROGRESS
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
                'pattern' => '/^s3:\/\/gtis-appbuilder/',
                'message' => \Yii::t('app', 'Artifact Url must be S3 Url for gti-appbuilder bucket.')
            ],            
            [
                'updated', 'default', 'value' => Utils::getDatetime(), 'isEmpty' => function(){
                    // always return true so it get set on every save
                    return true;
                },
            ],
        ]);
    }
    public function fields()
    {
        return [
            'id',
            'job_id',
            'status',
            'build_number',
            'build_result',
            'build_error',
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
            $links[Link::REL_SELF] = Url::toRoute(['/build/'.$this->id], true);
            $links['job'] = Url::toRoute(['/job/'.$this->job_id], true);
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

    
}
