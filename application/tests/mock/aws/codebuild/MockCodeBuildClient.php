<?php
namespace tests\mock\aws\codebuild;

use Codeception\Util\Debug;
use tests\mock\aws\codebuild\MockPromise;

class MockCodeBuildClient
{
    public static $builds = [];
    public static $batches = [];

    public static function clearGlobals() {
        self::$builds = [];
        self::$batches = [];
    }
    public function startBuildAsync($parms) {
        self::$builds[] = $parms;
        $overrides = $parms['environmentVariablesOverride'];
        $buildOverride = $overrides[0];
        $promise = new MockPromise();
        if ($buildOverride['name'] === 'BUILD_NUMBER') {
            $promise->build = true;
        } else {
            $promise->build = false;
        }
        $promise->buildNumber = $buildOverride['value'];
        return($promise);
    }
    public function batchGetBuilds($parms) {
        self::$batches[] = $parms;
        $ids = $parms['ids'];
        $id = $ids[0];
        $build1 = [
            'currentPhase' => 'BUILD',
            'buildStatus' => 'IN_PROGRESS',
            'buildComplete' => false,
        ];
        $build2 = [
            'currentPhase' => 'COMPLETED',
            'buildStatus' => 'SUCCEEDED',
            'buildComplete' => true,
        ];
        $build3 = [
            'currentPhase' => 'COMPLETED',
            'buildStatus' => 'FAILED',
            'buildComplete' => true,
        ];
        $build4 = [
            'currentPhase' => 'COMPLETED',
            'buildStatus' => 'STOPPED',
            'buildComplete' => true,
        ];
        $build5 = [
            'currentPhase' => 'COMPLETED',
            'buildStatus' => 'TIMED_OUT',
            'buildComplete' => true,
        ];
        $build = $build2;
        switch ($id) {
            case 'build_app:7049fc2a-db58-4c33-8d4e-c0c568b25c7a':
                // codecept_debug("Build in progress");
                $build = $build1;
                break;
            case 'build_app:7447f3ea-00ce-4ad7-ab95-db0e1b25dd5e':
            case 'publish_app:f16d4385-5579-4139-8c1e-a3937e88b23':
                // codecept_debug("Build failed");
                $build = $build3;
                break;
            case 'build_app:f16d4385-5579-4139-8c1e-a3937e88bda':
            case 'publish_app:f16d4385-5579-4139-8c1e-a3937e88b11':
                // codecept_debug("Build stopped");
                $build = $build4;
                break;
            case 'build_app:f16d4385-5579-4139-8c1e-a3937e88b99':
            case 'publish_app:f16d4385-5579-4139-8c1e-a3937e88b99':
                // codecept_debug('Build timed out');
                $build = $build5;
                break;
            default:
                // codecept_debug("Build complete");
                $build = $build2;
        }
        $builds = [$build];
        return ([
            'builds' => $builds,
        ]);
    }
}