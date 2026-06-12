import type { ProjectCache, ProjectSource } from '@aws-sdk/client-codebuild';
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
import type { Job } from 'bullmq';
import { join } from 'node:path';
import { CodeBuild } from '../aws/codebuild';
import { IAmWrapper } from '../aws/iamwrapper';
import { S3 } from '../aws/s3';
import type { BullMQ } from '../bullmq';
import { prisma } from '../prisma';
import { AWSVars } from '$lib/server/aws/vars';

type Logger = (msg: string) => void;

export async function createCodeBuildProject(
  job: Job<BullMQ.System.CreateCodeBuildProject>
): Promise<unknown> {
  try {
    const build = await createProject(
      'build_app',
      {
        location: AWSVars.artifacts() + '/codebuild-cache',
        type: 'S3'
      },
      {
        buildspec: 'version: 0.2',
        gitCloneDepth: 1,
        location: 'https://git-codecommit.us-east-1.amazonaws.com/v1/repos/sample',
        type: 'CODECOMMIT'
      },
      (msg) => job.log(msg)
    );

    job.updateProgress(25);

    // Build publish role if necessary
    // Copy default file
    const project = await copyFolder(
      join(process.cwd(), './scripts/project_default'),
      's3://' + AWSVars.artifacts(),
      (msg) => job.log(msg)
    );

    job.updateProgress(50);

    const publish = await createProject(
      'publish_app',
      {
        type: 'NO_CACHE'
      },
      {
        buildspec: 'version: 0.2',
        gitCloneDepth: 1,
        location: `arn:aws:s3:::${AWSVars.artifacts()}/default/default.zip`,
        type: 'S3'
      },
      (msg) => job.log(msg)
    );

    job.updateProgress(75);

    const scripts = await copyFolder(
      join(process.cwd(), './scripts/upload'),
      's3://' + AWSVars.projects(),
      (msg) => job.log(msg)
    );

    job.updateProgress(100);
    return {
      build,
      project,
      publish,
      scripts
    };
  } catch (e) {
    job.log(`${e}`);
  }
  return;
}

async function createProject(
  projectName: string,
  cache: ProjectCache,
  source: ProjectSource,
  log: Logger
) {
  try {
    const codeBuild = new CodeBuild();
    const iamWrapper = new IAmWrapper();
    if (!(await codeBuild.projectExists(projectName))) {
      log('Creating build project ' + projectName);

      const roleArn = await iamWrapper.getRoleArn(projectName);
      const res = await codeBuild.createProject(projectName, roleArn, cache, source);
      log('Project created');
      return res;
    }
  } catch (e) {
    log(`${e}`);
  }
}
async function copyFolder(sourceFolder: string, bucket: string, log: Logger) {
  try {
    const s3 = new S3();
    const res = await s3.uploadFolder(sourceFolder, bucket);
    log('Copy completed');
    return res;
  } catch (e) {
    log(`${e}`);
  }
}

const appNames = [
  'scriptureappbuilder',
  'readingappbuilder',
  'dictionaryappbuilder',
  'keyboardappbuilder'
] as const;

type App = (typeof appNames)[number];

export async function refreshAppVersions(
  job: Job<BullMQ.System.RefreshAppVersions>
): Promise<unknown> {
  // db connectivity handled by server hooks
  // Prepare default response structure
  let versions: Map<App, string> | null = null;
  let imageHash: string | null = null;

  const repoConfig = AWSVars.imageRepo();
  const tagFilter = AWSVars.imageTag();
  const region = AWSVars.artifactsRegion();

  // Status: log repo config presence
  job.log(`repoConfig=${repoConfig ?? '(none)'}`);

  // Try to query ECR if AWS SDK is available and repo is configured
  if (repoConfig) {
    job.log(`AwsEcrEcrClient available, attempting ECR query (region=${region})`);
    try {
      const client = new ECRClient({
        region
      });

      job.log('EcrClient constructed');

      // repositoryName for ECR is typically the last path segment if repo includes a path
      let repoName = repoConfig;
      if (repoName.includes('/')) {
        const parts = repoName.split('/');
        repoName = parts.at(-1)!;
      }

      job.log(`resolved repositoryName=${repoName}`);

      job.updateProgress(10);

      // Verify repository exists before calling describeImages
      job.log(`*** Verify ECR Repository ***`);
      const repoMeta = await verifyEcrRepositoryExists(client, repoName, (msg) => job.log(msg));
      job.log(`*****************************`);

      job.updateProgress(30);

      let imageDetails: ImageDetail[] = [];
      if (!repoMeta) {
        job.log(
          `repository verification failed or repository not found: ${repoName} - skipping describeImages.`
        );
      } else {
        // Describe tagged images
        job.log('calling describeImages for ' + repoName);
        const resp = await client.send(
          new DescribeImagesCommand({ repositoryName: repoName, filter: { tagStatus: 'TAGGED' } })
        );
        imageDetails = resp['imageDetails'] ?? [];

        job.log(`describeImages returned ${imageDetails.length} imageDetails`);
      }

      job.updateProgress(40);

      // Look for version information in image manifests
      for (const img of imageDetails) {
        if (!img['imageTags']) {
          continue;
        }

        job.log(`image: ${JSON.stringify(img)}`);

        // Extract image digest (hash) from the image details - this is available without fetching manifest
        const imageDigest = img['imageDigest'] || null;
        job.log(
          `found imageDigest: ${imageDigest ?? 'none'} for image with tags: ${img['imageTags'].join(', ')}`
        );

        for (const imgTag of img['imageTags']) {
          // Only process tags that match the tagFilter (if set)
          if (tagFilter && !imgTag.includes(tagFilter)) {
            job.log(`skipping tag ${imgTag} (does not match tagFilter)`);
            continue;
          }

          // Extract versions for all apps from the image manifest/config
          job.log(`*** Fetch AppVersions ***`);
          versions = await fetchAllAppVersionsFromManifest(client, repoName, imgTag, (msg) =>
            job.log(msg)
          );
          job.log(`*************************`);

          if (versions?.size) {
            imageHash = imageDigest;
          } else {
            job.log(`no app versions found in manifest for tag ${imgTag}`);
          }
        }
      }
    } catch (e) {
      // If ECR query fails (missing creds/permissions or network), leave versions empty
      // Do not throw: the health check should still succeed if DB is OK
      job.log(`ECR query failed: ${e}`);
      // Detect AccessDenied and add an extra hint to logs
      if (e instanceof Error && e.message.match(/AccessDenied/i)) {
        job.log(
          'ECR Access Denied. Ensure IAM principal has ecr:DescribeImages for the repository and correct region/account.'
        );
      }
    }
  } else {
    job.log('skipping ECR query (no repoConfig or ECR client not available)');
  }

  job.updateProgress(90);

  const entries = await Promise.all(
    versions?.entries().map(([appName, version]) =>
      prisma.appVersion.upsert({
        where: { appName },
        update: { version, imageHash: imageHash! },
        create: { appName, version, imageHash: imageHash! }
      })
    ) ?? []
  );

  job.updateProgress(100);

  return entries;
}

/**
 * Verify that an ECR repository exists and return its metadata.
 * Returns repository array on success, or null on not found / error.
 * Logs info/warnings for common AWS error codes.
 */
async function verifyEcrRepositoryExists(client: ECRClient, repoName: string, log: Logger) {
  try {
    log(`calling describeRepositories for ${repoName}`);
    const resp = await client.send(
      new DescribeRepositoriesCommand({
        repositoryNames: [repoName]
      })
    );
    const repos = resp['repositories'] ?? [];
    if (repos.length) {
      log(`repository found: ${repos[0]['repositoryArn'] ?? repoName}`);
      return repos[0];
    }
    log(`describeRepositories returned empty for ${repoName}`);
  } catch (e) {
    if (e instanceof ECRServiceException) {
      log(`AwsException: ${e.message} awsCode=${e.name}`);
      if (e instanceof RepositoryNotFoundException) {
        log(`repository not found: ${repoName}`);
      } else if (e.message.match(/AccessDenied/i) || e.name === 'AccessDeniedException') {
        log(
          `access denied for describeRepositories on ${repoName}. Ensure IAM permissions include ecr:DescribeRepositories and ecr:DescribeImages.`
        );
      } else {
        // Other AWS error: log and return null
        log(`unexpected ECR error: ${e.message}`);
      }
    } else {
      log(`unexpected error: ${e}`);
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
  imageTag: string,
  log: Logger
): Promise<Map<App, string> | null> {
  try {
    log(`batchGetImage for ${imageTag}`);
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
      return fail(log(`no images returned for tag ${imageTag}`));
    }

    log(`received ${images.length} image(s) for tag ${imageTag}`);

    const imageManifest = images[0]['imageManifest'];
    if (!imageManifest) {
      return fail(log(`imageManifest missing for ${imageTag}`));
    }

    log(`parsing manifest JSON for ${imageTag}`);
    let manifest;
    try {
      manifest = JSON.parse(imageManifest);
    } catch {
      return fail(
        log(`unable to decode manifest JSON for ${imageTag}\nManifest:\n${imageManifest ?? ''}`)
      );
    }

    log(`manifest schema version: ${manifest['schemaVersion'] ?? 'unknown'} for ${imageTag}`);

    // Locate config digest in manifest (schema v2) to fetch the image config with labels
    const configDigest = manifest['config']['digest'];
    if (!configDigest) {
      return fail(log(`no config digest found in manifest for ${imageTag}`));
    }

    log(`found config digest: ${configDigest} for ${imageTag}`);

    // Get a download URL for the config blob and fetch it
    log(`getDownloadUrlForLayer for ${configDigest}`);
    const dl = await client.send(
      new GetDownloadUrlForLayerCommand({
        repositoryName: repoName,
        layerDigest: configDigest
      })
    );
    const url = dl['downloadUrl'];
    if (!url) {
      return fail(log(`no downloadUrl for config ${configDigest}`));
    }

    log(`fetching config from URL for ${imageTag}`);

    // Fetch the config JSON
    const configContent = await fetch(url).then((r) => r.text());
    if (!configContent) {
      return fail(log(`failed to fetch config from ${url}`));
    }

    log(`received config content (${configContent.length} bytes) for ${imageTag}`);

    let config;
    try {
      config = JSON.parse(configContent);
    } catch {
      return fail(
        log(`unable to decode config JSON for ${imageTag}\nConfig:\n${configContent ?? ''}`)
      );
    }

    log(`parsed config JSON successfully for ${imageTag}`);

    // Common places for labels
    let labels: Record<string, string> = {};
    if (config['config']['Labels']) {
      labels = config['config']['Labels'];
      log(`found ${labels.length} labels in config.Labels for ${imageTag}`);
    } else if (config['container_config']['Labels']) {
      labels = config['container_config']['Labels'];
      log(`found ${labels.length} labels in container_config.Labels for ${imageTag}`);
    } else {
      log(`no labels found in config for ${imageTag}`);
    }

    // Log all label keys for debugging
    if (Object.keys(labels).length) {
      log(`available label keys: [${Object.keys(labels).join(', ')}] for ${imageTag}`);
    }

    // Extract version for each app from labels
    const appVersions = new Map<App, string>(
      appNames
        .map((app) => {
          const labelKey = `org.opencontainers.image.version_${app}`;
          if (labels[labelKey]) {
            const version = labels[labelKey];
            log(`found version for ${app}: ${version} (label: ${labelKey}) for ${imageTag}`);
            return [app, version] as [App, string];
          } else {
            log(`no version found for ${app} (looked for label: ${labelKey}) for ${imageTag}`);
            return null;
          }
        })
        .filter((p) => !!p)
    );

    log(
      `extracted ${appVersions.size} app versions for ${imageTag}: [${appVersions
        .entries()
        .map(([k, v]) => `${k}=${v}`)
        .toArray()
        .join(', ')}]`
    );

    return appVersions;
  } catch (e) {
    if (e instanceof ECRServiceException) {
      log(`AwsException: ${e.message} awsCode=${e.name}`);
      if (e.name === 'AccessDeniedException') {
        log(
          'AccessDenied. Ensure ecr:BatchGetImage and ecr:GetDownloadUrlForLayer permissions are present.'
        );
      }
    } else {
      log(`unexpected error: ${e}`);
    }
  }
  return null;
}

function fail(_: void) {
  return null;
}
