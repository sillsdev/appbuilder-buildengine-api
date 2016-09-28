<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Release */

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Releases', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="release-view">

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
            [
                'attribute'=>'build_id',
                'format'=>"html",
                'value' => Html::a($model->build_id, ['build-admin/view', 'id' => $model->build_id]),
            ],
            'status',
            'created',
            'updated',
            'result',
            'error:url',
            'channel',
            'title',
            'defaultLanguage',
            [
                'attribute'=>'build_number',
                'format'=>"html",
                'value' => $model->jenkinsUrl() ? Html::a($model->build_number, $model->jenkinsUrl()) : "<span>" . $model->build_number . "</span>",
            ],
            'promote_from',
        ],
    ]) ?>

</div>
