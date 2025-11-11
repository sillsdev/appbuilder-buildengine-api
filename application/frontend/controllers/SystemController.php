<?php
namespace frontend\controllers;

use Yii;
use common\models\EmailQueue;
use yii\helpers\ArrayHelper;
use yii\rest\Controller;
use yii\filters\auth\CompositeAuth;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\AccessControl;
use yii\web\ForbiddenHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;
use Aws\Exception\AwsException;

/**
 * System controller
 */
class SystemController extends Controller
{
    // Cache key prefix for version cache
    const VERSION_CACHE_PREFIX = 'ecr_image_versions_';
    const VERSION_CACHE_DURATION = 3600; // 1 hour

    public function actionCheck()
    {
        // Verify DB connectivity first
        try {
            EmailQueue::find()->all();
        } catch (\Exception $e) {
            throw new ServerErrorHttpException("Unable to connect to db, error code " . $e->getCode(), $e->getCode());
        }

        // Prepare default response structure
        $versions = [];
        $imageHash = null;
        $createdFromCache = null;

        // Prefer params from frontend config/main.php (no getenv fallback)
        $repoConfig = Yii::$app->params['codeBuildImageRepo'] ?? null;
        $tagFilter = Yii::$app->params['codeBuildImageTag'] ?? null;
        $region = Yii::$app->params['buildEngineArtifactsBucketRegion'] ?? 'us-east-1';

        // Status: log repo config presence
        Yii::info('SystemController::actionCheck - repoConfig=' . ($repoConfig ?: '(none)'));

        // Log a warning if a repo is configured but the AWS ECR client class isn't available
        if ($repoConfig && !class_exists('\\Aws\\Ecr\\EcrClient')) {
            Yii::warning('ECR client class "\\Aws\\Ecr\\EcrClient" not found. Install the AWS SDK for PHP (composer require aws/aws-sdk-php) so versions can be discovered from ECR.');
        }

        // Try to query ECR if AWS SDK is available and repo is configured
        if ($repoConfig && class_exists('\\Aws\\Ecr\\EcrClient')) {
            Yii::info('SystemController::actionCheck - Aws\\Ecr\\EcrClient available, attempting ECR query (region=' . $region . ')');
            try {
                $client = new \Aws\Ecr\EcrClient([
                    'version' => '2015-09-21',
                    'region' => $region,
                ]);

                Yii::info('SystemController::actionCheck - EcrClient constructed');

                // repositoryName for ECR is typically the last path segment if repo includes a path
                $repoName = $repoConfig;
                if (strpos($repoName, '/') !== false) {
                    $parts = explode('/', $repoName);
                    $repoName = end($parts);
                }

                Yii::info('SystemController::actionCheck - resolved repositoryName=' . $repoName);

                // Verify repository exists before calling describeImages
                $repoMeta = $this->verifyEcrRepositoryExists($client, $repoName);
                if (empty($repoMeta)) {
                    Yii::warning('SystemController::actionCheck - repository verification failed or repository not found: ' . $repoName . ' - skipping describeImages.');
                    $imageDetails = [];
                } else {
                    // Describe tagged images
                    $params = ['repositoryName' => $repoName, 'filter' => ['tagStatus' => 'TAGGED']];
                    Yii::info('SystemController::actionCheck - calling describeImages for ' . $repoName);
                    $resp = $client->describeImages($params);
                    $imageDetails = $resp->get('imageDetails') ?: [];

                    Yii::info('SystemController::actionCheck - describeImages returned ' . count($imageDetails) . ' imageDetails');
                }

                // Look for version information in image manifests
                $appNames = [
                    'scriptureappbuilder',
                    'readingappbuilder',
                    'dictionaryappbuilder',
                    'keyboardappbuilder'
                ];

                foreach ($imageDetails as $img) {
                    if (empty($img['imageTags'])) {
                        continue;
                    }

                    Yii::info('SystemController::actionCheck - image: ' . json_encode($img));


                    // Extract image digest (hash) from the image details - this is available without fetching manifest
                    $imageDigest = $img['imageDigest'] ?? null;
                    Yii::info('SystemController::actionCheck - found imageDigest: ' . ($imageDigest ?: 'none') . ' for image with tags: ' . implode(', ', $img['imageTags']));

                    foreach ($img['imageTags'] as $imgTag) {

                        // Only process tags that match the tagFilter (if set)
                        if ($tagFilter && strpos($imgTag, $tagFilter) === false) {
                            Yii::info('SystemController::actionCheck - skipping tag ' . $imgTag . ' (does not match tagFilter)');
                            continue;
                        }


                        // Check cache first using the image digest if available
                        if ($imageDigest) {
                            $cacheKey = self::VERSION_CACHE_PREFIX . $imageDigest;
                            $cachedData = Yii::$app->cache->get($cacheKey);

                            if ($cachedData !== false) {
                                Yii::info('SystemController::actionCheck - returning cached versions for digest ' . $imageDigest . ' (tag ' . $imgTag . ') - skipping manifest fetch');
                                $appVersions = $cachedData['versions'] ?? [];
                                $imageHash = $imageDigest;
                                $createdFromCache = $cachedData['created'] ?? null;

                                if (!empty($appVersions)) {
                                    foreach ($appVersions as $app => $version) {
                                        $versions[$app] = $version;
                                        Yii::info('SystemController::actionCheck - cached version for tag ' . $imgTag . ' => ' . $app . ' ' . $version);
                                    }
                                }
                                break 2; // break out of image and tag loops since we found a match.
                            }

                            // Extract versions for all apps from the image manifest/config
                            $appVersions = $this->fetchAllAppVersionsFromManifest($client, $repoName, $imgTag, $appNames);

                            if (!empty($appVersions)) {
                                // Cache the results using the image digest as the key
                                $created = (new \DateTime('now', new \DateTimeZone('UTC')))->format('c');
                                if ($imageDigest) {
                                    $cacheData = [
                                        'versions' => $appVersions,
                                        'created' => $created,
                                        'cached_at' => time()
                                    ];
                                    Yii::$app->cache->set($cacheKey, $cacheData, self::VERSION_CACHE_DURATION);
                                    Yii::info('SystemController::actionCheck - cached versions for digest ' . $imageDigest . ' (tag ' . $imgTag . ') for ' . self::VERSION_CACHE_DURATION . ' seconds');
                                }

                                $imageHash = $imageDigest;
                                $createdFromCache = $created;

                                foreach ($appVersions as $app => $version) {
                                    $versions[$app] = $version;
                                    Yii::info('SystemController::actionCheck - manifest-derived version for tag ' . $imgTag . ' => ' . $app . ' ' . $version);
                                }
                            } else {
                                Yii::info('SystemController::actionCheck - no app versions found in manifest for tag ' . $imgTag);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // If ECR query fails (missing creds/permissions or network), leave versions empty
                // Do not throw: the health check should still succeed if DB is OK
                Yii::warning('SystemController::actionCheck - ECR query failed: ' . $e->getMessage());
                // Detect AccessDenied and add an extra hint to logs
                $msg = $e->getMessage();
                if (stripos($msg, 'AccessDenied') !== false || stripos($msg, 'AccessDeniedException') !== false) {
                    Yii::warning('SystemController::actionCheck - ECR Access Denied. Ensure IAM principal has ecr:DescribeImages for the repository and correct region/account.');
                }
            }
        } else {
            Yii::info('SystemController::actionCheck - skipping ECR query (no repoConfig or ECR client not available)');
        }

        // Build timestamps - use cached creation date if available, otherwise current time
        $now = (new \DateTime('now', new \DateTimeZone('UTC')))->format('c');
        $created = $createdFromCache ?: $now;
        $updated = $now;

        // Debug logging
        Yii::info('SystemController::actionCheck - Final $versions structure: ' . json_encode($versions));

        $response = [
            'versions' => (object) $versions, // cast to object to ensure JSON object even when empty
            'created' => $created,
            'updated' => $updated,
            'imageHash' => $imageHash,
            '_links' => [
                'self' => [
                    'href' => Yii::$app->request->absoluteUrl,
                ],
            ],
        ];

        return $response;
    }

    /**
     * Verify that an ECR repository exists and return its metadata.
     * Returns repository array on success, or null on not found / error.
     * Logs info/warnings for common AWS error codes.
     */
    private function verifyEcrRepositoryExists($client, $repoName)
    {
        try {
            Yii::info('SystemController::verifyEcrRepositoryExists - calling describeRepositories for ' . $repoName);
            $resp = $client->describeRepositories([
                'repositoryNames' => [$repoName],
            ]);
            $repos = $resp->get('repositories') ?: [];
            if (count($repos) > 0) {
                Yii::info('SystemController::verifyEcrRepositoryExists - repository found: ' . ($repos[0]['repositoryArn'] ?? $repoName));
                return $repos[0];
            }
            Yii::warning('SystemController::verifyEcrRepositoryExists - describeRepositories returned empty for ' . $repoName);
            return null;
        } catch (AwsException $ae) {
            $awsCode = $ae->getAwsErrorCode();
            Yii::warning('SystemController::verifyEcrRepositoryExists - AwsException: ' . $ae->getMessage() . ' awsCode=' . $awsCode);
            if ($awsCode === 'RepositoryNotFoundException') {
                Yii::warning('SystemController::verifyEcrRepositoryExists - repository not found: ' . $repoName);
                return null;
            }
            if (stripos($ae->getMessage(), 'AccessDenied') !== false || $awsCode === 'AccessDeniedException') {
                Yii::warning('SystemController::verifyEcrRepositoryExists - access denied for describeRepositories on ' . $repoName . '. Ensure IAM permissions include ecr:DescribeRepositories and ecr:DescribeImages.');
                return null;
            }
            // Other AWS error: log and return null
            Yii::warning('SystemController::verifyEcrRepositoryExists - unexpected ECR error: ' . $ae->getMessage());
            return null;
        } catch (\Exception $e) {
            Yii::warning('SystemController::verifyEcrRepositoryExists - unexpected error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Fetch the image manifest/config and extract version information for all apps.
     * Returns an associative array of app names to version strings.
     * Does NOT manage caching - that's the caller's responsibility.
     */
    private function fetchAllAppVersionsFromManifest($client, $repoName, $imageTag, $appNames)
    {
        try {
            Yii::info('SystemController::fetchAllAppVersionsFromManifest - batchGetImage for ' . $imageTag);
            $resp = $client->batchGetImage([
                'repositoryName' => $repoName,
                'imageIds' => [['imageTag' => $imageTag]],
                'acceptedMediaTypes' => [
                    'application/vnd.docker.distribution.manifest.v2+json',
                    'application/vnd.oci.image.manifest.v1+json',
                ],
            ]);

            $images = $resp->get('images') ?: [];
            if (empty($images)) {
                Yii::info('SystemController::fetchAllAppVersionsFromManifest - no images returned for tag ' . $imageTag);
                return [];
            }

            Yii::info('SystemController::fetchAllAppVersionsFromManifest - received ' . count($images) . ' image(s) for tag ' . $imageTag);

            $imageManifest = $images[0]['imageManifest'] ?? null;
            if (!$imageManifest) {
                Yii::info('SystemController::fetchAllAppVersionsFromManifest - imageManifest missing for ' . $imageTag);
                return [];
            }

            Yii::info('SystemController::fetchAllAppVersionsFromManifest - parsing manifest JSON for ' . $imageTag);
            $manifest = json_decode($imageManifest, true);
            if (!is_array($manifest)) {
                Yii::warning('SystemController::fetchAllAppVersionsFromManifest - unable to decode manifest JSON for ' . $imageTag);
                return [];
            }

            Yii::info('SystemController::fetchAllAppVersionsFromManifest - manifest schema version: ' . ($manifest['schemaVersion'] ?? 'unknown') . ' for ' . $imageTag);

            // Locate config digest in manifest (schema v2) to fetch the image config with labels
            $configDigest = $manifest['config']['digest'] ?? null;
            if (!$configDigest) {
                Yii::info('SystemController::fetchAllAppVersionsFromManifest - no config digest found in manifest for ' . $imageTag);
                return [];
            }

            Yii::info('SystemController::fetchAllAppVersionsFromManifest - found config digest: ' . $configDigest . ' for ' . $imageTag);

            // Get a download URL for the config blob and fetch it
            Yii::info('SystemController::fetchAllAppVersionsFromManifest - getDownloadUrlForLayer for ' . $configDigest);
            $dl = $client->getDownloadUrlForLayer([
                'repositoryName' => $repoName,
                'layerDigest' => $configDigest,
            ]);
            $url = $dl->get('downloadUrl') ?? null;
            if (!$url) {
                Yii::warning('SystemController::fetchAllAppVersionsFromManifest - no downloadUrl for config ' . $configDigest);
                return [];
            }

            Yii::info('SystemController::fetchAllAppVersionsFromManifest - fetching config from URL for ' . $imageTag);

            // Fetch the config JSON
            $configContent = @file_get_contents($url);
            if ($configContent === false) {
                Yii::warning('SystemController::fetchAllAppVersionsFromManifest - failed to fetch config from ' . $url);
                return [];
            }

            Yii::info('SystemController::fetchAllAppVersionsFromManifest - received config content (' . strlen($configContent) . ' bytes) for ' . $imageTag);

            $config = json_decode($configContent, true);
            if (!is_array($config)) {
                Yii::warning('SystemController::fetchAllAppVersionsFromManifest - unable to decode config JSON for ' . $imageTag);
                return [];
            }

            Yii::info('SystemController::fetchAllAppVersionsFromManifest - parsed config JSON successfully for ' . $imageTag);

            // Common places for labels
            $labels = [];
            if (!empty($config['config']['Labels']) && is_array($config['config']['Labels'])) {
                $labels = $config['config']['Labels'];
                Yii::info('SystemController::fetchAllAppVersionsFromManifest - found ' . count($labels) . ' labels in config.Labels for ' . $imageTag);
            } elseif (!empty($config['container_config']['Labels']) && is_array($config['container_config']['Labels'])) {
                $labels = $config['container_config']['Labels'];
                Yii::info('SystemController::fetchAllAppVersionsFromManifest - found ' . count($labels) . ' labels in container_config.Labels for ' . $imageTag);
            } else {
                Yii::info('SystemController::fetchAllAppVersionsFromManifest - no labels found in config for ' . $imageTag);
            }

            // Log all label keys for debugging
            if (!empty($labels)) {
                Yii::info('SystemController::fetchAllAppVersionsFromManifest - available label keys: [' . implode(', ', array_keys($labels)) . '] for ' . $imageTag);
            }

            // Extract version for each app from labels
            $appVersions = [];
            foreach ($appNames as $app) {
                $labelKey = 'org.opencontainers.image.version_' . $app;
                if (!empty($labels[$labelKey])) {
                    $version = (string)$labels[$labelKey];
                    $appVersions[$app] = $version;
                    Yii::info('SystemController::fetchAllAppVersionsFromManifest - found version for ' . $app . ': ' . $version . ' (label: ' . $labelKey . ') for ' . $imageTag);
                } else {
                    Yii::info('SystemController::fetchAllAppVersionsFromManifest - no version found for ' . $app . ' (looked for label: ' . $labelKey . ') for ' . $imageTag);
                }
            }

            Yii::info('SystemController::fetchAllAppVersionsFromManifest - extracted ' . count($appVersions) . ' app versions for ' . $imageTag . ': [' . implode(', ', array_map(function($k, $v) { return "$k=$v"; }, array_keys($appVersions), $appVersions)) . ']');

            return $appVersions;
        } catch (AwsException $ae) {
            Yii::warning('SystemController::fetchAllAppVersionsFromManifest - AwsException: ' . $ae->getMessage() . ' awsCode=' . $ae->getAwsErrorCode());
            if ($ae->getAwsErrorCode() === 'AccessDeniedException') {
                Yii::warning('SystemController::fetchAllAppVersionsFromManifest - AccessDenied. Ensure ecr:BatchGetImage and ecr:GetDownloadUrlForLayer permissions are present.');
            }
            return [];
        } catch (\Exception $e) {
            Yii::warning('SystemController::fetchAllAppVersionsFromManifest - unexpected error: ' . $e->getMessage());
            return [];
        }
    }


    public function behaviors()
    {
        return ArrayHelper::merge(parent::behaviors(), [
            'authenticator' => [
                'class' => CompositeAuth::className(),
                'authMethods' => [
                    HttpBearerAuth::className(), // Use header ... Authorization: Bearer abc123
                ],
            ],
            'access' => [
                'class' => AccessControl::className(),
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'], // Any logged in user
                    ],
                ],
                'denyCallback' => function($rule, $action){
                    if(\Yii::$app->user->isGuest){
                        throw new UnauthorizedHttpException();
                    } else {
                        throw new ForbiddenHttpException();
                    }
                },
            ]
        ]);
    }

}
