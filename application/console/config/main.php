<?php
$BUILD_ENGINE_GIT_USER_NAME = getenv('BUILD_ENGINE_GIT_USER_NAME') ?: 'SIL AppBuilder Build Agent';
$BUILD_ENGINE_GIT_USER_EMAIL = getenv('BUILD_ENGINE_GIT_USER_EMAIL') ?: 'appbuilder_buildagent@sil.org';
$BUILD_ENGINE_BUILD_AGENT_CODECOMMIT_GIT_SSH_USER = getenv('BUILD_ENGINE_BUILD_AGENT_CODECOMMIT_GIT_SSH_USER') ?: 'APKAJWRTYGFR4FSZTFNQ';
$BUILD_ENGINE_REPO_URL = getenv('BUILD_ENGINE_REPO_URL') ?: "git@bitbucket.org:silintl/appbuilder-ci-scripts";
$BUILD_ENGINE_REPO_PRIVATE_KEY = getenv('BUILD_ENGINE_REPO_PRIVATE_KEY') ?: "/data/.ssh/id_rsa";
$BUILD_ENGINE_REPO_BRANCH = getenv('BUILD_ENGINE_REPO_BRANCH') ?: "master";
$BUILD_ENGINE_REPO_LOCAL_PATH = getenv('BUILD_ENGINE_REPO_LOCAL_PATH') ?: "/tmp/appbuilder/appbuilder-ci-scripts";
$BUILD_ENGINE_REPO_SCRIPT_DIR = getenv('BUILD_ENGINE_REPO_SCRIPT_DIR') ?: "groovy";
$BUILD_ENGINE_JENKINS_MASTER_URL = getenv('BUILD_ENGINE_JENKINS_MASTER_URL') ?: "http://192.168.70.241";

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
        'buildEngineBuildAgentCodecommitGitSshUser' => $BUILD_ENGINE_BUILD_AGENT_CODECOMMIT_GIT_SSH_USER,
        'buildEngineRepoUrl' => $BUILD_ENGINE_REPO_URL,
        'buildEngineRepoBranch' => $BUILD_ENGINE_REPO_BRANCH,
        'buildEngineRepoPrivateKey' => $BUILD_ENGINE_REPO_PRIVATE_KEY,
        'buildEngineRepoLocalPath' => $BUILD_ENGINE_REPO_LOCAL_PATH,
        'buildEngineRepoScriptDir' => $BUILD_ENGINE_REPO_SCRIPT_DIR,
        'buildEngineJenkinsMasterUrl' => $BUILD_ENGINE_JENKINS_MASTER_URL,
    ],
];
