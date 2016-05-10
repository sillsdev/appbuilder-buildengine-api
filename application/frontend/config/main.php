    <?php

/* Get frontend-specific config settings from ENV vars or set defaults. */
$FRONT_COOKIE_KEY = getenv('FRONT_COOKIE_KEY') ?: null;

return [
    'id' => 'app-frontend',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'frontend\controllers',
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
            ]
        ],
    ],
    'modules' => [

    ],
    'params' => [],
];
