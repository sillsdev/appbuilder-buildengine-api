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
 * @property string $created
 * @property string $updated
 * @property string $channel
 * @property integer $version_code
 * @property string $artifact_url_base
 * @property string $artifact_files
 *
 * @property Job $job
 * @property Release[] $releases
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
            [['job_id', 'build_number', 'version_code'], 'integer'],
            [['created', 'updated'], 'safe'],
            [['status', 'result', 'channel', 'artifact_files'], 'string', 'max' => 255],
            [['error', 'artifact_url_base'], 'string', 'max' => 2083],
            [['job_id'], 'exist', 'skipOnError' => true, 'targetClass' => Job::className(), 'targetAttribute' => ['job_id' => 'id']],
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
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
            'channel' => Yii::t('app', 'Channel'),
            'version_code' => Yii::t('app', 'Version Code'),
            'artifact_url_base' => Yii::t('app', 'Artifact Url Base'),
            'artifact_files' => Yii::t('app', 'Artifact Files'),
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
    public function getReleases()
    {
        return $this->hasMany(Release::className(), ['build_id' => 'id']);
    }
}
