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
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'job',
                    'pluralize' => false,
                    'extraPatterns' => [
                        'GET <id>/build' => 'list-builds',
                        'GET <id>/build/<build_id:\d+>' => 'view-build',
                        'PUT <id>/build/<build_id:\d+>' => 'publish-build',
                        'POST <id>/build' => 'new-build',
                    ]
                ],
                [
                    'class' => 'yii\rest\UrlRule',
                    'controller' => 'build',
                    'pluralize' => false
                ],
            ]
        ],
    ],
    'modules' => [

    ],
    'params' => [],
];
