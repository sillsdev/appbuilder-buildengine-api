import {
  BatchGetBuildsCommand,
  BatchGetProjectsCommand,
  CodeBuildClient,
  type ComputeType,
  CreateProjectCommand,
  type ProjectCache,
  type ProjectSource,
  StartBuildCommand
} from '@aws-sdk/client-codebuild';
import { SpanStatusCode, trace } from '@opentelemetry/api';
import type { Prisma } from '@prisma/client';
import { AWSCommon } from './common';
import { S3 } from './s3';
import {
  type BuildForPrefix,
  getArtifactFilename,
  getArtifactPath,
  getBasePrefixUrl
} from '$lib/server/models/artifacts';
import { Job } from '$lib/server/models/job';

export type ReleaseForCodeBuild = Prisma.releaseGetPayload<{
  select: {
    id: true;
    promote_from: true;
    channel: true;
    environment: true;
    targets: true;
    build: {
      select: {
        id: true;
        artifact_files: true;
        job: { select: { id: true; app_id: true; publisher_id: true; git_url: true } };
      };
    };
  };
}>;
export type BuildForCodeBuild = Prisma.buildGetPayload<{
  select: {
    id: true;
    targets: true;
    environment: true;
    job: { select: { id: true; app_id: true; publisher_id: true } };
  };
}>;

const tracer = trace.getTracer('CodeBuild');

export class CodeBuild extends AWSCommon {
  public codeBuildClient;
  public constructor() {
    super();
    this.codeBuildClient = CodeBuild.getCodeBuildClient();
  }

  /**
   * Configure and get the CodeBuild Client
   * @return \Aws\CodeBuild\CodeBuildClient
   */
  public static getCodeBuildClient() {
    return new CodeBuildClient({ region: AWSCommon.getArtifactsBucketRegion() });
  }

  /**
   * Start a build for the function
   *
   * @param string repoUrl
   * @param string commitId
   * @param string build
   * @param string buildSpec Buildspec script to be executed
   * @param string versionCode
   * @return string Guid part of build ID
   */
  public async startBuild(
    repoUrl: string,
    commitId: string,
    build: BuildForCodeBuild,
    buildSpec: string,
    versionCode: number,
    codeCommit: boolean
  ) {
    return tracer.startActiveSpan(`CodeBuild - StartBuild`, async (span) => {
      try {
        const job = build.job;
        const buildProcess = `build_${job.app_id}`;
        span.setAttributes({
          'code-build.process': buildProcess,
          'code-build.repo-url': repoUrl,
          'code-build.commit-id': commitId,
          'code-build.job-id': job.id,
          'code-build.build-id': build.id,
          'code-build.version-code': versionCode
        });
        const artifacts_bucket = CodeBuild.getArtifactsBucket();
        const secretsBucket = CodeBuild.getSecretsBucket();
        const buildApp = CodeBuild.getCodeBuildProjectName('build_app');
        const buildPath = this.getBuildPath(job);
        const artifactPath = getArtifactPath(job, 'codebuild-output');
        span.setAttribute('code-build.artifact-path', artifactPath);
        // Leaving all this code together to make it easier to remove when git is no longer supported
        if (codeCommit) {
          span.addEvent('StartBuild - CodeCommit');
          const res = await this.codeBuildClient.send(
            new StartBuildCommand({
              projectName: buildApp,
              artifactsOverride: {
                location: artifacts_bucket,
                name: '/',
                namespaceType: 'NONE',
                packaging: 'NONE',
                path: artifactPath,
                type: 'S3'
              },
              buildspecOverride: buildSpec,
              environmentVariablesOverride: [
                {
                  name: 'BUILD_NUMBER',
                  value: String(build.id)
                },
                {
                  name: 'APP_BUILDER_SCRIPT_PATH',
                  value: buildPath
                },
                {
                  name: 'PUBLISHER',
                  value: job.publisher_id
                },
                {
                  name: 'VERSION_CODE',
                  value: '' + versionCode
                },
                {
                  name: 'SECRETS_BUCKET',
                  value: secretsBucket
                }
              ],
              sourceLocationOverride: repoUrl,
              sourceVersion: commitId
            })
          );
          const buildId = res.build?.id;
          const buildGuid = buildId?.substring(buildId.indexOf(':') + 1);
          span.setAttributes({
            'code-build.build.build-id': buildId,
            'code-build.build.build-guid': buildGuid
          });
          return buildGuid;
        } else {
          span.addEvent('StartBuild - S3');
          const targets = build.targets ?? 'apk play-listing';
          const environmentArray = [
            // BUILD_NUMBER Must be first for tests
            {
              name: 'BUILD_NUMBER',
              value: String(build.id)
            },
            {
              name: 'APP_BUILDER_SCRIPT_PATH',
              value: buildPath
            },
            {
              name: 'PUBLISHER',
              value: job.publisher_id
            },
            {
              name: 'VERSION_CODE',
              value: '' + versionCode
            },
            {
              name: 'SECRETS_BUCKET',
              value: secretsBucket
            },
            {
              name: 'PROJECT_S3',
              value: repoUrl
            },
            {
              name: 'TARGETS',
              value: targets
            },
            {
              name: 'SCRIPT_S3',
              value: S3.getBuildScriptPath()
            }
          ];
          const adjustedEnvironmentArray = this.addEnvironmentToArray(
            environmentArray,
            build.environment
          );
          const computeType = this.getComputeType(adjustedEnvironmentArray);
          const imageTag = this.getImageTag(adjustedEnvironmentArray);
          const result = await this.codeBuildClient.send(
            new StartBuildCommand({
              projectName: buildApp,
              artifactsOverride: {
                location: artifacts_bucket, // output bucket
                name: '/', // name of output artifact object
                namespaceType: 'NONE',
                packaging: 'NONE',
                path: artifactPath, // path to output artifacts
                type: 'S3' // REQUIRED
              },
              buildspecOverride: buildSpec,
              environmentVariablesOverride: adjustedEnvironmentArray,
              sourceTypeOverride: 'NO_SOURCE',
              computeTypeOverride: computeType,
              imageOverride: CodeBuild.getCodeBuildImageRepo() + ':' + imageTag
            })
          );
          const buildId = result.build?.id;
          const buildGuid = buildId?.substring(buildId.indexOf(':') + 1);
          span.setAttributes({
            'code-build.build.build-id': buildId,
            'code-build.build.build-guid': buildGuid
          });
          return buildGuid;
        }
      } catch (e) {
        span.recordException(e as Error);
        span.setStatus({
          code: SpanStatusCode.ERROR,
          message: (e as Error).message
        });
      } finally {
        span.end();
      }
    });
  }

  /**
   * This method returns the build status object
   *
   * @param string guid - Code Build GUID for the build
   * @param string buildProcess - Name of code build project (e.g. build_scriptureappbuilder)
   * @return AWS/Result Result object on the status of the build
   */
  public async getBuildStatus(guid: string, buildProcess: string) {
    const span = trace.getActiveSpan();
    try {
      span?.setAttributes({
        'code-build.build-process': buildProcess,
        'code-build.build-guid': guid
      });

      const buildId = this.getBuildId(guid, buildProcess);
      const result = await this.codeBuildClient.send(
        new BatchGetBuildsCommand({
          ids: [buildId]
        })
      );
      return result.builds?.at(0);
    } catch (e) {
      span?.recordException(e as Error);
      span?.setStatus({
        code: SpanStatusCode.ERROR,
        message: (e as Error).message
      });
    }
  }

  /**
   * This method returns the completion status of the job
   * based upon the build status object passed in
   */
  public isBuildComplete(buildStatus: Awaited<ReturnType<typeof this.getBuildStatus>>) {
    return buildStatus?.buildComplete;
  }
  /**
   * This method returns the status of the build
   * @param AWS/Result Return value from getBuildStatus
   */
  public getStatus(buildStatus: Awaited<ReturnType<typeof this.getBuildStatus>>) {
    return buildStatus?.buildStatus;
  }

  /**
   * Recreate the build id
   *
   * @param string guid Build GUID
   * @param string buildProcess CodeBuild Project Name (e.g. scriptureappbuilder)
   * @return string CodeBuild build arn
   */
  private getBuildId(guid: string, buildProcess: string) {
    return `${buildProcess}:${guid}`;
  }
  /**
   * Returns the name of the shell command to be run
   *
   * @param job Job associated with this build
   * @return string Name of the task to be run
   */
  private getBuildPath(job: Prisma.jobGetPayload<{ select: { app_id: true } }>) {
    switch (job.app_id) {
      case Job.AppType.ScriptureApp:
        return 'scripture-app-builder';
      case Job.AppType.ReadingApp:
        return 'reading-app-builder';
      case Job.AppType.DictionaryApp:
        return 'dictionary-app-builder';
      case Job.AppType.KeyboardApp:
        return 'keyboard-app-builder';
      default:
        return 'unknown';
    }
  }

  /**
   * Starts a publish action
   * @param Release release
   * @param string releaseSpec
   * @return false|string
   */
  public async startRelease(release: ReleaseForCodeBuild, releaseSpec: string) {
    return tracer.startActiveSpan(`CodeBuild - StartRelease`, async (span) => {
      try {
        const build = release.build;
        const job = build.job;
        const buildPath = this.getBuildPath(job);
        const artifacts_bucket = CodeBuild.getArtifactsBucket();
        const artifactPath = getArtifactPath(job, 'codebuild-output', true);
        const secretsBucket = CodeBuild.getSecretsBucket();
        const scriptureEarthKey = CodeBuild.getScriptureEarthKey();
        const publishApp = CodeBuild.getCodeBuildProjectName('publish_app');
        const promoteFrom = release.promote_from ?? '';

        const sourceLocation = this.getSourceLocation(build);
        const s3Artifacts = this.getArtifactsLocation(build);
        const targets = release.targets ?? 'google-play';

        span.setAttributes({
          'code-build.release-id': release.id,
          'code-build.build-id': build.id,
          'code-build.artifact-path': artifactPath,
          'code-build.promote-from': promoteFrom,
          'code-build.source': sourceLocation,
          'code-build.targets': targets
        });

        const environmentArray = [
          // RELEASE_NUMBER Must be first for tests
          {
            name: 'RELEASE_NUMBER',
            value: String(release.id)
          },
          {
            name: 'APP_BUILDER_SCRIPT_PATH',
            value: buildPath
          },
          {
            name: 'BUILD_NUMBER',
            value: String(build.id)
          },
          {
            name: 'CHANNEL',
            value: release.channel
          },
          {
            name: 'PUBLISHER',
            value: job.publisher_id
          },
          {
            name: 'PROJECT_S3',
            value: job.git_url
          },
          {
            name: 'SECRETS_BUCKET',
            value: secretsBucket
          },
          {
            name: 'PROMOTE_FROM',
            value: promoteFrom
          },
          {
            name: 'ARTIFACTS_S3_DIR',
            value: s3Artifacts
          },
          {
            name: 'TARGETS',
            value: targets
          },
          {
            name: 'SCRIPT_S3',
            value: S3.getBuildScriptPath()
          },
          {
            name: 'SCRIPTURE_EARTH_KEY',
            value: scriptureEarthKey
          }
        ];
        const adjustedEnvironmentArray = this.addEnvironmentToArray(
          environmentArray,
          release.environment
        );
        const result = await this.codeBuildClient.send(
          new StartBuildCommand({
            projectName: publishApp,
            artifactsOverride: {
              location: artifacts_bucket, // output bucket
              name: '/', // name of output artifact object
              namespaceType: 'NONE',
              packaging: 'NONE',
              path: artifactPath, // path to output artifacts
              type: 'S3' // REQUIRED
            },
            buildspecOverride: releaseSpec,
            environmentVariablesOverride: adjustedEnvironmentArray,
            sourceLocationOverride: sourceLocation
          })
        );
        const buildId = result.build?.id;
        const buildGuid = buildId?.substring(buildId.indexOf(':') + 1);
        span.setAttributes({
          'code-build.release.build-id': buildId,
          'code-build.release.build-guid': buildGuid
        });
        return buildGuid;
      } catch (e) {
        span.recordException(e as Error);
        span.setStatus({
          code: SpanStatusCode.ERROR,
          message: (e as Error).message
        });
      } finally {
        span.end();
      }
    });
  }
  /**
   * Get the url for the apk file in a format that codebuild accepts for an S3 Source
   * We are using the apk file as a source, even though we're not really using it because
   * codebuild requires a source and if S3 is the type, it must be a zip file.
   *
   * @param Build build - build object for this operation
   * @return string - Arn format for the apk file
   */
  private getSourceLocation(
    build: Prisma.buildGetPayload<{
      select: { id: true; artifact_files: true; job: { select: { id: true; app_id: true } } };
    }>
  ) {
    const appEnv = S3.getAppEnv();
    const apkFilename = getArtifactFilename(/\.apk$/, build.artifact_files);
    const sourceLocation = S3.getS3Arn(build, appEnv, apkFilename);
    return sourceLocation;
  }
  /**
   * Get the URL for the S3 artifacts folder in the format required by the buildspec
   *
   * @param Build build - build object for this operation
   * @return string - s3:// url format for s3 artifacts folder
   */
  private getArtifactsLocation(build: BuildForPrefix) {
    const artifactsBucket = CodeBuild.getArtifactsBucket();
    const artifactFolder = getBasePrefixUrl(build, CodeBuild.getAppEnv());
    return `s3://${artifactsBucket}/${artifactFolder}`;
  }

  /**
   * This creates a project in CodeBuild
   *
   * @param string base_name base project being built, e.g. build_app or publish_app
   * @param string role_arn Arn for the IAm role
   * @param Array cache Strings defining the cache parameter of the build
   * @param Array source Strings defining the source parameter of the build
   *
   */
  public async createProject(
    base_name: string,
    role_arn: string,
    cache: ProjectCache,
    source: ProjectSource
  ) {
    const project_name = CodeBuild.getCodeBuildProjectName(base_name);
    const artifacts_bucket = CodeBuild.getArtifactsBucket();
    return await this.codeBuildClient.send(
      new CreateProjectCommand({
        artifacts: {
          // REQUIRED
          location: artifacts_bucket, // output bucket
          name: '/', // name of output artifact object
          namespaceType: 'NONE',
          packaging: 'NONE',
          path: 'codebuild-output', // path to output artifacts
          type: 'S3' // REQUIRED
        },
        cache: cache,
        environment: {
          // REQUIRED
          computeType: 'BUILD_GENERAL1_SMALL', // REQUIRED
          image: CodeBuild.getCodeBuildImageRepo() + ':' + CodeBuild.getCodeBuildImageTag(), // REQUIRED
          privilegedMode: false,
          type: 'LINUX_CONTAINER' // REQUIRED
        },
        name: project_name, // REQUIRED
        serviceRole: role_arn,
        source: source
      })
    );
  }

  public static getConsoleTextUrl(baseName: string, guid: string) {
    const projectName = CodeBuild.getCodeBuildProjectName(baseName);
    const region = AWSCommon.getArtifactsBucketRegion() ?? 'us-east-1';
    const regionUrl = `https://console.aws.amazon.com/cloudwatch/home?region=${region}`;
    const taskExtension = `#logEvent:group=/aws/codebuild/${projectName};stream=${guid}`;
    return `${regionUrl}${taskExtension}`;
  }
  public static getCodeBuildUrl(baseName: string, guid: string) {
    const projectName = CodeBuild.getCodeBuildProjectName(baseName);
    const region = AWSCommon.getArtifactsBucketRegion() ?? 'us-east-1';
    const regionUrl = `https://console.aws.amazon.com/codebuild/home?region=${region}`;
    const taskExtension = `#/builds/${projectName}:${guid}/view/new`;
    return `${regionUrl}${taskExtension}`;
  }
  /**
   * Checks to see if the current project exists
   *
   * @param string baseName - Name of the project to search for
   * @return boolean true if project found
   */
  public async projectExists(baseName: string) {
    let exists = false;
    try {
      const projectName = CodeBuild.getCodeBuildProjectName(baseName);
      trace?.getActiveSpan()?.setAttribute('code-build.project-name', projectName);
      const result = await this.codeBuildClient.send(
        new BatchGetProjectsCommand({
          names: [projectName]
        })
      );
      exists = !!result.projects?.length;
    } catch (e) {
      trace.getActiveSpan()?.recordException(e as Error);
      trace.getActiveSpan()?.setStatus({
        code: SpanStatusCode.ERROR,
        message: (e as Error).message
      });
    }
    return exists;
  }

  private addEnvironmentToArray(
    environmentVariables: { name: string; value: string }[],
    environment: string | null
  ) {
    if (environment === 'null') {
      environment = null;
    }
    if (environment) {
      try {
        return environmentVariables.concat(
          Object.entries(JSON.parse(environment) as Record<string, string>).map(
            ([name, value]) => ({
              name,
              value
            })
          )
        );
      } catch {
        /* empty */
      }
    }
    return environmentVariables;
  }

  private getImageTag(environmentVariables: { name: string; value: string }[]) {
    return (
      environmentVariables.find(({ name }) => name === 'BUILD_IMAGE_TAG')?.value ??
      CodeBuild.getCodeBuildImageTag()
    );
  }

  private getComputeType(environmentVariables: { name: string; value: string }[]): ComputeType {
    let computeType: ComputeType = 'BUILD_GENERAL1_SMALL';
    switch (environmentVariables.find(({ name }) => name === 'BUILD_COMPUTE_TYPE')?.value) {
      case 'small':
        computeType = 'BUILD_GENERAL1_SMALL';
        break;
      case 'medium':
        computeType = 'BUILD_GENERAL1_MEDIUM';
        break;
      case 'large':
        computeType = 'BUILD_GENERAL1_LARGE';
        break;
      case '2xlarge':
        computeType = 'BUILD_GENERAL1_2XLARGE';
        break;
    }
    return computeType;
  }
}

// eslint-disable-next-line @typescript-eslint/no-namespace
export namespace CodeBuild {
  export enum Status {
    Completed = 'COMPLETED', // this is not in AWS Build StatusType for some reason
    Succeeded = 'SUCCEEDED',
    Failed = 'FAILED',
    InProgress = 'IN_PROGRESS',
    Stopped = 'STOPPED',
    TimedOut = 'TIMED_OUT',
    Fault = 'FAULT'
  }
}
