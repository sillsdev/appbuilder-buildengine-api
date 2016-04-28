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
            [['prefix'], 'string', 'max' => 4]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'access_token' => 'Access Token',
            'prefix' => 'Prefix',
            'created' => 'Created',
            'updated' => 'Updated',
        ];
    }
}
