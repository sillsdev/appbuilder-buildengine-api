<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "client".
 *
 * @property integer $id
 * @property string $access_token
 * @property string $prefix
 * @property string $created
 * @property string $updated
 *
 * @property Job[] $jobs
 */
class ClientBase extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'client';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['access_token', 'prefix'], 'required'],
            [['created', 'updated'], 'safe'],
            [['access_token'], 'string', 'max' => 255],
            [['prefix'], 'string', 'max' => 4],
            [['prefix'], 'match', 'pattern'=>'/^([a-z0-9])+$/']
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'access_token' => Yii::t('app', 'Access Token'),
            'prefix' => Yii::t('app', 'Prefix'),
            'created' => Yii::t('app', 'Created'),
            'updated' => Yii::t('app', 'Updated'),
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getJobs()
    {
        return $this->hasMany(Job::className(), ['client_id' => 'id']);
    }
}
