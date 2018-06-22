<?php
namespace tests\mock\aws\codecommit;

use Codeception\Util\Debug;

class MockCodeCommitClient
{
    public static $repos = [];
    public static $branches = [];
    public static $getRepo = [];

    public static function clearGlobals() {
        self::$repos = [];
        self::$branches = [];
        self::$getRepo = [];
    }
    public function startBuildAsync($parms) {
        self::$repos[] = $parms;
        $metadata = [
            'cloneUrlHttp' => 'https://git-codecommit.us-east-1.amazonaws.com/v1/repos/scriptureappbuilder-LSDEV-eng-t4test',
        ];
        return([
            'repositoryMetadata' => $metadata,
        ]);
    }
    public function getBranch($parms) {
        self::$branches[] = $parms;
        $branch = [
            'commitId' => '07fc609fc5c2344afcf60d0f97cc7bb6f1945ede',
        ];
        return([
            'branch' => $branch,
        ]);
    }
    public function getRepository($parms) {
        self::$getRepo[] = $parms;
        $metadata = [
            'cloneUrlHttp' => 'https://git-codecommit.us-east-1.amazonaws.com/v1/repos/scriptureappbuilder-LSDEV-eng-t4test',
        ];
        return([
            'repositoryMetadata' => $metadata,
        ]);
    }
}