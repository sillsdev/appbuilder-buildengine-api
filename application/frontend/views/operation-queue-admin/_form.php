<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\OperationQueue */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="operation-queue-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'operation')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'operation_object_id')->textInput() ?>

    <?= $form->field($model, 'operation_parms')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'attempt_count')->textInput() ?>

    <?= $form->field($model, 'last_attempt')->textInput() ?>

    <?= $form->field($model, 'try_after')->textInput() ?>

    <?= $form->field($model, 'start_time')->textInput() ?>

    <?= $form->field($model, 'last_error')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'created')->textInput() ?>

    <?= $form->field($model, 'updated')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Create' : 'Update', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
