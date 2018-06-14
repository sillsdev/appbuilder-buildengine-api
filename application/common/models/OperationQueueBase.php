<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "operation_queue".
 *
 * @property int $id
 * @property string $operation
 * @property int $operation_object_id
 * @property string $operation_parms
 * @property int $attempt_count
 * @property string $last_attempt
 * @property string $try_after
 * @property string $start_time
 * @property string $last_error
 * @property string $created
 * @property string $updated
 */
class OperationQueueBase extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'operation_queue';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['operation', 'attempt_count'], 'required'],
            [['operation_object_id', 'attempt_count'], 'integer'],
            [['last_attempt', 'try_after', 'start_time', 'created', 'updated'], 'safe'],
            [['operation'], 'string', 'max' => 255],
            [['operation_parms', 'last_error'], 'string', 'max' => 2048],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'operation' => Yii::t('app', 'Operation'),
            'operation_object_id' => Yii::t('app', 'Operation Object ID'),
            'operation_parms' => Yii::t('app', 'Operation Parms'),
            'attempt_count' => Yii::t('app', 'Attempt Count'),
            'last_attempt' => Yii::t('app', 'Last Attempt'),
            'try_after' => Yii::t('app', 'Try After'),
            'start_time' => Yii::t('app', 'Start Time'),
            'last_error' => Yii::t('app', 'Last Error'),
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
        ];
    }
}
