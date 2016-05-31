<?php
namespace tests\unit\console\components;
use console\components\SyncScriptsAction;
use tests\unit\UnitTestBase;

use tests\unit\fixtures\common\models\JobFixture;
use tests\unit\fixtures\common\models\BuildFixture;
use tests\unit\fixtures\common\models\ReleaseFixture;
use tests\mock\console\controllers\MockCronController;
use tests\mock\common\components\MockFileUtils;
use tests\mock\gitWrapper\MockGitWrapper;
use tests\mock\gitWrapper\MockGitWorkingCopy;
use common\models\Job;

class SyncScriptsActionTest extends UnitTestBase
{
    /**
     * @var \UnitTester
     */

    protected function _before()
    {
    }

    protected function _after()
    {
    }
    public function fixtures()
    {
        return [
            'job' => JobFixture::className(),
            'build' => BuildFixture::className(),
        ];
    }
    public function testGetRepoClone()
    {
        $this->setContainerObjects();
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        MockFileUtils::setFileExistsVar(false);
        $method = $this->getPrivateMethod('console\components\SyncScriptsAction', 'getRepo');
        $repoWorkingCopy = $method->invokeArgs($syncScriptsAction, array( ));
        $expectedUrl = "ssh://APKAJNELREDI767PX3QQ@git-codecommit.us-east-1.amazonaws.com/v1/repos/ci-scripts-development-dmoore-windows10";
        $this->assertEquals($expectedUrl, MockGitWrapper::getTestUrl(), " *** Wrong URL");
        $expectedPath = "/tmp/appbuilder/appbuilder-ci-scripts";
        $this->assertEquals($expectedPath, MockGitWrapper::getTestPath(), " *** Wrong Path");
        $expectedKey = "/root/.ssh/id_rsa";
        $this->assertEquals($expectedKey, MockGitWrapper::getTestPrivateKey(), " *** Wrong private key");
        $expectedUserName = "SIL AppBuilder Build Agent";
        $this->assertEquals($expectedUserName, $repoWorkingCopy->envParms['user.name'], " *** Wrong User Name");
        $expectedEMail = "appbuilder_buildagent@sil.org";
        $this->assertEquals($expectedEMail, $repoWorkingCopy->envParms['user.email'], " *** Wrong User Email");
        $expectedBranch = "master";
        $this->assertEquals($expectedBranch, $repoWorkingCopy->checkoutBranch, " *** Wrong branch checked out");
    }
     public function testGetRepoInit()
    {
        $this->setContainerObjects();
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        MockFileUtils::setFileExistsVar(true);
        MockGitWorkingCopy::setExceptionOnCheckout(true);
        $method = $this->getPrivateMethod('console\components\SyncScriptsAction', 'getRepo');
        $repoWorkingCopy = $method->invokeArgs($syncScriptsAction, array( ));
        $this->assertNull(MockGitWrapper::getTestUrl(), " *** Url isn't set on init");
        $expectedPath = "/tmp/appbuilder/appbuilder-ci-scripts";
        $this->assertEquals($expectedPath, MockGitWrapper::getTestPath(), " *** Wrong Path");
        $expectedKey = "/root/.ssh/id_rsa";
        $this->assertEquals($expectedKey, MockGitWrapper::getTestPrivateKey(), " *** Wrong private key");
        $expectedBranch = "master";
        $this->assertEquals($expectedBranch, $repoWorkingCopy->checkoutBranch, " *** Wrong branch checked out");
        $this->assertEquals(1, $repoWorkingCopy->resetCount, " *** Reset should have been called");
        $this->assertEquals("origin/master", $repoWorkingCopy->lastResetBranch, " *** Last Branch called with reset wrong");
        $this->assertEquals(1, $repoWorkingCopy->checkoutNewBranchCount, " *** checkoutNewBranch should have been called");
    }
    public function testCreateScriptsUpdate()
    {
        $this->setContainerObjects();
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        $syncScriptsAction->performAction();  // Run this to set $git
        $gitSubstPatterns = [ '/ssh:\/\/([0-9A-Za-z]*)@git-codecommit/' => "ssh://APKAJNELREDI767PX3QQ@git-codecommit",
                              '/ssh:\/\/git-codecommit/' => "ssh://APKAJNELREDI767PX3QQ@git-codecommit" ];
        $method = $this->getPrivateMethod('console\components\SyncScriptsAction', 'createBuildScript');
        $job = Job::findOne(['id' => 22]);
        $testPath = "/tmp/appbuilder/appbuilder-ci-scripts/groovy";

        list($updatesString, $added, $updated) = $method->invokeArgs($syncScriptsAction, array($job,$gitSubstPatterns, $testPath));
        $expectedString = "update: build_scriptureappbuilder_22.groovy".PHP_EOL;
        $this->assertEquals($expectedString, $updatesString, " *** Update string incorrect");
        $this->assertEquals(1, $updated, " *** One update");
        $this->assertEquals(0, $added, " *** None added");
        $expectedString = "/tmp/appbuilder/appbuilder-ci-scripts/groovy/build_scriptureappbuilder_22.groovy";
        $this->assertEquals($expectedString, MockGitWorkingCopy::$lastAdded, " *** Wrong last filename added to Git");
    }
    public function testCreateScriptsAdd()
    {
        $this->setContainerObjects();
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        $syncScriptsAction->performAction();  // Run this to set $git
        MockFileUtils::setFileExistsVar(false);
        $gitSubstPatterns = [ '/ssh:\/\/([0-9A-Za-z]*)@git-codecommit/' => "ssh://APKAJNELREDI767PX3QQ@git-codecommit",
                              '/ssh:\/\/git-codecommit/' => "ssh://APKAJNELREDI767PX3QQ@git-codecommit" ];
        $method = $this->getPrivateMethod('console\components\SyncScriptsAction', 'createBuildScript');
        $job = Job::findOne(['id' => 22]);
        $testPath = "/tmp/appbuilder/appbuilder-ci-scripts/groovy";

        list($updatesString, $added, $updated) = $method->invokeArgs($syncScriptsAction, array($job,$gitSubstPatterns, $testPath));
        $expectedString = "add: build_scriptureappbuilder_22.groovy".PHP_EOL;
        $this->assertEquals($expectedString, $updatesString, " *** Update string incorrect");
        $this->assertEquals(0, $updated, " *** None updated");
        $this->assertEquals(1, $added, " *** One added");
        $expectedString = "/tmp/appbuilder/appbuilder-ci-scripts/groovy/build_scriptureappbuilder_22.groovy";
        $this->assertEquals($expectedString, MockGitWorkingCopy::$lastAdded, " *** Wrong last filename added to Git");
    }
    public function testRemoveScripts()
    {
        $this->setContainerObjects();
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        $syncScriptsAction->performAction();  // Run this to set $git
        $method = $this->getPrivateMethod('console\components\SyncScriptsAction', 'removeScriptIfNoJobRecord');
        $scriptFile = "/tmp/appbuilder/appbuilder-ci-scripts/groovy/build_scriptureappbuilder_1.groovy";
        $jobs = [];
        $jobs["scriptureappbuilder_2"] = 1;
        $jobs["scriptureappbuilder_3"] = 1;  
        list($retString, $removed) = $method->invokeArgs($syncScriptsAction, array($scriptFile, $jobs));
        $this->assertEquals(1, $removed, " *** remove 1");
        $expectedString = "remove: build_scriptureappbuilder_1".PHP_EOL;
        $this->assertEquals($expectedString, $retString, " *** returned string does not match");
    }
    public function testRemoveScriptsNoRemove()
    {
        $this->setContainerObjects();
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        $syncScriptsAction->performAction();  // Run this to set $git
        $method = $this->getPrivateMethod('console\components\SyncScriptsAction', 'removeScriptIfNoJobRecord');
        $scriptFile = "/tmp/appbuilder/appbuilder-ci-scripts/groovy/build_scriptureappbuilder_2.groovy";
        $jobs = [];
        $jobs["scriptureappbuilder_2"] = 1;
        $jobs["scriptureappbuilder_3"] = 1;  
        list($retString, $removed) = $method->invokeArgs($syncScriptsAction, array($scriptFile, $jobs));
        $this->assertEquals(0, $removed, " *** remove 1");
        $expectedString = "";
        $this->assertEquals($expectedString, $retString, " *** returned string does not match");
    }
    public function testExceptionIfUrlNotSSH()
    {
        $this->setExpectedException('\Exception');
        $this->setContainerObjects();
        \Yii::$app->params['buildEngineRepoUrl'] = 'http://git-codecommit.us-east-1.amazonaws.com/v1/repos/ci-scripts-development-dmoore-windows10';
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        $syncScriptsAction->performAction();
    }
    public function testExceptionIfNoSSHUser()
    {
        $this->setExpectedException('\Exception');
        $this->setContainerObjects();
        \Yii::$app->params['buildEngineGitSshUser'] = null;
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        $syncScriptsAction->performAction();
    }
   public function testPerformActionForSyncScripts()
    {
        $this->setContainerObjects();
        MockGitWrapper::resetTest();
        $cronController = new MockCronController();
        $syncScriptsAction = new SyncScriptsAction($cronController);
        MockFileUtils::setFileExistsVar(false);
        $syncScriptsAction->performAction();
        $expectedString = "cron add=0 update =8 delete=0".PHP_EOL."update: build_scriptureappbuilder_22.groovy"
                .PHP_EOL."update: publish_scriptureappbuilder_22.groovy"
                .PHP_EOL."update: build_scriptureappbuilder_23.groovy"
                .PHP_EOL."update: publish_scriptureappbuilder_23.groovy"
                .PHP_EOL."update: build_scriptureappbuilder_24.groovy"
                .PHP_EOL."update: publish_scriptureappbuilder_24.groovy"
                .PHP_EOL."update: build_scriptureappbuilder_25.groovy"
                .PHP_EOL."update: publish_scriptureappbuilder_25.groovy".PHP_EOL;
        $this->assertEquals($expectedString, MockGitWorkingCopy::$lastCommitLine, " *** Last commit line");
    }
}
