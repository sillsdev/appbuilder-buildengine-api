import {
  BatchGetImageCommand,
  DescribeImagesCommand,
  DescribeRepositoriesCommand,
  ECRClient,
  ECRServiceException,
  GetDownloadUrlForLayerCommand,
  type ImageDetail,
  RepositoryNotFoundException
} from '@aws-sdk/client-ecr';
import type { RequestHandler } from './$types';
import { AWSCommon } from '$lib/server/aws/common';

// GET system/check
export const GET: RequestHandler = async () => {
  // db connectivity handled by server hooks
  // Prepare default response structure
  const versions: Record<string, string> = {};
  let imageHash: string | null = null;

  const repoConfig = AWSCommon.getCodeBuildImageRepo();
  const tagFilter = AWSCommon.getCodeBuildImageTag();
  const region = AWSCommon.getArtifactsBucketRegion();

  // Status: log repo config presence
  console.log(`system/check:GET - repoConfig=${repoConfig ?? '(none)'}`);

  // Try to query ECR if AWS SDK is available and repo is configured
  if (repoConfig) {
    console.log(
      `system/check:GET - AwsEcrEcrClient available, attempting ECR query (region=${region})`
    );
    try {
      const client = new ECRClient({
        region
      });

      console.log('system/check:GET - EcrClient constructed');

      // repositoryName for ECR is typically the last path segment if repo includes a path
      let repoName = repoConfig;
      if (repoName.includes('/')) {
        const parts = repoName.split('/');
        repoName = parts.at(-1)!;
      }

      console.log(`system/check:GET - resolved repositoryName=${repoName}`);

      // Verify repository exists before calling describeImages
      const repoMeta = await verifyEcrRepositoryExists(client, repoName);
      let imageDetails: ImageDetail[] = [];
      if (!repoMeta) {
        console.warn(
          `system/check:GET - repository verification failed or repository not found: ${repoName} - skipping describeImages.`
        );
      } else {
        // Describe tagged images
        console.log('system/check:GET - calling describeImages for ' + repoName);
        const resp = await client.send(
          new DescribeImagesCommand({ repositoryName: repoName, filter: { tagStatus: 'TAGGED' } })
        );
        imageDetails = resp['imageDetails'] ?? [];

        console.log(
          `system/check:GET - describeImages returned ${imageDetails.length} imageDetails`
        );
      }

      // Look for version information in image manifests
      for (const img of imageDetails) {
        if (!img['imageTags']) {
          continue;
        }

        console.log(`system/check:GET - image: ${JSON.stringify(img)}`);

        // Extract image digest (hash) from the image details - this is available without fetching manifest
        const imageDigest = img['imageDigest'] || null;
        console.log(
          `system/check:GET - found imageDigest: ${imageDigest ?? 'none'} for image with tags: ${img['imageTags'].join(', ')}`
        );

        for (const imgTag of img['imageTags']) {
          // Only process tags that match the tagFilter (if set)
          if (tagFilter && !imgTag.includes(tagFilter)) {
            console.log(`system/check:GET - skipping tag ${imgTag} (does not match tagFilter)`);
            continue;
          }

          // Extract versions for all apps from the image manifest/config
          const appVersions = await fetchAllAppVersionsFromManifest(client, repoName, imgTag);

          if (Object.keys(appVersions).length) {
            imageHash = imageDigest;

            for (const [app, version] of Object.entries(appVersions)) {
              versions[app] = version;
              console.log(
                `system/check:GET - manifest-derived version for tag ${imgTag} : ${app} ${version}`
              );
            }
          } else {
            console.log(`system/check:GET - no app versions found in manifest for tag ${imgTag}`);
          }
        }
      }
    } catch (e) {
      // If ECR query fails (missing creds/permissions or network), leave versions empty
      // Do not throw: the health check should still succeed if DB is OK
      console.warn(`system/check:GET - ECR query failed: ${e}`);
      // Detect AccessDenied and add an extra hint to logs
      if (e instanceof Error && e.message.match(/AccessDenied/i)) {
        console.warn(
          'system/check:GET - ECR Access Denied. Ensure IAM principal has ecr:DescribeImages for the repository and correct region/account.'
        );
      }
    }
  } else {
    console.log(
      'system/check:GET - skipping ECR query (no repoConfig or ECR client not available)'
    );
  }

  // Build timestamps
  const created = new Date();

  // Debug logging
  console.log(`system/check:GET - Final versions structure: ${JSON.stringify(versions)}`);

  return new Response(
    JSON.stringify({
      versions,
      created,
      updated: created,
      imageHash,
      _links: {
        self: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/system/check`
        }
      }
    })
  );
};

const appNames = [
  'scriptureappbuilder',
  'readingappbuilder',
  'dictionaryappbuilder',
  'keyboardappbuilder'
] as const;

/**
 * Verify that an ECR repository exists and return its metadata.
 * Returns repository array on success, or null on not found / error.
 * Logs info/warnings for common AWS error codes.
 */
async function verifyEcrRepositoryExists(client: ECRClient, repoName: string) {
  try {
    console.log(
      `system/check:verifyEcrRepositoryExists - calling describeRepositories for ${repoName}`
    );
    const resp = await client.send(
      new DescribeRepositoriesCommand({
        repositoryNames: [repoName]
      })
    );
    const repos = resp['repositories'] ?? [];
    if (repos.length) {
      console.log(
        `system/check:verifyEcrRepositoryExists - repository found: ${repos[0]['repositoryArn'] ?? repoName}`
      );
      return repos[0];
    }
    console.warn(
      `system/check:verifyEcrRepositoryExists - describeRepositories returned empty for ${repoName}`
    );
  } catch (e) {
    if (e instanceof ECRServiceException) {
      console.warn(
        `system/check:verifyEcrRepositoryExists - AwsException: ${e.message} awsCode=${e.name}`
      );
      if (e instanceof RepositoryNotFoundException) {
        console.warn(`system/check:verifyEcrRepositoryExists - repository not found: ${repoName}`);
      } else if (e.message.match(/AccessDenied/i) || e.name === 'AccessDeniedException') {
        console.warn(
          `system/check:verifyEcrRepositoryExists - access denied for describeRepositories on ${repoName}. Ensure IAM permissions include ecr:DescribeRepositories and ecr:DescribeImages.`
        );
      } else {
        // Other AWS error: log and return null
        console.warn(`system/check:verifyEcrRepositoryExists - unexpected ECR error: ${e.message}`);
      }
    } else {
      console.warn(`system/check:verifyEcrRepositoryExists - unexpected error: ${e}`);
    }
  }

  return null;
}

/**
 * Fetch the image manifest/config and extract version information for all apps.
 * Returns an associative array of app names to version strings.
 * Does NOT manage caching - that's the caller's responsibility.
 */
async function fetchAllAppVersionsFromManifest(
  client: ECRClient,
  repoName: string,
  imageTag: string
) {
  try {
    console.log(`system/check:fetchAllAppVersionsFromManifest - batchGetImage for ${imageTag}`);
    const resp = await client.send(
      new BatchGetImageCommand({
        repositoryName: repoName,
        imageIds: [{ imageTag: imageTag }],
        acceptedMediaTypes: [
          'application/vnd.docker.distribution.manifest.v2+json',
          'application/vnd.oci.image.manifest.v1+json'
        ]
      })
    );

    const images = resp['images'];
    if (!images) {
      console.log(
        `system/check:fetchAllAppVersionsFromManifest - no images returned for tag ${imageTag}`
      );
      return {};
    }

    console.log(
      `system/check:fetchAllAppVersionsFromManifest - received ${images.length} image(s) for tag ${imageTag}`
    );

    const imageManifest = images[0]['imageManifest'];
    if (!imageManifest) {
      console.log(
        `system/check:fetchAllAppVersionsFromManifest - imageManifest missing for ${imageTag}`
      );
      return {};
    }

    console.log(
      `system/check:fetchAllAppVersionsFromManifest - parsing manifest JSON for ${imageTag}`
    );
    let manifest;
    try {
      manifest = JSON.parse(imageManifest);
    } catch {
      console.warn(
        `system/check:fetchAllAppVersionsFromManifest - unable to decode manifest JSON for ${imageTag}\nManifest:\n${imageManifest ?? ''}`
      );
      return {};
    }

    console.log(
      `system/check:fetchAllAppVersionsFromManifest - manifest schema version: ${manifest['schemaVersion'] ?? 'unknown'} for ${imageTag}`
    );

    // Locate config digest in manifest (schema v2) to fetch the image config with labels
    const configDigest = manifest['config']['digest'];
    if (!configDigest) {
      console.log(
        `system/check:fetchAllAppVersionsFromManifest - no config digest found in manifest for ${imageTag}`
      );
      return {};
    }

    console.log(
      `system/check:fetchAllAppVersionsFromManifest - found config digest: ${configDigest} for ${imageTag}`
    );

    // Get a download URL for the config blob and fetch it
    console.log(
      `system/check:fetchAllAppVersionsFromManifest - getDownloadUrlForLayer for ${configDigest}`
    );
    const dl = await client.send(
      new GetDownloadUrlForLayerCommand({
        repositoryName: repoName,
        layerDigest: configDigest
      })
    );
    const url = dl['downloadUrl'];
    if (!url) {
      console.warn(
        `system/check:fetchAllAppVersionsFromManifest - no downloadUrl for config ${configDigest}`
      );
      return {};
    }

    console.log(
      `system/check:fetchAllAppVersionsFromManifest - fetching config from URL for ${imageTag}`
    );

    // Fetch the config JSON
    const configContent = await fetch(url).then((r) => r.text());
    if (!configContent) {
      console.warn(
        `system/check:fetchAllAppVersionsFromManifest - failed to fetch config from ${url}`
      );
      return {};
    }

    console.log(
      `system/check:fetchAllAppVersionsFromManifest - received config content (${configContent.length} bytes) for ${imageTag}`
    );

    let config;
    try {
      config = JSON.parse(configContent);
    } catch {
      console.warn(
        `system/check:fetchAllAppVersionsFromManifest - unable to decode config JSON for ${imageTag}\nConfig:\n${configContent ?? ''}`
      );
      return {};
    }

    console.log(
      `system/check:fetchAllAppVersionsFromManifest - parsed config JSON successfully for ${imageTag}`
    );

    // Common places for labels
    let labels: Record<string, string> = {};
    if (config['config']['Labels']) {
      labels = config['config']['Labels'];
      console.log(
        `system/check:fetchAllAppVersionsFromManifest - found ${labels.length} labels in config.Labels for ${imageTag}`
      );
    } else if (config['container_config']['Labels']) {
      labels = config['container_config']['Labels'];
      console.log(
        `system/check:fetchAllAppVersionsFromManifest - found ${labels.length} labels in container_config.Labels for ${imageTag}`
      );
    } else {
      console.log(
        `system/check:fetchAllAppVersionsFromManifest - no labels found in config for ${imageTag}`
      );
    }

    // Log all label keys for debugging
    if (Object.keys(labels).length) {
      console.log(
        `system/check:fetchAllAppVersionsFromManifest - available label keys: [${Object.keys(labels).join(', ')}] for ${imageTag}`
      );
    }

    // Extract version for each app from labels
    const appVersions: Record<string, string> = {};
    for (const app of appNames) {
      const labelKey = `org.opencontainers.image.version_${app}`;
      if (labels[labelKey]) {
        const version = labels[labelKey];
        appVersions[app] = version;
        console.log(
          `system/check:fetchAllAppVersionsFromManifest - found version for ${app}: ${version} (label: ${labelKey}) for ${imageTag}`
        );
      } else {
        console.log(
          `system/check:fetchAllAppVersionsFromManifest - no version found for ${app} (looked for label: ${labelKey}) for ${imageTag}`
        );
      }
    }

    console.log(
      `system/check:fetchAllAppVersionsFromManifest - extracted ${Object.keys(appVersions).length} app versions for ${imageTag}: [${Object.entries(
        appVersions
      )
        .map(([k, v]) => `${k}=${v}`)
        .join(', ')}]`
    );

    return appVersions;
  } catch (e) {
    if (e instanceof ECRServiceException) {
      console.warn(
        `system/check:fetchAllAppVersionsFromManifest - AwsException: ${e.message} awsCode=${e.name}`
      );
      if (e.name === 'AccessDeniedException') {
        console.warn(
          'system/check:fetchAllAppVersionsFromManifest - AccessDenied. Ensure ecr:BatchGetImage and ecr:GetDownloadUrlForLayer permissions are present.'
        );
      }
    } else {
      console.warn(`system/check:fetchAllAppVersionsFromManifest - unexpected error: ${e}`);
    }
  }
  return {};
}
