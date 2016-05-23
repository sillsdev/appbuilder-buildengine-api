<?php
/**
 * Application config for common unit tests
 */
$BUILD_ENGINE_GIT_USER_NAME = getenv('BUILD_ENGINE_GIT_USER_NAME') ?: 'SIL AppBuilder Build Agent';
$BUILD_ENGINE_GIT_USER_EMAIL = getenv('BUILD_ENGINE_GIT_USER_EMAIL') ?: 'appbuilder_buildagent@sil.org';
$BUILD_ENGINE_GIT_SSH_USER = getenv('BUILD_ENGINE_GIT_SSH_USER');
$BUILD_ENGINE_REPO_URL = getenv('BUILD_ENGINE_REPO_URL');
$BUILD_ENGINE_REPO_PRIVATE_KEY = getenv('BUILD_ENGINE_REPO_PRIVATE_KEY') ?: "/root/.ssh/id_rsa";
$BUILD_ENGINE_REPO_BRANCH = getenv('BUILD_ENGINE_REPO_BRANCH') ?: "master";
$BUILD_ENGINE_REPO_LOCAL_PATH = getenv('BUILD_ENGINE_REPO_LOCAL_PATH') ?: "/tmp/appbuilder/appbuilder-ci-scripts";
$BUILD_ENGINE_REPO_SCRIPT_DIR = getenv('BUILD_ENGINE_REPO_SCRIPT_DIR') ?: "groovy";
$BUILD_ENGINE_JENKINS_MASTER_URL = getenv('BUILD_ENGINE_JENKINS_MASTER_URL') ?: "http://192.168.70.241:8080";
$BUILD_ENGINE_ARTIFACT_URL_BASE = getenv('BUILD_ENGINE_ARTIFACT_URL_BASE') ?: "unset";
$PUBLISH_JENKINS_MASTER_URL = getenv('PUBLISH_JENKINS_MASTER_URL');

$APPBUILDER_GIT_SSH_USER = getenv('APPBUILDER_GIT_SSH_USER');
return yii\helpers\ArrayHelper::merge(
    require(YII_APP_BASE_PATH . '/common/config/main.php'),
    require(YII_APP_BASE_PATH . '/common/config/main-local.php'),
    require(dirname(__DIR__) . '/config.php'),
    require(dirname(__DIR__) . '/unit.php'),
    [
        'id' => 'app-common',
        'basePath' => dirname(__DIR__),
    ]
);
