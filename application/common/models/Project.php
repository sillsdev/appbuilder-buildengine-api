<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;


use yii\web\Link;
use yii\web\Linkable;
use yii\helpers\Url;

use common\helpers\Utils;
class Project extends ProjectBase implements Linkable
{
    const STATUS_INITIALIZED = 'initialized';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DELETE_PENDING = 'delete';
    const STATUS_DELETING = 'deleting';

    const RESULT_SUCCESS = 'SUCCESS';
    const RESULT_FAILURE = 'FAILURE';
    
    /**
     * Array of valid status transitions. The key is the starting
     * status and the values are valid options to be changed to.
     * @var array
     */
    public $validStatusTransitions = [
        self::STATUS_INITIALIZED => [
            self::STATUS_ACTIVE,
            self::STATUS_DELETE_PENDING,
        ],
        self::STATUS_ACTIVE => [
            self::STATUS_COMPLETED,
            self::STATUS_DELETE_PENDING,
        ],
        self::STATUS_COMPLETED => [
            self::STATUS_DELETE_PENDING,
        ],
        self::STATUS_DELETE_PENDING => [
            self::STATUS_DELETING,
        ],
        self::STATUS_DELETING => [
            self::STATUS_COMPLETED,
        ]
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
                    self::STATUS_INITIALIZED,
                    self::STATUS_DELETE_PENDING,
                    self::STATUS_DELETING,
                ],
            ],
            [
                'status', 'default', 'value' => self::STATUS_INITIALIZED,
            ],
            [
                // This should come from another model
                // 'app_id', 'exist', 'targetClass' => 'common\models\App', 'targetAttribute' => 'id',
                // message => \Yii::t('app', 'Invalid App ID'),
                'app_id', 'in', 'range' => ['scriptureappbuilder'],
            ],                    
        ]);
    }
    public function fields()
    {
        return [
            'id',
            'status',
            'result',
            'error',
            'url',
            'user_id',
            'group_id',
            'app_id',
            'project_name',
            'language_code',
            'publishing_key',
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
            $links[Link::REL_SELF] = Url::toRoute(['/project/'.$this->id], true);
        }

        return $links;
    }
    public function groupName()
    {
        $groupName = 'CodeCommit-'.$this->entityName();
        return $groupName;        
    }
    public function entityName()
    {
        return strtoupper($this->group_id);
    }
}