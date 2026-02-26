import { env } from '$env/dynamic/private';

export class AWSVars {
  public static artifactsRegion() {
    return env.BUILD_ENGINE_ARTIFACTS_BUCKET_REGION || 'us-east-1';
  }

  public static region() {
    return env.AWS_REGION;
  }

  public static artifacts() {
    return env.BUILD_ENGINE_ARTIFACTS_BUCKET;
  }

  public static userId() {
    return env.AWS_USER_ID;
  }

  public static appEnv() {
    return env.APP_ENV;
  }

  public static secrets() {
    return env.BUILD_ENGINE_SECRETS_BUCKET;
  }

  public static projects() {
    return env.BUILD_ENGINE_PROJECTS_BUCKET;
  }

  public static imageTag() {
    return env.CODE_BUILD_IMAGE_TAG || 'production';
  }
  public static imageRepo() {
    return env.CODE_BUILD_IMAGE_REPO || 'sillsdev/appbuilder-agent';
  }
  public static scriptureEarthKey() {
    return env.SCRIPTURE_EARTH_KEY;
  }

  public static scriptsPath() {
    return `s3://${AWSVars.projects()}/default`;
  }
  /**
   *  Get the project name which is the prd or stg plus build_app or publish_app
   *
   * @param string baseName build_app or publish_app
   * @return string app name
   */
  public static projectName(baseName: string) {
    return `${baseName}-${AWSVars.appEnv()}`;
  }

  public static roleName(baseName: string) {
    return `codebuild-${baseName}-service-role-${AWSVars.appEnv()}`;
  }
}
