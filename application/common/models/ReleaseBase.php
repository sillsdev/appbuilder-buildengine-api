<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "release".
 *
 * @property integer $id
 * @property integer $build_id
 * @property string $status
 * @property string $created
 * @property string $updated
 * @property string $result
 * @property string $error
 * @property string $channel
 * @property string $title
 * @property string $defaultLanguage
 * @property integer $build_number
 *
 * @property Build $build
 */
class ReleaseBase extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'release';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['build_id', 'channel'], 'required'],
            [['build_id', 'build_number'], 'integer'],
            [['created', 'updated'], 'safe'],
            [['status', 'result', 'channel', 'defaultLanguage'], 'string', 'max' => 255],
            [['error'], 'string', 'max' => 2083],
            [['title'], 'string', 'max' => 30],
            [['build_id'], 'exist', 'skipOnError' => true, 'targetClass' => Build::className(), 'targetAttribute' => ['build_id' => 'id']],
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
            'result' => Yii::t('app', 'Result'),
            'error' => Yii::t('app', 'Error'),
            'channel' => Yii::t('app', 'Channel'),
            'title' => Yii::t('app', 'Title'),
            'defaultLanguage' => Yii::t('app', 'Default Language'),
            'build_number' => Yii::t('app', 'Build Number'),
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
