<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Job */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Jobs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="job-view">

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
            'request_id',
            'git_url:url',
            'app_id',
            'publisher_id',
            [
                'attribute'=>'client_id',
                'format'=>"html",
                'value' => $model->client_id ? Html::a($model->client_id, ['client-admin/view', 'id' => $model->client_id]) : "<span class='not-set'>(not set)</span>",
            ],
            'existing_version_code',
            [
                'attribute'=>'jenkins_build_url',
                'format'=>"html",
                'value' => $model->jenkins_build_url ? Html::a($model->jenkins_build_url, $model->jenkins_build_url) : "<span class='not-set'>(not set)</span>",
            ],
            [
                'attribute'=>'jenkins_publish_url',
                'format'=>"html",
                'value' => $model->jenkins_publish_url ? Html::a($model->jenkins_publish_url, $model->jenkins_publish_url) : "<span class='not-set'>(not set)</span>",
            ],
            'created',
            'updated',
        ],
    ]) ?>

</div>
