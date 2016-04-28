<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;

use common\helpers\Utils;

class Client extends ClientBase
{

    public static function findByAccessToken($token)
    {
        return self::findOne(['access_token' => $token]);
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
        ]);
    }

    /**
     * Check for dependent jobs and delete them prior to deleting
     * record
     */
    public function beforeDelete() {
        foreach (Job::findAllByClientId($this->id) as $job)
        {
            if (!$job->delete()){
                return false;
            }
        }
        return parent::beforeDelete();
    }
}
