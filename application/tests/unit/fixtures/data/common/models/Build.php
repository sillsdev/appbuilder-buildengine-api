<?php

use common\helpers\Utils;

return [
    'build1' => [
        'id' => 11,
        'job_id' => 22,
        'status' => 'completed',
        'build_number' => 1,
        'result' => 'SUCCESS',
        'error' => NULL,
        'artifact_url' => NULL,
        'created' => Utils::getDatetime(),
        'updated' => Utils::getDatetime(),
        'channel' => 'unpublished',
        'version_code' => 1,
    ],
    'build2' => [
        'id' => 12,
        'job_id' => 22,
        'status' => 'completed',
        'build_number' => 1,
        'result' => 'SUCCESS',
        'error' => NULL,
        'artifact_url' => 'https://s3-us-west-2.amazonaws.com/sil-appbuilder-artifacts/testing/jobs/build_scriptureappbuilder_22/1/Test-1.0.apk',
        'created' => Utils::getDatetime(),
        'updated' => Utils::getDatetime(),
        'channel' => 'unpublished',
        'version_code' => 2,
    ],
    'build3' => [
        'id' => 13,
        'job_id' => 22,
        'status' => 'initialized',
        'build_number' => NULL,
        'result' => NULL,
        'error' => NULL,
        'artifact_url' => NULL,
        'created' => Utils::getDatetime(),
        'updated' => Utils::getDatetime(),
        'channel' => 'unpublished',
        'version_code' => NULL,
    ],
    'build4' => [
        'id' => 14,
        'job_id' => 22,
        'status' => 'active',
        'build_number' => 2,
        'result' => NULL,
        'error' => NULL,
        'artifact_url' => NULL,
        'created' => Utils::getDatetime(),
        'updated' => Utils::getDatetime(),
        'channel' => 'unpublished',
        'version_code' => 3,
    ],
    'build5' => [
        'id' => 15,
        'job_id' => 23,
        'status' => 'active',
        'build_number' => 3,
        'result' => NULL,
        'error' => NULL,
        'artifact_url' => NULL,
        'created' => Utils::getDatetime(),
        'updated' => Utils::getDatetime(),
        'channel' => 'unpublished',
        'version_code' => 4,
    ],
    'build6' => [
        'id' => 16,
        'job_id' => 24,
        'status' => 'active',
        'build_number' => 4,
        'result' => NULL,
        'error' => NULL,
        'artifact_url' => NULL,
        'created' => Utils::getDatetime(),
        'updated' => Utils::getDatetime(),
        'channel' => 'unpublished',
        'version_code' => 5,
    ],
];
