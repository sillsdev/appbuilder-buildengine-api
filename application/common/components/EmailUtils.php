<?php
namespace common\components;

use common\components\Appbuilder_logger;
use common\models\EmailQueue;
use common\helpers\Utils;

class EmailUtils
{

    const EventLogType = "Email_Sent";

    const DT_Format = 'Y-m-d H:i:s';

    public static function getMaxAttempts()
    {
        $maxParam = 'max_email_attempts';
        $config = \Yii::$app->params;
        if (Utils::isArrayEntryTruthy($config, $maxParam) ) {
            return $config[$maxParam];
        }
        //default
        return 6;
    }

    public static function getMaxEmails()
    {
        $maxParam = 'max_emails_per_try';
        $config = \Yii::$app->params;
        if (Utils::isArrayEntryTruthy($config, $maxParam) ) {
            return $config[$maxParam];
        }

        return 21;
    }

    public static function getAdminEmailAddress()
    {
        $adminEmailParam = 'adminEmail';
        $config = \Yii::$app->params;
        if (Utils::isArrayEntryTruthy($config, $adminEmailParam) ) {
            return $config[$adminEmailParam];
        }

        return 'nobody@nowhere.com';
    }
    /**
     * @param $email a Mailer email object
     * @return array with two elements ...
     *    - bool whether the email got sent successfully
     *    - string for a possible error method
     */
    public static function trySendingEmail($email) {

        $logger = new Appbuilder_logger("EmailUtils");
        try {
            $gotSent = $email->send();

            // log email sent
            if ($gotSent) {
                $logContents = [
                    'to' => $email->to,
                    'subject' => $email->subject
                ];
                $logger->appbuilderInfoLog($logContents);
            }

            return [$gotSent, ""];
        } catch (\Exception $e) {
            // the to property seems to come as ... array('to@dom.com' => null);
            if (is_array($email->to)) {
                if(!empty($email->to)) {
                    $to = array_keys($email->to)[0];
                } else {
                    $to = "No To Address";
                }
            } else {
                $to = $email->to;
            }

            $logContents = [
                'to' => $to,
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
            ];
            $logger->appbuilderExceptionLog($logContents, $e);

            $errorMsg =  'Error code: ' . $e->getCode() . '. ' . $e->getMessage();
            return [false, $errorMsg];
        }
    }



    /**
     * Sends emails for all the EmailQueue entries.
     * Also, adds and EventLog record regarding the email being sent.
     *
     * @param \Mailer [optional] for unit testing
     * @return array with two elements ...
     *     1) an array with the pk's and to address of the emails that were sent
     *     2) an array of the error messages that were encountered
     */
    public static function sendEmailQueue($mailer=null)
    {
        if ($mailer === null) {
            $mailer = \Yii::$app->mailer;
        }

        $maxAttempts = self::getMaxAttempts();

        $maxEmails = self::getMaxEmails();
        $emailQueues = EmailQueue::find()
                       ->where('attempts_count <= ' . $maxAttempts)
                       ->orWhere(['attempts_count' => null])
                       ->limit($maxEmails)
                       ->all();

        $errors = array();
        $eQueuesSent = array();

        foreach($emailQueues as $nextEQueue) {
            // for reporting the results
            $eQueueID = 'EmailQueue ' . $nextEQueue->id . ': ' . $nextEQueue->to;

            $newEmail = $mailer->compose()
                ->setFrom(EmailQueue::getFromAddress())
                ->setTo($nextEQueue->to)
                ->setSubject($nextEQueue->subject)
                ->setTextBody($nextEQueue->text_body);

            $setMethods = ['setCc' => $nextEQueue->cc,
                           'setBcc' => $nextEQueue->bcc,
                           'setHtmlBody' => $nextEQueue->html_body];

            foreach ($setMethods as $method => $value) {
                if ($value) {
                    $newEmail->$method($value);
                }
            }

            list($sent, $errorMsg) = self::trySendingEmail($newEmail);

            if ( ! $sent) {
                $errors[] = $eQueueID . ". Tried to send but failed.";

                $now = new \DateTime();
                $now = $now->format(self::DT_Format);

                $nextEQueue->error = $errorMsg;
                $nextEQueue->attempts_count++;
                $nextEQueue->last_attempt = $now;
                $nextEQueue->save();
                continue;
            }

            $eQueuesSent[] = $eQueueID;

            $logger = new Appbuilder_logger("EmailUtils");
            $logContents = [
                    'to' => $nextEQueue->to,
                    'subject' => $nextEQueue->subject
                ];

            $logger->appbuilderInfoLog($logContents);
            $nextEQueue->delete();
        }

        return array($eQueuesSent, $errors);
    }



    /**
     * Attemps to send an email.
     * If an exception is thrown, does a Yii:error log,
     *    creates a corresponding EmailQueue entry and returns false.
     * If the email fails to send, creates a corresponding EmailQueue entry
     *   and returns false.
     * Otherwise, returns true.
     *
     *
     * @param string $to
     * @param string $subject
     * @param string $textBody
     * @param string $cc
     * @param string $bcc
     * @param null $htmlBody
     * @param null $mailer for unit testing
     * @return bool
     */
    public static function sendEmailOrQueueIt($to, $subject, $textBody, $cc, $bcc,
                                              $htmlBody = null,
                                              $mailer = null)
    {

        if ($mailer === null) {
            $mailer = \Yii::$app->mailer;
        }

        $from = EmailQueue::getFromAddress();

        $newEmail = $mailer->compose()
            ->setFrom($from)
            ->setTo($to)
            ->setSubject($subject)
            ->setTextBody($textBody);

        $setMethods = ['setCc' => $cc, 'setBcc' => $bcc, 'setHtmlBody' => $htmlBody];

        foreach ($setMethods as $method => $value) {
            if ($value) {
                $newEmail->$method($value);
            }
        }

        list($sent, $errorMsg) = self::trySendingEmail($newEmail);

        if ($sent) {
            return true;
        }

        self::QueueEmail($to, $subject, $textBody, $cc, $bcc, $htmlBody);

        return false;
    }


    /**
     * Creates an  EmailQueue entry, saves it and returns it.
     *
     * @param string $to
     * @param string $subject
     * @param string $textBody
     * @param string $cc
     * @param string $bcc
     * @param null $htmlBody
     * @return bool
     */
    public static function QueueEmail($to, $subject, $textBody, $cc, $bcc,
                                                            $htmlBody = null)
    {
        $emailQueue = new EmailQueue;
        $emailQueue->to = $to;
        $emailQueue->cc = $cc;
        $emailQueue->bcc = $bcc;
        $emailQueue->subject = $subject;
        $emailQueue->text_body = $textBody;
        $emailQueue->html_body = $htmlBody;

        $emailQueue->save();

        return $emailQueue;
    }
}