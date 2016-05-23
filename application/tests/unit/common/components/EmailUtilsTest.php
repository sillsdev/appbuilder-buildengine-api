<?php

namespace tests\unit\common\components;

use tests\mock\mailer\MockMailer;
use \yii\codeception\DbTestCase;

use common\components\EmailUtils;
use common\models\EmailQueue;

use tests\unit\fixtures\common\models\EmailQueueFixture;

class EmailUtilsTest extends DbTestCase
{
    public $appConfig = '@tests/codeception/config/config.php';

    public $mailFolder;

    private $startConfig;

    public function fixtures()
    {
        return [
            'email_queue' => EmailQueueFixture::className(),
        ];
    }

    protected function _before()
    {
        $this->mailFolder = __DIR__ . '/../../../../runtime/mail';
        // delete runtime/mail files
        $files = glob($this->mailFolder . '/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file))
                unlink($file); // delete file
        }
    }
    /**
     * This method is only here because if I don't put it in, the first test
     * fails because the params isn't loaded.  Don't know why, but this fixed it
     */
    public function testDummy()
    {
        $this->assertEquals(1,1);
    }
    public function testSendEmailQueue_OK()
    {
//        ParamFixture::setParams();
        $mailer = new MockMailer();
        $initialEmailQueueCount = EmailQueue::find()->count();
        // use real mailer
        list($sent_results, $error_results) = EmailUtils::sendEmailQueue($mailer);
        $this->assertEquals([], $error_results, " *** Didn't expect any errors");

        $expected = ['EmailQueue 1: rec1@dom.com', 'EmailQueue 2: rec2@dom.com'];
        $this->assertEquals($expected, $sent_results, " *** Mismatching sent emails");

        $msg = " *** EmailQueues didn't get deleted correctly.";
        $this->assertEquals(0, EmailQueue::find()->count(), $msg);

        $msg = " *** Wrong number of emails sent/saved.";
        $emailFileCount = $mailer->goodCount;
        $this->assertEquals(2, $emailFileCount, $msg);
    }

    public function testSendEmailQueue_OneError()
    {
        $firstEmail = EmailQueue::findOne(1);
        $firstEmail->subject =  'error';
        $firstEmail->save();
        $firstAttempts = $firstEmail->attempts_count;

        $initialEmailQueueCount = EmailQueue::find()->count();
        $mailer = new MockMailer();

        list($sent_results, $error_results) = EmailUtils::sendEmailQueue($mailer);

        $expected = ['EmailQueue 1: rec1@dom.com. Tried to send but failed.'];
        $this->assertEquals($expected, $error_results, " *** Wrong errors");

        $expected = ['EmailQueue 2: rec2@dom.com'];
        $this->assertEquals($expected, $sent_results, " *** Mismatching sent emails");

        $msg = " *** EmailQueues didn't get deleted correctly.";
        $this->assertEquals(1, EmailQueue::find()->count(), $msg);

        $expected = 1;
        $msg = " *** Wrong number of errors recorded.";
        $this->assertEquals($expected, $mailer->exceptionCount, $msg);

        $firstEmail = EmailQueue::findOne(1);

        $expected = "Error code: 0. Mock Mailer Failed";
        $msg = " *** Bad error message got saved.";
        $this->assertEquals($expected, $firstEmail->error, $msg);

        $expected = $firstAttempts + 1;
        $msg = " *** Bad attempts_count after error";
        $this->assertEquals($expected, $firstEmail->attempts_count, $msg);
    }



    public function testSendEmailOrQueueIt_False()
    {
        $initialEmailQueueCount = EmailQueue::find()->count();
        $mailer = new MockMailer();

        // 'fail' as subject makes MockMailer return false
        $results =  EmailUtils::sendEmailOrQueueIt('to@dom.com', 'fail', 'body',
            'cc@dom.com', 'bcc@dom.com', null, $mailer);

        $this->assertFalse($results, " *** Expected false return value for ".
            " mail not sent.");

        $finalEmailQueueCount = EmailQueue::find()->count();

        $msg = " *** Wrong number of Email Queues";
        $this->assertEquals($initialEmailQueueCount+1, $finalEmailQueueCount, $msg);

        $emailQueues = EmailQueue::find()->asArray()->all();

        $emailQueueValues = array_values($emailQueues);
        $lastQueue = array_pop($emailQueueValues);

        $msg = " *** Bad To field on EmailQueue";
        $this->assertEquals('to@dom.com', $lastQueue['to'], $msg);

        $msg = " *** Bad CC field on EmailQueue";
        $this->assertEquals('cc@dom.com', $lastQueue['cc'], $msg);

        $msg = " *** Bad BCC field on EmailQueue";
        $this->assertEquals('bcc@dom.com', $lastQueue['bcc'], $msg);

        $msg = " *** Bad subject on EmailQueue";
        $this->assertEquals('fail', $lastQueue['subject'], $msg);

        $msg = " *** Bad text_body on EmailQueue";
        $this->assertEquals('body', $lastQueue['text_body'], $msg);
    }


    public function testSendEmailOrQueueIt_Error()
    {
        $initialEmailQueueCount = EmailQueue::find()->count();
        $mailer = new MockMailer();

        // 'fail' as subject makes MockMailer return false
        $results =  EmailUtils::sendEmailOrQueueIt('to@dom.com', 'error', 'body',
            'cc@dom.com', 'bcc@dom.com', null, $mailer);

        $this->assertFalse($results, " *** Expected false return value for ".
            " mail not sent.");

        $finalEmailQueueCount = EmailQueue::find()->count();

        $msg = " *** Wrong number of Email Queues";
        $this->assertEquals($initialEmailQueueCount+1, $finalEmailQueueCount, $msg);

        $emailQueues = EmailQueue::find()->asArray()->all();

        $emailQueueValues = array_values($emailQueues);
        $lastQueue = array_pop($emailQueueValues);

        $msg = " *** Bad To field on EmailQueue";
        $this->assertEquals('to@dom.com', $lastQueue['to'], $msg);

        $msg = " *** Bad subject on EmailQueue";
        $this->assertEquals('error', $lastQueue['subject'], $msg);

        $msg = " *** Bad text_body on EmailQueue";
        $this->assertEquals('body', $lastQueue['text_body'], $msg);
    }


    public function testSendEmailOrQueueIt_OK()
    {
        $initialEmailQueueCount = EmailQueue::find()->count();
        $mailer = new MockMailer();

        // 'fail' as subject makes MockMailer return false
        $results =  EmailUtils::sendEmailOrQueueIt('to@dom.com', 'no problem', 'body',
            'cc@dom.com', 'bcc@dom.com', null, $mailer);

        $this->assertTrue($results, " *** Expected true return value for ".
            "mail being sent.");

        $finalEmailQueueCount = EmailQueue::find()->count();

        $msg = " *** Wrong number of Email Queues";
        $this->assertEquals($initialEmailQueueCount, $finalEmailQueueCount, $msg);
    }

    public function testGetMaxEmailAttempts_Default()
    {
        $params = \Yii::$app->params;
        unset($params['max_email_attempts']);
        \Yii::$app->params = $params;

        $results = EmailUtils::getMaxAttempts();
        $this->assertEquals(6, $results);
    }

    public function testGetMaxEmailAttempts_FromParams()
    {
        $newValue = 2;
        $params = \Yii::$app->params;
        $params['max_email_attempts'] = $newValue;
        \Yii::$app->params = $params;

        $results = EmailUtils::getMaxAttempts();
        $this->assertEquals($newValue, $results);
    }

    public function testGetMaxEmails_Default()
    {
        $params = \Yii::$app->params;
        unset($params['max_emails_per_try']);
        \Yii::$app->params = $params;

        $results = EmailUtils::getMaxEmails();
        $this->assertEquals(21, $results);
    }

    public function testGetMaxEmails_FromParams()
    {
        $newValue = 12;
        $params = \Yii::$app->params;
        $params['max_emails_per_try'] = $newValue;
        \Yii::$app->params = $params;

        $results = EmailUtils::getMaxEmails();
        $this->assertEquals($newValue, $results);
    }
}