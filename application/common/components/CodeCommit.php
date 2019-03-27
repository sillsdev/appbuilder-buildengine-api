<?php
namespace common\components;

use common\components\AWSCommon;
use common\helpers\Utils;

class CodeCommit extends AWSCommon{
    public $codeCommitClient;

    public function __construct() {
        try {
            // Injected if Unit Test
            $this->codeCommitClient = \Yii::$container->get('codeCommitClient'); 
        } catch (\Exception $e) {
            // Get real S3 client
            $this->codeCommitClient = self::getCodeCommitClient();
        }
    }
    /**
     * Configure and get the CodeCommit Client
     * @return \Aws\CodeBuild\CodeCommitClient
     */
    public static function getCodeCommitClient()
    {
        $client = new \Aws\CodeCommit\CodeCommitClient([
            'region' => self::getArtifactsBucketRegion(),
            'version' => '2015-04-13'
            ]);
        return $client;
    }
    /**
     * Returns http url of code commit archive derived from git url needed for CodeBuild
     *
     * @param string $git_url
     * @return string http codecommit url
     */
    public function getSourceURL($git_url) {
        $prefix = Utils::getPrefix();
         echo "[$prefix] getSourceURL URL: " .$git_url . PHP_EOL;
        $repo = substr($git_url, strrpos($git_url, '/') + 1);
        $repoInfo = $this->codeCommitClient->getRepository([
            'repositoryName' => $repo
        ]);
//        var_dump($repoInfo['repositoryMetadata']);
//        echo "repoInfo: " . $repoInfo['repositoryMetadata'] . PHP_EOL;
        $metadata = $repoInfo['repositoryMetadata'];
        echo "cloneUrl: " . $metadata['cloneUrlHttp'] . PHP_EOL;
        $cloneUrl = $metadata['cloneUrlHttp'];
        return $cloneUrl;
    }
    /**
     * Return ssh url of code commit archive derived from git url needed for CodeBuild
     *
     * @param string $git_url
     * @return string http codecommit url
     */
    public function getSourceSshURL($git_url) {
        $prefix = Utils::getPrefix();
         echo "[$prefix] getSourceURL URL: " .$git_url . PHP_EOL;
        $repo = substr($git_url, strrpos($git_url, '/') + 1);
        $repoInfo = $this->codeCommitClient->getRepository([
            'repositoryName' => $repo
        ]);
        $metadata = $repoInfo['repositoryMetadata'];
        echo "cloneUrl: " . $metadata['cloneUrlSsh'] . PHP_EOL;
        $cloneUrl = $metadata['cloneUrlSsh'];
        return $cloneUrl;
    }

    /**
     *  Returns commit id of the specified branch for the specified repo
     * 
     * @param string $git_url
     * @param string $branch
     * @return string commit id
     */
    public function getCommitId($git_url, $branch) {
        $prefix = Utils::getPrefix();
         echo "[$prefix] getCommitId URL: " .$git_url . " Branch: " . $branch . PHP_EOL;
        $repo = substr($git_url, strrpos($git_url, '/') + 1);
        $result = $this->codeCommitClient->getBranch([
            'branchName' => $branch,
            'repositoryName' => $repo,
        ]);
        $branchInfo = $result['branch'];
        $commitId = $branchInfo['commitId'];
        echo "commitId: " . $commitId . PHP_EOL;
        return $commitId;
    }
}
