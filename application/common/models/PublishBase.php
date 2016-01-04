<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "publish".
 *
 * @property integer $id
 * @property integer $build_id
 * @property string $status
 * @property string $created
 * @property string $updated
 *
 * @property Build $build
 */
class PublishBase extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'publish';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['build_id'], 'required'],
            [['build_id'], 'integer'],
            [['created', 'updated'], 'safe'],
            [['status'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'build_id' => Yii::t('app', 'Build ID'),
            'status' => Yii::t('app', 'Status'),
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBuild()
    {
        return $this->hasOne(Build::className(), ['id' => 'build_id']);
    }
}
