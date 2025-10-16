import type { Prisma } from '@prisma/client';
import { env } from '$env/dynamic/private';

export class AWSCommon {
  public static getArtifactsBucketRegion() {
    return env.BUILD_ENGINE_ARTIFACTS_BUCKET_REGION;
  }

  public static getAwsRegion() {
    return env.AWS_REGION;
  }

  public static getArtifactsBucket() {
    return env.BUILD_ENGINE_ARTIFACTS_BUCKET;
  }

  public static getAWSUserAccount() {
    return env.AWS_USER_ID;
  }

  public static getAppEnv() {
    return env.APP_ENV;
  }

  public static getSecretsBucket() {
    return env.BUILD_ENGINE_SECRETS_BUCKET;
  }

  public static getProjectsBucket() {
    return env.BUILD_ENGINE_PROJECTS_BUCKET;
  }

  public static getCodeBuildImageTag() {
    return env.CODE_BUILD_IMAGE_TAG;
  }
  public static getCodeBuildImageRepo() {
    return env.CODE_BUILD_IMAGE_REPO;
  }
  public static getScriptureEarthKey() {
    return env.SCRIPTURE_EARTH_KEY;
  }

  public static getBuildScriptPath() {
    return `s3://${AWSCommon.getProjectsBucket()}/default`;
  }
  public static getArtifactPath(
    job: Prisma.jobGetPayload<{ select: { app_id: true; id: true } }>,
    productionStage: string,
    isPublish = false
  ) {
    return `${productionStage}/jobs/${isPublish ? 'publish' : 'build'}_${job.app_id}_${job.id}`;
  }
  /**
   *  Get the project name which is the prd or stg plus build_app or publish_app
   *
   * @param string baseName build_app or publish_app
   * @return string app name
   */
  public static getCodeBuildProjectName(baseName: string) {
    return `${baseName}-${AWSCommon.getAppEnv()}`;
  }

  public static getRoleName(baseName: string) {
    return `codebuild-${baseName}-service-role-${AWSCommon.getAppEnv()}`;
  }
}
