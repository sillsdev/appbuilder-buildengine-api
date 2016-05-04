<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Builds';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="build-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Build', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
           [
                'attribute' => 'job_id',
                'format' => 'html',
                'value' => function($data) {
                    return Html::a("$data->job_id", ['job-admin/view', 'id' => $data->job_id]);
                },
            ],
            'status',
            'build_number',
            'result',
            // 'error',
            // 'artifact_url:url',
            // 'created',
            // 'updated',
            // 'channel',
            // 'version_code',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
