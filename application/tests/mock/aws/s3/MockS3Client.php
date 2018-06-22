<?php
namespace tests\mock\aws\s3;

use Codeception\Util\Debug;

class MockS3Client
{
    public static $gets = [];
    public static $puts = [];
    public static $copies = [];
    public static $lists = [];
    public static $deletes = [];
    public static $deletedBucket;
    public static $deletedKey;
    public function putObject($parms) {
        self::$puts[] = $parms;
    }
    public function getObject($parms) {
        $key = $parms['Key'];
        $bucket = $parms['Bucket'];
        self::$gets[] = $parms;
        $pieces = explode("/", $key);
        $filename = $pieces[4];
        switch ($filename) {
            case 'version_code.txt':
                $body = "42";
                break;
            default:
                $body = "Test body";
        }
        return([
            'Body' => $body,
        ]);
    }
    public function copyObject($parms) {
        self::$copies[] = $parms;
    }
    public function listObjectsV2($params) {
        self::$lists[] = $params;
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/manifest.txt',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/about.txt',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/Kuna_Gospels-1.0.apk',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/package_name.txt',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/default-language.txt',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/full_description.txt',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/images/featureGraphic.png',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/images/icon.png',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/images/phoneScreenshots/screen-0.png',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/images/phoneScreenshots/screen-1.png',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/images/phoneScreenshots/screen-2.png',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/images/phoneScreenshots/screen with bad @+% chars.png',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/short_description.txt',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/title.txt',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/play-listing/es-419/whats_new.txt',
        ];
        $keys[] = [
            'Key' => 'codebuild-output/jobs/build_scriptureappbuilder_22/11/version_code.txt',
        ];

        $retVal = [
            'KeyCount' => 16,
            'Contents' => $keys,
        ];
        return $retVal;
    }
    public static function clearGlobals() {
        self::$puts = [];
        self::$deletes = [];
        self::$lists = [];
        self::$copies = [];
        self::$deletedBucket = null;
        self::$deletedKey = null;
    }
    public function getObjectUrl($s3bucket, $s3key)
    {
        $prefix = "https://s3-us-west-2.amazonaws.com/";
        $retString = $prefix . $s3bucket . "/" . $s3key;
        return $retString;
    }
    public function deleteMatchingObjects($s3bucket, $s3key)
    {
        $delete['bucket'] = $s3bucket;
        $delete['key'] = $s3key;
        self::$deletes[] = $delete;
    }
    public function getPaginator($command, $parms)
    {
        $results = [];
        $keyList = [];
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/TestPublishing-1.0.apk";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/package_name.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/publish.tar.gz";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/version_code.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/TestPublishing-1.0.apk";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/package_name.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/publish.tar.gz";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/version_code.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/TestPublishing-1.0.apk";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/package_name.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/publish.tar.gz";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/version_code.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/about.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/package_name.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/Test-1.0.apk";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/version_code.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/11/play-listing/index.html";
        $keyList[] = $object;

        $contents['Contents'] = $keyList;
        $results[] = $contents;
        return $results;
    }
}
