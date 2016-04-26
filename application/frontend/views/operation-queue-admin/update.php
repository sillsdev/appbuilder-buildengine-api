<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\OperationQueue */

$this->title = 'Update Operation Queue: ' . ' ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Operation Queues', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="operation-queue-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
