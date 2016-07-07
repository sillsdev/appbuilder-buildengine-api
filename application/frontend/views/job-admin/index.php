<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Jobs';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="job-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Job', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'request_id',
            'git_url:url',
            'app_id',
            'publisher_id',
            [
                'attribute' => 'client_id',
                'format' => 'html',
                'value' => function($data) {
                    return $data->client_id ? Html::a($data->client_id, ['client-admin/view', 'id' => $data->client_id]) : "<span class='not-set'>(not set)</span>";
                },
            ],
            'existing_version_code',
            // 'created',
            // 'updated',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
