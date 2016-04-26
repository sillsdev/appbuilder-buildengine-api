<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\OperationQueue */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Operation Queues', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="operation-queue-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'operation',
            'operation_object_id',
            'operation_parms',
            'attempt_count',
            'last_attempt',
            'try_after',
            'start_time',
            'last_error',
            'created',
            'updated',
        ],
    ]) ?>

</div>
