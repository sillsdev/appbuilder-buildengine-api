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
        \Yii::$container->set('s3Client', '\tests\mock\s3client\MockS3Client');
    }
}

