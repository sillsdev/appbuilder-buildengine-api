<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "project".
 *
 * @property integer $id
 * @property string $status
 * @property string $result
 * @property string $error
 * @property string $url
 * @property string $user_id
 * @property string $group_id
 * @property string $app_id
 * @property string $project_name
 * @property string $language_code
 * @property string $publishing_key
 * @property string $created
 * @property string $updated
 * @property integer $client_id
 *
 * @property Client $client
 */
class ProjectBase extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'project';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['created', 'updated'], 'safe'],
            [['client_id'], 'integer'],
            [['status', 'result', 'user_id', 'group_id', 'app_id', 'project_name', 'language_code'], 'string', 'max' => 255],
            [['error'], 'string', 'max' => 2083],
            [['url', 'publishing_key'], 'string', 'max' => 1024],
            [['client_id'], 'exist', 'skipOnError' => true, 'targetClass' => Client::className(), 'targetAttribute' => ['client_id' => 'id']],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'status' => Yii::t('app', 'Status'),
            'result' => Yii::t('app', 'Result'),
            'error' => Yii::t('app', 'Error'),
            'url' => Yii::t('app', 'Url'),
            'user_id' => Yii::t('app', 'User ID'),
            'group_id' => Yii::t('app', 'Group ID'),
            'app_id' => Yii::t('app', 'App ID'),
            'project_name' => Yii::t('app', 'Project Name'),
            'language_code' => Yii::t('app', 'Language Code'),
            'publishing_key' => Yii::t('app', 'Publishing Key'),
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
            'client_id' => Yii::t('app', 'Client ID'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getClient()
    {
        return $this->hasOne(Client::className(), ['id' => 'client_id']);
    }
}
