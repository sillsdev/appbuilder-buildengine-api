<?php
namespace tests\unit\fixtures\common\models;

use yii\test\ActiveFixture;

class ReleaseFixture extends ActiveFixture
{
    public $modelClass = 'common\models\Release';
    public $dataFile = 'tests/unit/fixtures/data/common/models/Release.php';
    public $depends = [
        'tests\unit\fixtures\common\models\BuildFixture',
    ];
}
