<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\OperationQueue */

$this->title = 'Create Operation Queue';
$this->params['breadcrumbs'][] = ['label' => 'Operation Queues', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="operation-queue-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
