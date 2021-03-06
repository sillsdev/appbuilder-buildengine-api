<?php

namespace console\components;

use console\components\OperationInterface;
use common\models\Job;
use common\models\Build;
use common\models\Release;
use common\components\S3;
use common\components\Appbuilder_logger;

use common\helpers\Utils;


class CopyToS3Operation implements OperationInterface
{
    private $build_id;
    private $maxRetries = 50;
    private $maxDelay = 30;
    private $alertAfter = 5;
    private $fileUtil;
    private $s3;
    private $parms;

    public function __construct($id, $parms)
    {
        $this->build_id = $id;
        $this->parms = $parms;
        $this->fileUtil = \Yii::$container->get('fileUtils');
    }
    public function performOperation()
    {
        $prefix = Utils::getPrefix();
        echo "[$prefix] CopyToS3Operation ID: " .$this->build_id . PHP_EOL;
        if ($this->parms == "release") {
            $release = Release::findOneByBuildId($this->build_id);
            if ($release) {
                $s3 = new S3();
                $s3->copyS3Folder($release);
                $release->status = Release::STATUS_COMPLETED;
                $release->save();
                $s3->removeCodeBuildFolder($release);
             }
        }
        else
        {
            $build = Build::findOneByBuildId($this->build_id);
            if ($build) {
                $job = $build->job;
                if ($job){
                    $this->saveBuild($build);
                    $build->status = Build::STATUS_COMPLETED;
                    $build->result = Build::RESULT_SUCCESS;
                    if (!$build->save()){
                        throw new \Exception("Unable to update Build entry, model errors: ".print_r($build->getFirstErrors(),true), 1450216434);
                    }
                    $this->s3->removeCodeBuildFolder($build);
                }
            }
        }
    }
    public function getMaximumRetries()
    {
        return $this->maxRetries;
    }
    public function getMaximumDelay()
    {
        return $this->maxDelay;
    }
    public function getAlertAfterAttemptCount()
    {
        return $this->alertAfter;
    }
    private function getExtraContent($build, $defaultLanguage) {
        echo 'getExtraContent defaultLanguage: ' . $defaultLanguage . PHP_EOL;
        $manifestFileContent = (string)$this->s3->readS3File($build, 'manifest.txt');
        if (!empty($manifestFileContent)) {
            $manifestFiles = explode("\n", $manifestFileContent);
            if (count($manifestFiles) > 0) {
                // Copy index.html to destination folder
                $file = \Yii::getAlias("@common") . "/preview/playlisting/index.html";

                $indexContents = $this->fileUtil->file_get_contents($file);
                $this->s3->writeFileToS3($indexContents, "play-listing/index.html", $build);
            }
            if (empty($defaultLanguage)) {
                // If defaultLanguage was not found, use first entry with icon
                foreach ($manifestFiles as $playListingFile) {
                    if (preg_match("/([^\/]*)\/images\/icon.png$/", $playListingFile, $matches)) {
                        $defaultLanguage = $matches[1];
                        break;
                    }
                }
            }

            // Note: I tried using array_map/array_filter, but it changed the json
            // serialization from an array to a hash where the indexes were the old
            // positions in the array.
            $playEncodedRelativePaths = array();
            $publishIndex = "<html><body><ul>" . PHP_EOL;
            foreach ($manifestFiles as $path) {
                if ((!empty($path)) && ($path != 'default-language.txt')) {
                    $encodedPath = self::encodePath('play-listing/' . $path);
                    $publishIndex .= "<li><a href=\"$encodedPath\">play-listing/$path</a></p></li>" . PHP_EOL;
                    array_push($playEncodedRelativePaths, self::encodePath($path));
                }
            }
            $publishIndex .= "</ul></body></html>" . PHP_EOL;
            $this->s3->writeFileToS3($publishIndex, 'play-listing.html', $build);
            $manifest = [ "files" => $playEncodedRelativePaths ];
            if (!empty($defaultLanguage)) {
                $manifest["default-language"] = $defaultLanguage;
            }
            $json = json_encode($manifest, JSON_UNESCAPED_SLASHES);
            $jsonFileName = 'play-listing/manifest.json';
            $this->s3->writeFileToS3($json, $jsonFileName, $build);
        }
    }

    private static function encodePath($path) {
        $encode = function($value) {
            return urlencode($value);
        };

        return implode("/", array_map($encode,explode("/", $path)));
    }


    /**
     * getDefaultLanguage reads the default language from default-language.txt
     * 
     * @param Build $build - Current build object
     * @return string Contents of default-language.txt or empty if file doesn't exist
     */
    private function  getDefaultLanguage($build) {
        $defaultLanguage = $this->s3->readS3File($build, 'play-listing/default-language.txt');
        return $defaultLanguage;
    }

    /**
     * Save the build to S3.
     * @param Build $build
     * @return string
     */
    private function saveBuild($build) {
        $logger = new Appbuilder_logger("CopyToS3Operation");
        $this->s3 = new S3();

        $this->s3->copyS3Folder($build);
        $defaultLanguage = $this->getDefaultLanguage($build);
        $this->getExtraContent($build, $defaultLanguage);

        # Log
        $log = Build::getlogBuildDetails($build);

        $logger->appbuilderWarningLog($log);

    }
}
