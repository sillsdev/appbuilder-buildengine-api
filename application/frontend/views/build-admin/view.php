<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Build */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Builds', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$value = "";
if (strpos($model->targets, "apk") !== false) {
    $value = $value .
        Html::a("apk", $model->apk()) . ", " .
        Html::a("about", $model->about()) . ", " . $value;
}

if (strpos($model->targets, "play-listing") !== false) {
    $value = $value .
        Html::a("play-listing", $model->playListing()) . ", " .
        Html::a("play-listing-manifest", $model->playListingManifest()) . ", " .
        Html::a("version_code", $model->versionCode()) . ", " .
        Html::a("version", $model->version()) . ", " .
        Html::a("package_name", $model->packageName()) . ", " .
        Html::a("whats_new", $model->whatsNew()) . ", ";
}

if (strpos($model->targets, "html") !== false) {
    $value = $value . $value = Htm::a("html", $model->html()) . ", ";
}

if (strpos($model->targets, "pwa") !== false) {
    $value = $value = Html::a("pwa", $model->pwa()) . ", ";
}

$value = $value .
    Html::a("cloudWatch", $model->cloudWatch()) . ", " .
    Html::a("consoleText", $model->consoleText());

$publishProperties = $model->publishProperties();
if (!empty($publishProperties)) {
    $value = $value . ", " .
    Html::a("publishProperties", $publishProperties);
}

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
                'value'=> $value
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
