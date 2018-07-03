<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "release".
 *
 * @property int $id
 * @property int $build_id
 * @property string $status
 * @property string $created
 * @property string $updated
 * @property string $result
 * @property string $error
 * @property string $channel
 * @property string $title
 * @property string $defaultLanguage
 * @property string $promote_from
 * @property string $build_guid
 * @property string $console_text_url
 *
 * @property Build $build
 */
class ReleaseBase extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'release';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['build_id', 'channel'], 'required'],
            [['build_id'], 'integer'],
            [['created', 'updated'], 'safe'],
            [['status', 'result', 'channel', 'defaultLanguage', 'promote_from', 'build_guid', 'console_text_url'], 'string', 'max' => 255],
            [['error'], 'string', 'max' => 2083],
            [['title'], 'string', 'max' => 30],
            [['build_id'], 'exist', 'skipOnError' => true, 'targetClass' => Build::className(), 'targetAttribute' => ['build_id' => 'id']],
        ];
    }

    /**
     * {@inheritdoc}
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
            'promote_from' => Yii::t('app', 'Promote From'),
            'build_guid' => Yii::t('app', 'Build Guid'),
            'console_text_url' => Yii::t('app', 'Console Text Url'),
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
