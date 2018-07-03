<?php
namespace tests\unit;
use \yii\codeception\DbTestCase;

class UnitTestBase extends DbTestCase
{
    protected $tester;
    public $appConfig = '@tests/codeception/config/config.php';
    /**
     * getPrivateMethod
     *
     * @author	Joe Sexton <joe@webtipblog.com>
     * @param 	string $className
     * @param 	string $methodName
     * @return	ReflectionMethod
     */
    public function getPrivateMethod( $className, $methodName ) {
            $reflector = new \ReflectionClass( $className );
            $method = $reflector->getMethod( $methodName );
            $method->setAccessible( true );

            return $method;
    }
    public function setContainerObjects() {
        \Yii::$container->set('fileUtils', 'tests\mock\common\components\MockFileUtils');
        \Yii::$container->set('jenkinsUtils', 'tests\mock\common\components\MockJenkinsUtils');
        \Yii::$container->set('s3Client', '\tests\mock\aws\s3\MockS3Client');
        \Yii::$container->set('codeCommitClient', '\tests\mock\aws\codecommit\MockCodeCommitClient');
        \Yii::$container->set('codeBuildClient', '\tests\mock\aws\codebuild\MockCodeBuildClient');
        \Yii::$container->set('gitWrapper', '\tests\mock\gitWrapper\MockGitWrapper');
        \Yii::$container->set('iAmWrapper', '\tests\mock\common\components\MockIAmWrapper');
        \Yii::$container->set('iAmClient', '\tests\mock\aws\iam\MockIamClient');
    }
}

