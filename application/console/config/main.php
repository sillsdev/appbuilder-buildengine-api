<?php
$BUILD_ENGINE_GIT_USER_NAME = getenv('BUILD_ENGINE_GIT_USER_NAME') ?: 'SIL AppBuilder Build Agent';
$BUILD_ENGINE_GIT_USER_EMAIL = getenv('BUILD_ENGINE_GIT_USER_EMAIL') ?: 'appbuilder_buildagent@sil.org';
$BUILD_ENGINE_GIT_SSH_USER = getenv('BUILD_ENGINE_GIT_SSH_USER');
$BUILD_ENGINE_REPO_URL = getenv('BUILD_ENGINE_REPO_URL');
$BUILD_ENGINE_REPO_PRIVATE_KEY = getenv('BUILD_ENGINE_REPO_PRIVATE_KEY') ?: "/root/.ssh/id_rsa";
$BUILD_ENGINE_REPO_BRANCH = getenv('BUILD_ENGINE_REPO_BRANCH') ?: "master";
$BUILD_ENGINE_REPO_LOCAL_PATH = getenv('BUILD_ENGINE_REPO_LOCAL_PATH') ?: "/tmp/appbuilder/appbuilder-ci-scripts";
$BUILD_ENGINE_REPO_SCRIPT_DIR = getenv('BUILD_ENGINE_REPO_SCRIPT_DIR') ?: "groovy";
$BUILD_ENGINE_JENKINS_MASTER_URL = getenv('BUILD_ENGINE_JENKINS_MASTER_URL') ?: "unset";
$BUILD_ENGINE_ARTIFACTS_BUCKET = getenv('BUILD_ENGINE_ARTIFACTS_BUCKET')  ?: "unset";
$PUBLISH_JENKINS_MASTER_URL = getenv('PUBLISH_JENKINS_MASTER_URL');

$APPBUILDER_GIT_SSH_USER = getenv('APPBUILDER_GIT_SSH_USER');

$AWS_ACCESS_KEY_ID = getenv('AWS_ACCESS_KEY_ID');
$AWS_SECRET_ACCESS_KEY = getenv('AWS_SECRET_ACCESS_KEY');
$AWS_REGION = getenv('AWS_REGION') ?: "us-east-1";
$AWS_USER_ID = getenv('AWS_USER_ID');

return [
    'id' => 'app-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'gii'],
    'controllerNamespace' => 'console\controllers',
    'modules' => [
      'gii' => [
          'class' => 'yii\gii\Module',
          'allowedIPs' => ['127.0.0.1', '::1', '192.168.0.*', '192.168.70.1'] // adjust this to your needs
      ],
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
        'buildEngineGitSshUser' => $BUILD_ENGINE_GIT_SSH_USER,
        'buildEngineRepoUrl' => $BUILD_ENGINE_REPO_URL,
        'buildEngineRepoBranch' => $BUILD_ENGINE_REPO_BRANCH,
        'buildEngineRepoPrivateKey' => $BUILD_ENGINE_REPO_PRIVATE_KEY,
        'buildEngineRepoLocalPath' => $BUILD_ENGINE_REPO_LOCAL_PATH,
        'buildEngineRepoScriptDir' => $BUILD_ENGINE_REPO_SCRIPT_DIR,
        'buildEngineJenkinsMasterUrl' => $BUILD_ENGINE_JENKINS_MASTER_URL,
        'buildEngineArtifactsBucket' => $BUILD_ENGINE_ARTIFACTS_BUCKET,
        'publishJenkinsMasterUrl' => $PUBLISH_JENKINS_MASTER_URL,
        'appBuilderGitSshUser' => $APPBUILDER_GIT_SSH_USER,
        'awsKeyId' => $AWS_ACCESS_KEY_ID,
        'awsSecretKey' => $AWS_SECRET_ACCESS_KEY,
        'awsRegion' => $AWS_REGION,
        'awsUserId' => $AWS_USER_ID,
    ],
    'controllerMap' => [
        'fixture' => [
            'class' => 'yii\faker\FixtureController',
            'fixtureDataPath' => '@tests/codeception/common/fixtures/data',
            'templatePath' => '@tests/codeception/common/templates/fixtures',
            'namespace' => 'tests\codeception\common\fixtures',
        ],
    ],
];
