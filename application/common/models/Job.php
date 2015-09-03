<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;
use yii\validators\UrlValidator;


//use yii\web\BadRequestHttpException;
//use yii\web\ConflictHttpException;
//use yii\web\ForbiddenHttpException;
//use yii\web\HttpException;
use yii\web\Link;
use yii\web\Linkable;
use yii\helpers\Url;

//use common\helpers\Utils;
//use common\models\Service;
//use common\models\ServiceStep;
//use common\models\ServiceUser;
//use common\models\ServiceUserStep;
//use common\components\EmailUtils;
//use common\components\MultipleErrorException;
//use yii\web\NotFoundHttpException;
//use yii\web\ServerErrorHttpException;

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
                // This should come from another model
                // 'app_id', 'exist', 'targetClass' => 'common\models\App', 'targetAttribute' => 'id',
                // message => \Yii::t('app', 'Invalid App ID'),
                'app_id', 'in', 'range' => ['scriptureappbuilder'],
            ],
            // This should be a rule to verify Git URL
            [
                'git_url', 'url', 'validSchemes' => ['ssh'], 'message' => \Yii::t('app', 'Git SSH Url is required.')
            ]
        ]);
    }
    public function fields()
    {
        return [
            'id',
            'request_id',
            'git_url',
            'app_id',
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
