<?php
namespace tests\mock\aws\sts;

use Codeception\Util\Debug;

class MockStsClient
{
    public function GetFederationToken(array $params)
    {
        $user = $params['Name'];

        $result = [
            'Credentials' => [
                'AccessKeyId' => 'AKIAIOSFODNN7EXAMPLE',
                'Expiration' => '2019-02-15T06:30:26Z',
                'SecretAccessKey' => 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYzEXAMPLEKEY',
                'SessionToken' => 'AQoDYXdzEPT//////////wEXAMPLEtc764bNrC9SAPBSM22wDOk4x4HIZ8j4FZTwdQWLWsKWHGBuFqwAeMicRXmxfpSPfIeoIYRqTflfKD8YUuwthAx7mSEI/qkPpKPi/kMcGdQrmGdeehM4IC1NtBmUpp2wUE8phUZampKsburEDy0KPkyQDYwT7WZ0wq5VSXDvp75YU9HFvlRd8Tx6q6fE8YQcHNVXAkiY9q6d+xo0rKwT38xVqr7ZD0u0iPPkUL64lIZbqBAz+scqKmlzm8FDrypNC9Yjc8fPOLn9FX9KSYvKTr4rvx3iSIlTJabIQwj2ICCR/oLxBA==',
            ],
            'FederatedUser' => [
                'Arn' => 'arn:aws:sts::123456789012:federated-user/' . $user,
                'FederatedUserId' => '123456789012:' . $user,
            ],
            'PackedPolicySize' => 6,
        ];

        return $result;
    }
}