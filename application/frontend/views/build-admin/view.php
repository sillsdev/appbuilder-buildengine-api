<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Build */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Builds', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="build-view">

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

    <?=
        DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute'=>'job_id',
                'format'=>"html",
                'value' => Html::a($model->job_id, ['job-admin/view', 'id' => $model->job_id]),
            ],
            'status',
            'build_number',
            'result',
            'error',
            'artifact_url:url',
            'created',
            'updated',
            'channel',
            'version_code',
        ],
    ]) ?>

</div>
