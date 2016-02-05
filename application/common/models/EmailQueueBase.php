<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "email_queue".
 *
 * @property integer $id
 * @property string $to
 * @property string $cc
 * @property string $bcc
 * @property string $subject
 * @property string $text_body
 * @property string $html_body
 * @property integer $attempts_count
 * @property string $last_attempt
 * @property string $created
 * @property string $error
 */
class EmailQueueBase extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'email_queue';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['to', 'subject'], 'required'],
            [['text_body', 'html_body'], 'string'],
            [['attempts_count'], 'integer'],
            [['last_attempt', 'created'], 'safe'],
            [['to', 'cc', 'bcc', 'subject', 'error'], 'string', 'max' => 255]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'to' => Yii::t('app', 'To'),
            'cc' => Yii::t('app', 'Cc'),
            'bcc' => Yii::t('app', 'Bcc'),
            'subject' => Yii::t('app', 'Subject'),
            'text_body' => Yii::t('app', 'Text Body'),
            'html_body' => Yii::t('app', 'Html Body'),
            'attempts_count' => Yii::t('app', 'Attempts Count'),
            'last_attempt' => Yii::t('app', 'Last Attempt'),
            'created' => Yii::t('app', 'Created'),
            'error' => Yii::t('app', 'Error'),
        ];
    }
}
