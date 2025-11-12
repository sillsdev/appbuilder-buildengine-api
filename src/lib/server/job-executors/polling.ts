import type { Prisma } from '@prisma/client';
import type { Job } from 'bullmq';
import { Build } from '../../models/build';
import { CodeBuild } from '../aws/codebuild';
import { BullMQ, getQueues } from '../bullmq';
import { prisma } from '../prisma';

export async function build(job: Job<BullMQ.Polling.Build>): Promise<unknown> {
  try {
    const build = await prisma.build.findUnique({
      where: { id: job.data.buildId },
      include: { job: true }
    });

    if (build?.job) {
      const codeBuild = new CodeBuild();
      const buildStatus = await codeBuild.getBuildStatus(
        build.build_guid!,
        CodeBuild.getCodeBuildProjectName('build_app')
      );
      const phase = buildStatus?.currentPhase;
      let status = buildStatus?.buildStatus;
      job.log(` phase: ${phase} status: ${status}`);
      if (codeBuild.isBuildComplete(buildStatus)) {
        job.log(' Build Complete');
      } else {
        job.log(' Build Incomplete');
      }

      job.updateProgress(50);

      if (codeBuild.isBuildComplete(buildStatus)) {
        await getQueues().Polling.removeJobScheduler(job.name);
        build.status = Build.Status.PostProcessing;
        status = codeBuild.getStatus(buildStatus);
        switch (status) {
          case CodeBuild.Status.Failed:
          case CodeBuild.Status.Fault:
          case CodeBuild.Status.TimedOut:
            build.result = Build.Result.Failure;
            await handleFailure(build);
            break;
          case CodeBuild.Status.Stopped:
            build.result = Build.Result.Aborted;
            await handleFailure(build);
            break;
          case CodeBuild.Status.Succeeded:
            await getQueues().S3.add(`Save Build ${job.data.buildId} to S3`, {
              type: BullMQ.JobType.S3_CopyArtifacts,
              scope: 'build',
              id: job.data.buildId
            });
            break;
        }
      }
      await prisma.build.update({
        where: { id: build.id },
        data: { ...build, job: undefined }
      });
      job.updateProgress(100);
      return {
        status: build.status,
        guid: build.build_guid,
        result: build.result,
        url_base: build.artifact_url_base,
        files: build.artifact_files
      };
    }
  } catch (e) {
    job.log(`${e}`);
    await prisma.build.update({
      where: { id: job.data.buildId },
      data: {
        result: Build.Result.Failure,
        status: Build.Status.Completed,
        error: String(e)
      }
    });
  }
}

export async function publish(job: Job<BullMQ.Polling.Publish>): Promise<unknown> {
  return;
}

async function handleFailure(
  build: Prisma.buildGetPayload<{ select: { error: true; id: true; console_text_url: true } }>
) {
  build.error = build.console_text_url;
  await getQueues().S3.add(`Save Errors for Build ${build.id} to S3`, {
    type: BullMQ.JobType.S3_CopyError,
    scope: 'build',
    id: build.id
  });
}
