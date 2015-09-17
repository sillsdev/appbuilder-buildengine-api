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
    public function scenarios()
    {
        return ArrayHelper::merge(parent::scenarios(),[

        ]);
    }

       public function rules()
    {
        $appEnv = \Yii::$app->params['appEnv'];
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
                'artifact_url_base', 'default', 'value' => "s3://gtis-appbuilder/$appEnv/"
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
                'pattern' => '/^ssh:\/\/[A-Za-z0-9]+@git-codecommit\./',
                'message' => \Yii::t('app', 'Git SSH Url is required.')
            ],
            [
                'artifact_url_base', 'url',
                'pattern' => '/^s3:\/\/gtis-appbuilder\//',
                'message' => \Yii::t('app', 'Artifact Url must be S3 Url for gtis-appbuilder bucket.')
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
            'artifact_url_base',
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
}
