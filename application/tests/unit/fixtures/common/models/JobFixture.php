<?php
namespace tests\unit\fixtures\common\models;

use yii\test\ActiveFixture;

class JobFixture extends ActiveFixture
{
    public $modelClass = 'common\models\Job';
    public $dataFile = 'tests/unit/fixtures/data/common/models/Job.php';
}
