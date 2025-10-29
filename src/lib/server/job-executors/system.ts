import type { ProjectCache, ProjectSource } from '@aws-sdk/client-codebuild';
import type { Job } from 'bullmq';
import { join } from 'node:path';
import { CodeBuild } from '../aws/codebuild';
import { IAmWrapper } from '../aws/iamwrapper';
import { S3 } from '../aws/s3';
import type { BullMQ } from '../bullmq';

type Logger = (msg: string) => void;

export async function createCodeBuildProject(
  job: Job<BullMQ.System.CreateCodeBuildProject>
): Promise<unknown> {
  try {
    const build = await createProject(
      'build_app',
      {
        location: S3.getArtifactsBucket() + '/codebuild-cache',
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
    const project = copyFolder(
      join(process.cwd(), './scripts/project_default'),
      's3://' + S3.getArtifactsBucket(),
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
        location: `arn:aws:s3:::${S3.getArtifactsBucket()}/default/default.zip`,
        type: 'S3'
      },
      (msg) => job.log(msg)
    );

    job.updateProgress(75);

    const scripts = await copyFolder(
      join(process.cwd(), './scripts/upload'),
      's3://' + S3.getProjectsBucket(),
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
