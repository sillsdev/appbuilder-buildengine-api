<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;


use yii\web\Link;
use yii\web\Linkable;
use yii\helpers\Url;

use common\helpers\Utils;
use common\interfaces\ArtifactsProvider;
use common\components\S3;

class Release extends ReleaseBase implements Linkable, ArtifactsProvider
{
    
    const STATUS_INITIALIZED = 'initialized';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_COMPLETED = 'completed';
    const STATUS_POSTPROCESSING = 'postprocessing';

    const ARTIFACT_CLOUD_WATCH = "cloudWatch";
    const ARTIFACT_CONSOLE_TEXT = "consoleText";

    /**
     * Array of valid status transitions. The key is the starting
     * status and the values are valid options to be changed to.
     * @var array
     */
    public $validStatusTransitions = [
        self::STATUS_INITIALIZED => [
            self::STATUS_ACCEPTED,
        ],
        self::STATUS_ACCEPTED => [
            self::STATUS_ACTIVE,
        ],
        self::STATUS_ACTIVE => [
            self::STATUS_COMPLETED,
            self::STATUS_EXPIRED,
        ],
        self::STATUS_EXPIRED => [
            self::STATUS_ACTIVE,
        ],
    ];

    const CHANNEL_ALPHA = 'alpha';
    const CHANNEL_BETA = 'beta';
    const CHANNEL_PRODUCTION = 'production';

    public function scenarios()
    {
        return ArrayHelper::merge(parent::scenarios(),[

        ]);
    }

    public function rules()
    {
        return ArrayHelper::merge(parent::rules(),[
            [
                ['created','updated'],'default', 'value' => Utils::getDatetime(),
            ],
            [
                'updated', 'default', 'value' => Utils::getDatetime(), 'isEmpty' => function(){
                    // always return true so it get set on every save
                    return true;
                },
            ],
            [
                'status', 'in', 'range' => [ 
                    self::STATUS_ACTIVE,
                    self::STATUS_ACCEPTED,
                    self::STATUS_COMPLETED,
                    self::STATUS_EXPIRED,
                    self::STATUS_INITIALIZED,
                    self::STATUS_POSTPROCESSING,
                ],
            ],
            [
                'status', 'default', 'value' => self::STATUS_INITIALIZED,
            ],
            [
                'build_id', 'exist', 'targetClass' => 'common\models\Build', 'targetAttribute' => 'id',
                'message' => \Yii::t('app', 'Invalid Build ID'),
            ],
            [
                'updated', 'default', 'value' => Utils::getDatetime(), 'isEmpty' => function(){
                    // always return true so it get set on every save
                    return true;
                },
            ],
            [
                'channel', 'in', 'range' => [
                    self::CHANNEL_ALPHA,
                    self::CHANNEL_BETA,
                    self::CHANNEL_PRODUCTION,
                ],
            ]
        ]);
    }
    public function fields()
    {
        return [
            'id',
            'build_id',
            'status',
            'result',
            'error',
            'title',
            'defaultLanguage',
            'channel',
            'artifacts' => function() {
                return [
                    self::ARTIFACT_CLOUD_WATCH => $this->cloudWatch(),
                    self::ARTIFACT_CONSOLE_TEXT => $this->consoleText()];
            },
            'consoleText' => function(){
                return $this->consoleText();
            },
            'created' => function(){
                return Utils::getIso8601($this->created);
            },
            'updated' => function(){
                return Utils::getIso8601($this->updated);
            },
        ];
    }

    public function getLinks()
    {
        $links = [];
        if($this->id){
            //$links[Link::REL_SELF] = Url::toRoute(['/release/'.$this->id], true);
        }
        return $links;
    }
    
    /**
     * Check if the new status is a valid transition from current
     * @param string $current The current status of Build
     * @param string $new The desired status for Build
     * @return bool
     */
    public function isValidStatusTransition($new, $current = null)
    {
        $current = $current ?: $this->status;
        if(in_array($new, $this->validStatusTransitions[$current])){
            return true;
        }

        return false;
    }

    public function jobName()
    {
        return $this->build->job->nameForPublish();
    }
    public function jobId()
    {
        return $this->build->job_id;
    }
    /**
     * Returns array of all  associated with the specified job
     *
     * @param type $build_id
     * @return type array of Release
     */
    public static function findAllByBuildId($build_id)
    {
       $releases = Release::find()->where('build_id = :build_id',
               ['build_id'=>$build_id])->all();
       return $releases;
    }
    /**
     * Clears the artifacts for a build
     */
    public function clearArtifacts()
    {
        $this->artifact_url_base = null;
        $this->artifact_files = null;
        $this->save();
    }
    private function getArtifactUrl($pattern) {
        $filename = $this->getArtifactFilename($pattern);
        if (!empty($filename))
        {
            return $this->artifact_url_base . $filename;
        }
        return null;
    }
    private function getArtifactFilename($pattern) {
        if (!empty($this->artifact_files)) {
            $files = explode(",", $this->artifact_files);
            foreach ($files as $file) {
                if (preg_match($pattern, $file)) {
                    return $file;
                }
            }
        }
        return null;
    }
    private function appendArtifact($file) {
        if (empty($this->artifact_files)) {
            $this->artifact_files = $file;
        } else {
            $this->artifact_files .= "," . $file;
        }
    }
    public function cloudWatch() {
        return $this->console_text_url;
    }
    public function consoleText() {
        return $this->getArtifactUrl("/\.log$/");
    }
    /**
     * Gets the base prefix for the s3 within the bucket for publish
     *
     * @param string $productStage - stg or prd
     * @return string prefix
     */
    public function getBasePrefixUrl($productStage) {
        $artifactPath = S3::getArtifactPath($this->build->job, $productStage, true);
        $buildNumber = (string)$this->id;
        $repoUrl =  $artifactPath . "/" . $buildNumber;
        return $repoUrl;
    }
    public function beginArtifacts($baseUrl) {
        $this->artifact_url_base = $baseUrl;
        $this->artifact_files = null;
    }
    public function artifactType($key) {
        $type = "unknown";
        $path_parts = pathinfo($key);
        $file = $path_parts['basename'];
        if ( $file == "cloudWatch") {
            $type = self::ARTIFACT_CLOUD_WATCH;
        } else if ($path_parts['extension'] === "log") {
            $type = self::ARTIFACT_CONSOLE_TEXT;
        }

        return array($type, $file);
    }

    public function handleArtifact($fileKey, $contents) {
        list($type, $file) = $this->artifactType($fileKey);
        switch ($type) {
            case self::ARTIFACT_CLOUD_WATCH:
            case self::ARTIFACT_CONSOLE_TEXT:
                break;

            default:
                // Don't include in files
                return;
        }
        $this->appendArtifact($file);
    }
    public static function findOneByBuildId($build_id)
    {
        $build = Release::findOne(['id' => $build_id]);
        if ($build) {
            if ($build->status == Release::STATUS_EXPIRED) {
                $build = null;
            }
       }
       return $build;
    }

}
