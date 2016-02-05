<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;

use common\helpers\Utils;

class EmailQueue extends EmailQueueBase
{
    const fromAddress = 'from@domain.com';

    public static function getFromAddress()
    {
        return self::fromAddress;
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
                'created','default', 'value' => Utils::getDatetime(),
            ],
        ]);
    }

}