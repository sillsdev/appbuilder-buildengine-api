import type { Prisma } from '@prisma/client';
import type { Job } from 'bullmq';
import { CodeBuild } from '../aws/codebuild';
import { AWSVars } from '../aws/vars';
import { BullMQ, getQueues } from '../bullmq';
import { Build } from '../models/build';
import { prisma } from '../prisma';
import { Release } from '$lib/server/models/release';
import type { Logger } from '$lib/utils';
import { trimStrings } from '$lib/valibot';

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
        AWSVars.projectName('build_app')
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
            await handleBuildFailure(build);
            break;
          case CodeBuild.Status.Stopped:
            build.result = Build.Result.Aborted;
            await handleBuildFailure(build);
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
        data: trimStrings({ ...build, job: undefined }, 'build', job.log)
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
    // don't await, in case error throws here
    getQueues().Polling.removeJobScheduler(job.name);
    job.log(`${e}`);
    await prisma.build.updateMany({
      where: { id: job.data.buildId },
      data: trimStrings(
        {
          result: Build.Result.Failure,
          status: Build.Status.Completed,
          error: String(e)
        },
        'build',
        job.log
      )
    });
    // rethrow so error makes it to HoneyComb
    throw e;
  }
}

async function handleBuildFailure(
  build: Prisma.buildGetPayload<{ select: { error: true; id: true; console_text_url: true } }>
) {
  build.error = build.console_text_url;
  await getQueues().S3.add(`Save Errors for Build ${build.id} to S3`, {
    type: BullMQ.JobType.S3_CopyError,
    scope: 'build',
    id: build.id
  });
}

export async function release(job: Job<BullMQ.Polling.Release>): Promise<unknown> {
  try {
    const release = await prisma.release.findUnique({
      where: { id: job.data.releaseId },
      include: { build: true }
    });

    if (release?.build) {
      const codeBuild = new CodeBuild();

      const buildStatus = await codeBuild.getBuildStatus(
        release.build_guid!,
        AWSVars.projectName('publish_app')
      );
      const phase = buildStatus?.currentPhase;
      let status = buildStatus?.buildStatus;
      job.log(` phase: ${phase} status: ${status}`);
      if (codeBuild.isBuildComplete(buildStatus)) {
        job.log(' Build Complete');
      } else {
        job.log(' Build Incomplete');
      }

      if (codeBuild.isBuildComplete(buildStatus)) {
        await getQueues().Polling.removeJobScheduler(job.name);
        release.status = Build.Status.PostProcessing;
        status = codeBuild.getStatus(buildStatus);
        switch (status) {
          case CodeBuild.Status.Failed:
          case CodeBuild.Status.Fault:
          case CodeBuild.Status.TimedOut:
            release.result = Build.Result.Failure;
            await handleReleaseFailure(release, job.log);
            break;
          case CodeBuild.Status.Stopped:
            release.result = Build.Result.Aborted;
            await handleReleaseFailure(release, job.log);
            break;
          case CodeBuild.Status.Succeeded:
            release.result = Build.Result.Success;
            await prisma.build.update({
              where: { id: release.build.id },
              data: { channel: release.channel }
            });
            await getQueues().S3.add(`Save Release ${release.id} to S3`, {
              type: BullMQ.JobType.S3_CopyArtifacts,
              scope: 'release',
              id: release.id
            });
            break;
        }
      }
      await prisma.release.update({
        where: { id: release.id },
        data: {
          status: release.status,
          result: release.result
        }
      });
      return {
        status: release.status,
        result: release.result
      };
    }
  } catch (e) {
    // don't await, in case error throws here
    getQueues().Polling.removeJobScheduler(job.name);
    job.log(`${e}`);
    await prisma.release.updateMany({
      where: { id: job.data.releaseId },
      data: { result: Build.Result.Failure, status: Release.Status.Completed }
    });
    // rethrow so error makes it to HoneyComb
    throw e;
  }
}

async function handleReleaseFailure(
  release: Prisma.releaseGetPayload<{ select: { id: true; console_text_url: true } }>,
  log: Logger
) {
  await prisma.release.update({
    where: { id: release.id },
    data: trimStrings({ error: release.console_text_url }, 'release', log)
  });
  await getQueues().S3.add(`Save Errors for Release ${release.id} to S3`, {
    type: BullMQ.JobType.S3_CopyError,
    scope: 'release',
    id: release.id
  });
}
