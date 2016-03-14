<?php

namespace common\components;

interface OperationInterface
{
    public function performOperation();
    public function getMaximumRetries();
    public function getMaximumDelay();
    public function getAlertAfterAttemptCount();
}

