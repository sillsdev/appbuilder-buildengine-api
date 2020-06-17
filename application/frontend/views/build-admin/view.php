<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Build */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Builds', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$artifacts = $model->artifacts();
$entries = array();
foreach ($artifacts as $key => $artifact) {
    array_push($entries, Html::a($key, $artifact));
}
$artifacts_value = join(", ", $entries);

?>
<div class="build-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?php if ($model->status == \common\models\Build::STATUS_POSTPROCESSING) {
            echo Html::a('Retry Copy', ['retry-copy', 'id' => $model->id], ['class' => 'btn btn-warning']);
        } ?>
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
            [
                'attribute'=>'build_guid',
                'format'=>"html",
                'value' =>  Html::a($model->build_guid, $model->codebuild_url),
            ],
            'result',
            'error:url',
            [
                'attribute' => 'artifacts',
                'format'=>'html',
                'value'=> $artifacts_value
            ],
            'created',
            'updated',
            'channel',
            'version_code',
            'targets',
            'environment'
        ],
    ]) ?>

</div>
