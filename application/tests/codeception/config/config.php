<?php
/**
 * Application configuration shared by all applications and test types
 */
$BUILD_ENGINE_GIT_USER_NAME = 'SIL AppBuilder Build Agent';
$BUILD_ENGINE_GIT_USER_EMAIL = 'appbuilder_buildagent@sil.org';
$BUILD_ENGINE_GIT_SSH_USER = 'APKAJNELREDI767PX3QQ';
$BUILD_ENGINE_REPO_URL = 'ssh://git-codecommit.us-east-1.amazonaws.com/v1/repos/ci-scripts-development-dmoore-windows10';
$BUILD_ENGINE_REPO_PRIVATE_KEY = "/root/.ssh/id_rsa";
$BUILD_ENGINE_REPO_BRANCH =  "master";
$BUILD_ENGINE_REPO_LOCAL_PATH = "/tmp/appbuilder/appbuilder-ci-scripts";
$BUILD_ENGINE_REPO_SCRIPT_DIR =  "groovy";
$BUILD_ENGINE_JENKINS_MASTER_URL =  "http://192.168.70.241:8080";
$BUILD_ENGINE_ARTIFACTS_BUCKET = "sil-appbuilder-artifacts";
$BUILD_ENGINE_ARTIFACTS_BUCKET_REGION = "us-west-2";
$BUILD_ENGINE_SECRETS_BUCKET = "sil-prd-aps-secrets";
$PUBLISH_JENKINS_MASTER_URL = "http://192.168.70.242:8080";
$MYSQL_HOST = getenv('MYSQL_HOST') ?: 'localhost';
$MYSQL_DATABASE = getenv('MYSQL_DATABASE') ?: 'example';
$MYSQL_USER = getenv('MYSQL_USER') ?: 'example';
$MYSQL_PASSWORD = getenv('MYSQL_PASSWORD') ?: 'example';
$TEST_MYSQL_HOST = getenv('TEST_MYSQL_HOST') ?: 'localhost';
$TEST_MYSQL_DATABASE = getenv('TEST_MYSQL_DATABASE') ?: 'example';
$TEST_MYSQL_USER = getenv('TEST_MYSQL_USER') ?: 'example';
$TEST_MYSQL_PASSWORD = getenv('TEST_MYSQL_PASSWORD') ?: 'example';
$ADMIN_EMAIL = 'nobody@nowhere.com';
$APP_ENV = "testing";

$APPBUILDER_GIT_SSH_USER = getenv('APPBUILDER_GIT_SSH_USER');
        \Yii::$app->params['buildEngineGitUserName'] = $BUILD_ENGINE_GIT_USER_NAME;
/*        'buildEngineGitUserEmail' => $BUILD_ENGINE_GIT_USER_EMAIL,
        'buildEngineGitSshUser' => $BUILD_ENGINE_GIT_SSH_USER,
        'buildEngineRepoUrl' => $BUILD_ENGINE_REPO_URL,
        'buildEngineRepoBranch' => $BUILD_ENGINE_REPO_BRANCH,
        'buildEngineRepoPrivateKey' => $BUILD_ENGINE_REPO_PRIVATE_KEY,
        'buildEngineRepoLocalPath' => $BUILD_ENGINE_REPO_LOCAL_PATH,
        'buildEngineRepoScriptDir' => $BUILD_ENGINE_REPO_SCRIPT_DIR,
        'buildEngineJenkinsMasterUrl' => "http://192.168.70.241:8080",
        'buildEngineArtifactsBucket' => $BUILD_ENGINE_ARTIFACTS_BUCKET,
        'buildEngineArtifactsBucketRegion' => $BUILD_ENGINE_ARTIFACTS_BUCKET_REGION,
        'publishJenkinsMasterUrl' => $PUBLISH_JENKINS_MASTER_URL,
        'appBuilderGitSshUser' => $APPBUILDER_GIT_SSH_USER,
        'adminEmail' => $ADMIN_EMAIL,
        'appEnv' => $APP_ENV,*/
        \Yii::$app->params['max_email_attempts'] = 5;
        \Yii::$app->params['max_emails_per_try'] = 20;
return [
    'id' => 'app-backend',
    'basePath' => dirname(__DIR__),
    'components' => [
        'db' => [
             'class' => 'yii\db\Connection',
            "dsn" => "mysql:host=$MYSQL_HOST;dbname=$MYSQL_DATABASE",
            "username" => $MYSQL_USER,
            "password" => $MYSQL_PASSWORD,
            'charset' => 'utf8',
            'emulatePrepare' => false,
            'tablePrefix' => '',
        ],
        'mailer' => [
            'useFileTransport' => true,
        ],
        'urlManager' => [
            'showScriptName' => true,
        ],
        "testDb" => [
            "class" => 'yii\db\Connection',
            "dsn" => "mysql:host=$TEST_MYSQL_HOST;dbname=$TEST_MYSQL_DATABASE",
            "username" => $TEST_MYSQL_USER,
            "password" => $TEST_MYSQL_PASSWORD,
            "emulatePrepare" => false,
            "charset" => "utf8",
            "tablePrefix" => "",
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
        'buildEngineJenkinsMasterUrl' => "http://192.168.70.241:8080",
        'buildEngineArtifactsBucket' => $BUILD_ENGINE_ARTIFACTS_BUCKET,
        'buildEngineArtifactsBucketRegion' => $BUILD_ENGINE_ARTIFACTS_BUCKET_REGION,
        'buildEngineSecretsBucket' => $BUILD_ENGINE_SECRETS_BUCKET,
        'publishJenkinsMasterUrl' => $PUBLISH_JENKINS_MASTER_URL,
        'appBuilderGitSshUser' => $APPBUILDER_GIT_SSH_USER,
        'adminEmail' => $ADMIN_EMAIL,
        'appEnv' => $APP_ENV,
        'max_email_attempts' => 5,
        'max_emails_per_try' => 20,
    ],
];
