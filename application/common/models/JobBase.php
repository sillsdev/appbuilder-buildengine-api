<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "job".
 *
 * @property integer $id
 * @property string $request_id
 * @property string $git_url
 * @property string $app_id
 * @property string $publisher_id
 * @property string $created
 * @property string $updated
 * @property integer $client_id
 *
 * @property Build[] $builds
 * @property Client $client
 */
class JobBase extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'job';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['request_id', 'git_url', 'app_id', 'publisher_id'], 'required'],
            [['created', 'updated'], 'safe'],
            [['client_id'], 'integer'],
            [['request_id', 'app_id', 'publisher_id'], 'string', 'max' => 255],
            [['git_url'], 'string', 'max' => 2083]
        ];
    }

    /**
     * @inheritdoc
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
        if (is_null($this->client_id)) {
            return null;
        }
        return Client::findOne(['id' => $this->client_id]);
    }
}
