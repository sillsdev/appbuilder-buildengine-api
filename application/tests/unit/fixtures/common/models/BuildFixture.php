<?php
namespace tests\unit\fixtures\common\models;

use yii\test\ActiveFixture;

class BuildFixture extends ActiveFixture
{
    public $modelClass = 'common\models\Build';
    public $dataFile = 'tests/unit/fixtures/data/common/models/Build.php';
    public $depends = [
        'tests\unit\fixtures\common\models\JobFixture',
    ];
}
