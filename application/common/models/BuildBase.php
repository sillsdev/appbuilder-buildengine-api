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
 *
 * @property Job $job
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
            [['status', 'result', 'error'], 'string', 'max' => 255],
            [['artifact_url'], 'string', 'max' => 2083]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'job_id' => 'Job ID',
            'status' => 'Status',
            'build_number' => 'Build Number',
            'result' => 'Result',
            'error' => 'Error',
            'artifact_url' => 'Artifact Url',
            'created' => 'Created',
            'updated' => 'Updated',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getJob()
    {
        return $this->hasOne(Job::className(), ['id' => 'job_id']);
    }
}
