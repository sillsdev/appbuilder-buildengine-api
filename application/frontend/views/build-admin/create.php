<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Build */

$this->title = 'Create Build';
$this->params['breadcrumbs'][] = ['label' => 'Builds', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="build-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
