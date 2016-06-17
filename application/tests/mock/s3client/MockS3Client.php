<?php
namespace tests\mock\s3client;

use Codeception\Util\Debug;

class MockS3Client
{
    public static $puts = [];
    public static $deletes = [];
    public static $deletedBucket;
    public static $deletedKey;
    public function putObject($parms) {
        self::$puts[] = $parms;
    }
    public static function clearGlobals() {
        self::$puts = [];
        self::$deletes = [];
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
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_1/1/TestPublishing-1.0.apk";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_1/1/package_name.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_1/1/publish.tar.gz";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_1/1/version_code.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_2/1/TestPublishing-1.0.apk";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_2/1/package_name.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_2/1/publish.tar.gz";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_2/1/version_code.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/1/TestPublishing-1.0.apk";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/1/package_name.txt";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/1/publish.tar.gz";
        $keyList[] = $object;
        $object['Key'] = "testing/jobs/build_scriptureappbuilder_22/1/version_code.txt";
        $keyList[] = $object;
        $contents['Contents'] = $keyList;
        $results[] = $contents;
        return $results;
    }
}
     