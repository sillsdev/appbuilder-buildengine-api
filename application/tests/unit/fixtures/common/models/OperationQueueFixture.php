<?php
namespace tests\unit\fixtures\common\models;

use yii\test\ActiveFixture;

class OperationQueueFixture extends ActiveFixture
{
    public $modelClass = 'common\models\OperationQueue';
    public $dataFile = 'tests/unit/fixtures/data/common/models/OperationQueue.php';
}
