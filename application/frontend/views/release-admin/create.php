<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Release */

$this->title = 'Create Release';
$this->params['breadcrumbs'][] = ['label' => 'Releases', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="release-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
