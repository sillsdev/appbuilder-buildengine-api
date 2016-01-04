<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "build".
 *
 * @property integer $id
 * @property integer $job_id
 * @property string $status
 * @property integer $build_number
 * @property string $result
 * @property string $error
 * @property string $artifact_url
 * @property string $created
 * @property string $updated
 * @property string $channel
 *
 * @property Job $job
 * @property Publish[] $publishes
 */
class BuildBase extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'build';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['job_id'], 'required'],
            [['job_id', 'build_number'], 'integer'],
            [['created', 'updated'], 'safe'],
            [['status', 'result', 'error', 'channel'], 'string', 'max' => 255],
            [['artifact_url'], 'string', 'max' => 2083]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'job_id' => Yii::t('app', 'Job ID'),
            'status' => Yii::t('app', 'Status'),
            'build_number' => Yii::t('app', 'Build Number'),
            'result' => Yii::t('app', 'Result'),
            'error' => Yii::t('app', 'Error'),
            'artifact_url' => Yii::t('app', 'Artifact Url'),
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
            'channel' => Yii::t('app', 'Channel'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getJob()
    {
        return $this->hasOne(Job::className(), ['id' => 'job_id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getPublishes()
    {
        return $this->hasMany(Publish::className(), ['build_id' => 'id']);
    }
}
