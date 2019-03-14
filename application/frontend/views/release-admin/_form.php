<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Release */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="release-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'build_id')->textInput() ?>

    <?= $form->field($model, 'status')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'created')->textInput() ?>

    <?= $form->field($model, 'updated')->textInput() ?>

    <?= $form->field($model, 'result')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'error')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'channel')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'defaultLanguage')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'build_guid')->textInput() ?>

    <?= $form->field($model, 'promote_from')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'targets')->textInput() ?>

    <?= $form->field($model, 'environment')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
