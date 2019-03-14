<?php

namespace common\models;

use Yii;
use yii\helpers\ArrayHelper;


use yii\web\Link;
use yii\web\Linkable;
use yii\helpers\Url;

use common\helpers\Utils;

class Build extends BuildBase implements Linkable
{
    
    const STATUS_INITIALIZED = 'initialized';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_POSTPROCESSING = 'postprocessing';
    const STATUS_COMPLETED = 'completed';

    const RESULT_SUCCESS = 'SUCCESS';
    const RESULT_FAILURE = 'FAILURE';
    const RESULT_ABORTED = 'ABORTED';

    const CHANNEL_UNPUBLISHED = 'unpublished';
    const CHANNEL_ALPHA = 'alpha';
    const CHANNEL_BETA = 'beta';
    const CHANNEL_PRODUCTION = 'production';

    const ARTIFACT_UNKNOWN = "unknown";
    const ARTIFACT_APK = "apk";
    const ARTIFACT_VERSION_CODE = "version_code";
    const ARTIFACT_VERSION = "version";
    const ARTIFACT_ABOUT = "about";
    const ARTIFACT_PLAY_LISTING = "play-listing";
    const ARTIFACT_PLAY_LISTING_MANIFEST = "play-listing-manifest";
    const ARTIFACT_PACKAGE_NAME = "package_name";
    const ARTIFACT_CLOUD_WATCH = "cloudWatch";
    const ARTIFACT_CONSOLE_TEXT = "consoleText";
    const ARTIFACT_WHATS_NEW = "whats_new";

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
            self::STATUS_POSTPROCESSING,
        ],
        self::STATUS_EXPIRED => [
            self::STATUS_ACTIVE,
        ],
        self::STATUS_POSTPROCESSING=> [
            self::STATUS_COMPLETED
        ],
    ];
    public $validChannelTransitions = [		
        self::CHANNEL_UNPUBLISHED => [		
            self::CHANNEL_ALPHA,		
            self::CHANNEL_BETA,		
            self::CHANNEL_PRODUCTION,		
        ],
        self::CHANNEL_ALPHA => [		
            self::CHANNEL_ALPHA,
            self::CHANNEL_BETA,		
            self::CHANNEL_PRODUCTION,		
        ],
        self::CHANNEL_BETA => [
           self::CHANNEL_BETA,
           self::CHANNEL_PRODUCTION,		
        ],
        self::CHANNEL_PRODUCTION => [
            self::CHANNEL_PRODUCTION,
        ],
     ];
    public function createRelease($channel, $targets, $environment) {
        $release = new Release();
        $release->build_id = $this->id;
        $release->channel = $channel;
        $release->targets = $targets;
        $release->environment = $environment;
        $release->save();

        return $release;
    }

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
                'job_id', 'exist', 'targetClass' => 'common\models\Job', 'targetAttribute' => 'id',
                'message' => \Yii::t('app', 'Invalid Job ID'),
            ],
            [
                'artifact_url_base', 'url',
                'pattern' => '/^https:\/\//',
                'message' => \Yii::t('app', 'Artifact Url must be an https S3 Url.')
            ],            
            [
                'updated', 'default', 'value' => Utils::getDatetime(), 'isEmpty' => function(){
                    // always return true so it get set on every save
                    return true;
                },
            ],
            [
                'channel', 'in', 'range' => [
                    self::CHANNEL_UNPUBLISHED,
                    self::CHANNEL_ALPHA,
                    self::CHANNEL_BETA,
                    self::CHANNEL_PRODUCTION,
                ],
            ],
            [
                'channel', 'default', 'value' => self::CHANNEL_UNPUBLISHED,
            ]

        ]);
    }
    public function fields()
    {
        return [
            'id',
            'job_id',
            'status',
            'result',
            'error',
            'artifacts' => function() {
                return [
                    self::ARTIFACT_APK => $this->apk(),
                    self::ARTIFACT_ABOUT => $this->about(),
                    self::ARTIFACT_PLAY_LISTING => $this->playListing(),
                    self::ARTIFACT_PLAY_LISTING_MANIFEST => $this->playListingManifest(),
                    self::ARTIFACT_VERSION_CODE => $this->versionCode(),
                    self::ARTIFACT_VERSION => $this->version(),
                    self::ARTIFACT_PACKAGE_NAME => $this->packageName(),
                    self::ARTIFACT_WHATS_NEW => $this->whatsNew(),
                    self::ARTIFACT_CLOUD_WATCH => $this->cloudWatch(),
                    self::ARTIFACT_CONSOLE_TEXT => $this->consoleText()];
            },
            'targets',
            'environment',
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
            $links[Link::REL_SELF] = Url::toRoute(['/job/'.$this->job_id.'/build/'.$this->id], true);
            $links['job'] = Url::toRoute(['/job/'.$this->job_id], true);
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
    public function isValidChannelTransition($new, $current = null)		
     {
        try {
            $current = $current ?: $this->channel;		
            if (in_array($new, $this->validChannelTransitions[$current])){		
                return true;		
            }		
        }
        catch (\Exception $e) {
        }

         return false;		
     }
    public function jobName()
    {
        return $this->job->nameForBuild();
    }

    /**
     * Returns the build specified by $build_id.  The inclusion of the $job_id
     * is done for validation purposes since this is also passed into the actions
     * that use this method
     *
     * @param type $job_id
     * @param type $build_id
     * @return type Build
     */
    public static function findOneById($job_id, $build_id)
    {
        $build = Build::findOne(['id' => $build_id, 'job_id' => $job_id]);
        if ($build) {
            if ($build->status == Build::STATUS_EXPIRED) {
                $build = null;
            }
       }
       return $build;
    }

    public static function findOneByBuildId($build_id)
    {
        $build = Build::findOne(['id' => $build_id]);
        if ($build) {
            if ($build->status == Build::STATUS_EXPIRED) {
                $build = null;
            }
       }
       return $build;
    }
    /**
     * Returns array of all non expired builds associated with the specified job
     *
     * @param type $job_id
     * @return type array of Build
     */
    public static function findAllActiveByJobId($job_id)
    {
       $builds = Build::find()->where('job_id = :job_id and status != :status',
               ['job_id'=>$job_id, 'status'=>Build::STATUS_EXPIRED])->all();
       return $builds;
    }
    /**
     * Returns array of all in progress builds associated with the specified job
     *
     * @param type $job_id
     * @return type array of Build
     */
    public static function findAllRunningByJobId($job_id)
    {
        $builds = Build::find()->where('job_id = :job_id and (status = :active or status = :postprocessing)',
                ['job_id'=>$job_id, 'active'=>Build::STATUS_ACTIVE, 'postprocessing'=>Build::STATUS_POSTPROCESSING])->all();
        return $builds;
    }

    /**
     * Returns array of all builds associated with the specified job
     *
     * @param type $job_id
     * @return type array of Build
     */
    public static function findAllByJobId($job_id)
    {
       $builds = Build::find()->where('job_id = :job_id',
               ['job_id'=>$job_id])->all();
       return $builds;
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
    /**
     * Check for dependent builds and delete them prior to deleting
     * record
     */
    public function beforeDelete() {
        foreach (Release::findAllByBuildId($this->id) as $release)
        {
            if (!$release->delete()){
                return false;
            }
        }
        return parent::beforeDelete();
    }

    public function artifactType($key) {
        $type = "unknown";
        $path_parts = pathinfo($key);
        $file = $path_parts['basename'];
        if ( $file == "cloudWatch") {
            $type = self::ARTIFACT_CLOUD_WATCH;
        } else if ($path_parts['extension'] === "log") {
            $type = self::ARTIFACT_CONSOLE_TEXT;
        } else if ($path_parts['extension'] === "apk") {
            $type = self::ARTIFACT_APK;
        } else if ($file === "version_code.txt") {
            $type = self::ARTIFACT_VERSION_CODE;
        } else if ($file === "version.json") {
            $type = self::ARTIFACT_VERSION;
        } else if ($file === "package_name.txt") {
            $type = self::ARTIFACT_PACKAGE_NAME;
        } else if ($file === "about.txt") {
            $type = self::ARTIFACT_ABOUT;
        } else if ($file === "whats_new.txt") {
            $type = self::ARTIFACT_WHATS_NEW;
        } else if (preg_match("/play-listing\/index\.html$/", $key)) {
            $type = self::ARTIFACT_PLAY_LISTING;
            $file = "play-listing/index.html";
        } else if (preg_match("/play-listing\/manifest.json$/", $key)) {
            $type = self::ARTIFACT_PLAY_LISTING_MANIFEST;
            $file = "play-listing/manifest.json";
        }

        return array($type, $file);
    }

    private function appendArtifact($file) {
        if (empty($this->artifact_files)) {
            $this->artifact_files = $file;
        } else {
            $this->artifact_files .= "," . $file;
        }
    }

    public function beginArtifacts($baseUrl) {
        $this->artifact_url_base = $baseUrl;
        $this->artifact_files = null;
    }

    public function handleArtifact($fileKey, $contents) {
        list($type, $file) = $this->artifactType($fileKey);
        switch ($type) {
            case self::ARTIFACT_VERSION_CODE:
                $this->version_code = $contents;
                break;

            case self::ARTIFACT_VERSION:
// FUTURE: pull version_code from version.json (as versionCode property)
//                $version = json_decode($contents);
//                $this->version_code = $version['versionCode']; // NOT TESTED!
//                break;

            case self::ARTIFACT_ABOUT:
            case self::ARTIFACT_APK:
            case self::ARTIFACT_PLAY_LISTING:
            case self::ARTIFACT_PLAY_LISTING_MANIFEST:
            case self::ARTIFACT_PACKAGE_NAME:
            case self::ARTIFACT_WHATS_NEW:
            case self::ARTIFACT_CLOUD_WATCH:
            case self::ARTIFACT_CONSOLE_TEXT:
                break;

            default:
                // Don't include in files
                return;
        }
        $this->appendArtifact($file);
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
    public function apkFilename() {
        return $this->getArtifactFilename("/\.apk$/");
    }
    public function apk() {
        return $this->getArtifactUrl("/\.apk$/");
    }
    public function about() {
        return $this->getArtifactUrl("/about\.txt$/");
    }
    public function playListing() {
        return $this->getArtifactUrl("/play-listing\/index\.html$/");
    }
    public function playListingManifest() {
        return $this->getArtifactUrl("/play-listing\/manifest\.json$/");
    }
    public function versionCode() {
        return $this->getArtifactUrl("/version_code\.txt$/");
    }
    public function version() {
        return $this->getArtifactUrl("/version\.json$/");
    }
    public function packageName() {
        return $this->getArtifactUrl("/package_name\.txt$/");
    }
    public function whatsNew() {
        return $this->getArtifactUrl("/whats_new\.txt$/");
    }
    public function cloudWatch() {
        return $this->console_text_url;
    }
    public function consoleText() {
        return $this->getArtifactUrl("/\.log$/");
    }

    /**
     *
     * get build details for logging.
     * @param Build $build
     * @return array
     */
    public static function getlogBuildDetails($build)
    {
        $jobName = $build->job->name();
        $log = [
            'jobName' => $jobName
        ];
        $log['buildId'] = $build->id;
        $log['buildStatus'] = $build->status;
        $log['buildNumber'] = $build->build_guid;
        $log['buildResult'] = $build->result;
        if (!is_null($build->artifact_url_base)) {
            $log['buildArtifactUrlBase'] = $build->artifact_url_base;
        }
        if (!is_null($build->artifact_files)) {
            $log['buildArtifactFiles'] = $build->artifact_files;
        }

        echo "Job=$jobName, Id=$build->id, Status=$build->status, Number=$build->build_guid, "
                    . "Result=$build->result, ArtifactUrlBase=$build->artifact_url_base, ArtifactFiles=$build->artifact_files". PHP_EOL;
        return $log;
    }
}
