<?php
$BUILD_ENGINE_GIT_USER_NAME = getenv('BUILD_ENGINE_GIT_USER_NAME') ?: 'SIL AppBuilder Build Agent';
$BUILD_ENGINE_GIT_USER_EMAIL = getenv('BUILD_ENGINE_GIT_USER_EMAIL') ?: 'appbuilder_buildagent@sil.org';
$BUILD_ENGINE_REPO_URL = getenv('BUILD_ENGINE_REPO_URL') ?: "git@bitbucket.org:silintl/appbuilder-ci-scripts";
$BUILD_ENGINE_REPO_PRIVATE_KEY = getenv('BUILD_ENGINE_REPO_PRIVATE_KEY') ?: "/data/.ssh/id_rsa";
$BUILD_ENGINE_REPO_BRANCH = getenv('BUILD_ENGINE_REPO_BRANCH') ?: "master";
$BUILD_ENGINE_REPO_LOCAL_PATH = getenv('BUILD_ENGINE_REPO_LOCAL_PATH') ?: "/tmp/appbuilder/appbuilder-ci-scripts";
$BUILD_REQUEST_CALLBACK_URL = getenv('BUILD_REQUEST_CALLBACK_URL') ?: "not set";

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'gii'],
    'controllerNamespace' => 'console\controllers',
    'modules' => [
        'gii' => 'yii\gii\Module',
    ],
    'components' => [
        'log' => [
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
    ],
    'params' => [
        'buildEngineGitUserName' => $BUILD_ENGINE_GIT_USER_NAME,
        'buildEngineGitUserEmail' => $BUILD_ENGINE_GIT_USER_EMAIL,
        'buildEngineRepoUrl' => $BUILD_ENGINE_REPO_URL,
        'buildEngineRepoBranch' => $BUILD_ENGINE_REPO_BRANCH,
        'buildEngineRepoPrivateKey' => $BUILD_ENGINE_REPO_PRIVATE_KEY,
        'buildEngineRepoLocalPath' => $BUILD_ENGINE_REPO_LOCAL_PATH,
        'buildRequestCallbackUrl' => $BUILD_REQUEST_CALLBACK_URL,
    ],
];
