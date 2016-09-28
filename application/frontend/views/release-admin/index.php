<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Releases';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="release-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Release', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            [
                'label' => 'Job ID',
                'attribute' => 'job_id',
                'format' => 'html',
                'value' => function($data) {
                    $jobId = $data->jobId();
                    return Html::a("$jobId", ['job-admin/view', 'id' => $jobId]);
                },
            ],
            [
                'attribute' => 'build_id',
                'format' => 'html',
                'value' => function($data) {
                    return Html::a("$data->build_id", ['build-admin/view', 'id' => $data->build_id]);
                },
            ],
            'status',
            'result',
            [
                'attribute'=>'build_number',
                'format'=>"html",
                'value' => function($data) {
                    return $data->jenkinsUrl() ? Html::a($data->build_number, $data->jenkinsUrl()) : "<span>" . $data->build_number . "</span>";
                }
            ],
            // 'created',
            // 'updated',
            // 'result',
            // 'error',
            // 'channel',
            // 'title',
            // 'defaultLanguage',
            // 'build_number',
            // 'promote_from',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
