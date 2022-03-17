    <?php

/* Get frontend-specific config settings from ENV vars or set defaults. */
$FRONT_COOKIE_KEY = getenv('FRONT_COOKIE_KEY') ?: null;
$BASE_URL = getenv('API_BASE_URL') ?: null;
$BUILD_ENGINE_PROJECTS_BUCKET = getenv('BUILD_ENGINE_PROJECTS_BUCKET') ?: "unset";
$BUILD_ENGINE_ARTIFACTS_BUCKET_REGION = getenv('BUILD_ENGINE_ARTIFACTS_BUCKET_REGION') ?: "us-east-1";

return [
    'id' => 'app-frontend',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'frontend\controllers',
    'aliases' => [
        "@bower" => "@vendor/bower-asset",
        "@npm" => "@vendor/npm-asset"
    ],
    'components' => [
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'request' => [
            'enableCookieValidation' => true,
            'enableCsrfValidation' => true,
            'cookieValidationKey' => $FRONT_COOKIE_KEY,
            'parsers' => [
                'application/json' => 'yii\web\JsonParser'
            ]
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => false,
            'showScriptName' => false,
            'baseUrl' => $BASE_URL,
            'rules' => [
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'job',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET' => 'index-jobs',
                        'GET <id>' => 'view-job',
                        'DELETE <id>' => 'delete-job',
                        'GET <id>/build' => 'index-builds',
                        'GET <id>/build/<build_id:\d+>' => 'view-build',
                        'DELETE <id>/build/<build_id:\d+>' => 'delete-build',
                        'PUT <id>/build/<build_id:\d+>' => 'publish-build',
                        'POST <id>/build' => 'new-build',
                        'GET <id>/build/<build_id:\d+>/release/<release_id:\d+>' => 'view-release',
                        'DELETE <id>/build/<build_id:\d+>/release/<release_id:\d+>' => 'delete-release',
                    ]
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'project',
                    'pluralize' => false,
                    'extraPatterns' => [  // It doesn't look like these are needed, since they match the action names
                        'POST' => 'new-project',
                        'GET' => 'index-projects',
                        'GET <id>' => 'view-project',
                        'DELETE <id>' => 'delete-project',
                        'PUT <id>' => 'modify-project',
                        'POST <id>/token' => 'create-token'
                    ],
                ],
                [   'class' => 'yii\rest\UrlRule',
                    'controller' => 'system',
                    'pluralize' => 'false',
                ]
            ],
        ],
    ],
    'modules' => [

    ],
    'params' => [
        'buildEngineProjectsBucket' => $BUILD_ENGINE_PROJECTS_BUCKET,
        'buildEngineArtifactsBucketRegion' => $BUILD_ENGINE_ARTIFACTS_BUCKET_REGION,
    ],
];
