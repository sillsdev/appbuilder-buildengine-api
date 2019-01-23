<?php
namespace tests\mock\aws\codebuild;

use Codeception\Util\Debug;

class MockPromise
{
    public $buildNumber = '1';
    public $build = true;

    public function getState() {
        return 'pending';
    }
    public function wait($type)
    {
        if ($this->build === true) {
            switch ($this->buildNumber) {
                case '13':
                    $id = 'build_app:7049fc2a-db58-4c33-8d4e-c0c568b25c7a';
                    break;
                case '30':
                    $id = 'build_app:7049fc2a-db58-4c33-8d4e-c0c568b25c7c';
                    break;
                default:
                    $id = 'build_app:7049fc2a-db58-4c33-8d4e-c0c568b25c7b';
                    break;
            }
        } else {
            switch ($this->buildNumber) {
                case '11':
                    $id = 'publish_app:7049fc2a-db58-4c33-8d4e-c0c568b25c8c';
                    break;
                case '12':
                    $id = 'publish_app:7049fc2a-db58-4c33-8d4e-c0c568b25c8a';
                    break;
                default:
                    $id = 'publish_app:7049fc2a-db58-4c33-8d4e-c0c568b25c8b';
                    break;
            }            
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
