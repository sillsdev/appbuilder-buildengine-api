<?php

return [
    'eQueue1' => [
        'id' => 1,
        'to' => 'rec1@dom.com',
        'cc' => 'cc1@dom.com',
        'bcc' => 'bcc1@dom.com',
        'subject' => 'Your approval is needed.',
        'text_body' => 'Please give your approval at this link.',
        'attempts_count' => 2,
    ],
    'eQueue2' => [
        'id' => 2,
        'to' => 'rec2@dom.com',
        'cc' => 'cc2@dom.com',
        'bcc' => 'bcc2@dom.com',
        'subject' => 'Account created.',
        'text_body' => 'Your new account was created.',
    ],
];