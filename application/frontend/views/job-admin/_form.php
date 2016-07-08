<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Job */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="job-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'request_id')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'git_url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'app_id')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'publisher_id')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'client_id')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'existing_version_code')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'jenkins_build_url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'jenkins_publish_url')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'created')->textInput() ?>

    <?= $form->field($model, 'updated')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
