<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "job".
 *
 * @property int $id
 * @property string $request_id
 * @property string $git_url
 * @property string $app_id
 * @property string $publisher_id
 * @property string $created
 * @property string $updated
 * @property int $client_id
 * @property int $existing_version_code
 * @property string $jenkins_build_url
 * @property string $jenkins_publish_url
 *
 * @property Build[] $builds
 * @property Client $client
 */
class JobBase extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'job';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['request_id', 'git_url', 'app_id', 'publisher_id'], 'required'],
            [['created', 'updated'], 'safe'],
            [['client_id', 'existing_version_code'], 'integer'],
            [['request_id', 'app_id', 'publisher_id'], 'string', 'max' => 255],
            [['git_url'], 'string', 'max' => 2083],
            [['jenkins_build_url', 'jenkins_publish_url'], 'string', 'max' => 1024],
            [['client_id'], 'exist', 'skipOnError' => true, 'targetClass' => Client::className(), 'targetAttribute' => ['client_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'request_id' => Yii::t('app', 'Request ID'),
            'git_url' => Yii::t('app', 'Git Url'),
            'app_id' => Yii::t('app', 'App ID'),
            'publisher_id' => Yii::t('app', 'Publisher ID'),
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
            'client_id' => Yii::t('app', 'Client ID'),
            'existing_version_code' => Yii::t('app', 'Existing Version Code'),
            'jenkins_build_url' => Yii::t('app', 'Jenkins Build Url'),
            'jenkins_publish_url' => Yii::t('app', 'Jenkins Publish Url'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getBuilds()
    {
        return $this->hasMany(Build::className(), ['job_id' => 'id']);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['id' => 'client_id']);
    }
}
