<?php
namespace tests\mock\aws\codebuild;

use Codeception\Util\Debug;

class MockPromise
{
    public $buildNumber = '1';

    public function getState() {
        return 'pending';
    }
    public function wait($type)
    {
        $id = 'build_app:7049fc2a-db58-4c33-8d4e-c0c568b25c7b';
        if ($this->buildNumber === '13')
        {
            $id = 'build_app:7049fc2a-db58-4c33-8d4e-c0c568b25c7a';
        }
        $build = [
            'arn' => 'arn',
            'id' => $id,
        ];
        return([
            'build' => $build,
        ]);
    }
}
