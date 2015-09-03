<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "job".
 *
 * @property integer $id
 * @property integer $request_id
 * @property string $git_url
 * @property string $app_id
 * @property string $created
 * @property string $updated
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
            [['request_id', 'git_url', 'app_id'], 'required'],
            [['request_id'], 'integer'],
            [['created', 'updated'], 'safe'],
            [['git_url'], 'string', 'max' => 2083],
            [['app_id'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'request_id' => 'Request ID',
            'git_url' => 'Git Url',
            'app_id' => 'App ID',
            'created' => 'Created',
            'updated' => 'Updated',
        ];
    }
}
