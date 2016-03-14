<?php

namespace common\components;

use yii\base\Exception;

class MaxRetriesExceededException extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function getName()
    {
        return 'Maximum Retries Exceeded';
    }
}
