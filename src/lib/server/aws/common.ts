import type { Prisma } from '@prisma/client';

export class AWSCommon {
  public static getArtifactsBucketRegion() {
    return process.env.BUILD_ENGINE_ARTIFACTS_BUCKET_REGION;
  }

  public static getArtifactsBucket() {
    return process.env.BUILD_ENGINE_ARTIFACTS_BUCKET;
  }

  public static getAWSUserAccount() {
    return process.env.AWS_USER_ID;
  }

  public static getAppEnv() {
    return process.env.APP_ENV;
  }

  public static getSecretsBucket() {
    return process.env.BUILD_ENGINE_SECRETS_BUCKET;
  }

  public static getProjectsBucket() {
    return process.env.BUILD_ENGINE_PROJECTS_BUCKET;
  }

  public static getCodeBuildImageTag() {
    return process.env.CODE_BUILD_IMAGE_TAG;
  }
  public static getCodeBuildImageRepo() {
    return process.env.CODE_BUILD_IMAGE_REPO;
  }
  public static getScriptureEarthKey() {
    return process.env.SCRIPTURE_EARTH_KEY;
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
