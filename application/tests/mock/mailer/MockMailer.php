<?php
namespace tests\mock\mailer;

use Codeception\Util\Debug;

class MockMailer
{

    public $results;

    public $to;
    public $subject;
    public $goodCount = 0;
    public $failedCount = 0;
    public $exceptionCount = 0;

    public function compose()
    {
        $this->results = [];
        return $this;
    }


    public function setFrom($fromEmail)
    {
        $this->results['from'] = $fromEmail;
        return $this;
    }

    public function setTo($email)
    {
        $this->results['to'] = $email;
        $this->to = $email;
        return $this;
    }

    public function setCc($email)
    {
        $this->results['cc'] = $email;
        return $this;
    }

    public function setBcc($email)
    {
        $this->results['bcc'] = $email;
        return $this;
    }

    public function setSubject($subject)
    {
        $this->results['subject'] = $subject;
        $this->subject = $subject;
        return $this;
    }

    public function setTextBody($body)
    {
        $this->results['body'] = $body;
        return $this;
    }


    /**
     * If the subject of the email is not set, return true.
     * If it is 'fail', return false.
     * If it is 'error', throw an Exception.
     * Otherwise, return true.
     *
     * @return bool
     * @throws \Exception
     */
    public function send()
    {
        if ( ! isset($this->results['subject'])) {
            $this->goodCount = $this->goodCount + 1;
            return true;
        }

        if ($this->results['subject'] === 'fail') {
            $this->failedCount = $this->failedCount + 1;
            return false;
        }

        if ($this->results['subject'] === 'error') {
            $this->exceptionCount = $this->exceptionCount + 1;
            throw new \Exception('Mock Mailer Failed');
        }

        $this->goodCount = $this->goodCount + 1;
        return true;
    }

}