import type { Job } from 'bullmq';
import { readFile } from 'fs/promises';
import { CodeBuild } from '../aws/codebuild';
import { S3 } from '../aws/s3';
import { BullMQ, getQueues } from '../bullmq';
import { prisma } from '../prisma';
import { Build } from '$lib/server/models/build';
import { Release } from '$lib/server/models/release';
import { trimStrings } from '$lib/valibot';

export async function product(job: Job<BullMQ.Release.Product>): Promise<unknown> {
  try {
    const release = await prisma.release.findUniqueOrThrow({
      where: { id: job.data.releaseId },
      include: {
        build: {
          include: {
            job: true
          }
        }
      }
    });

    job.updateProgress(10);

    const script = (await readFile('scripts/appbuilders_publish.yml')).toString();

    job.updateProgress(30);
    // Start the build
    const codeBuild = new CodeBuild();
    const lastBuildGuid = await codeBuild.startRelease(release, script);

    job.updateProgress(80);
    if (lastBuildGuid) {
      await prisma.release.update({
        where: { id: job.data.releaseId },
        data: trimStrings(
          {
            build_guid: lastBuildGuid,
            codebuild_url: CodeBuild.getCodeBuildUrl('publish_app', lastBuildGuid),
            console_text_url: CodeBuild.getConsoleTextUrl('publish_app', lastBuildGuid),
            status: Release.Status.Active
          },
          'release',
          job.log
        )
      });
    }
    const name = pollName(release.id);
    await getQueues().Polling.upsertJobScheduler(name, BullMQ.RepeatEveryMinute, {
      name,
      data: {
        type: BullMQ.JobType.Poll_Release,
        releaseId: release.id
      }
    });
    job.updateProgress(100);
    return { lastBuildGuid };
  } catch (e) {
    job.log(`${e}`);
    await prisma.release.update({
      where: { id: job.data.releaseId },
      data: trimStrings(
        {
          result: Build.Result.Failure,
          status: Release.Status.Completed,
          error: String(e)
        },
        'release',
        job.log
      )
    });
  }
}

export async function cancel(job: Job<BullMQ.Release.Cancel>): Promise<unknown> {
  const pollRemoved = await getQueues().Polling.removeJobScheduler(pollName(job.data.release.id));
  job.updateProgress(10);
  const codeBuild = new CodeBuild();
  job.updateProgress(20);
  const release = await codeBuild.cancelBuild(
    job.data.guid,
    CodeBuild.getCodeBuildProjectName('publish_app')
  );
  job.updateProgress(50);
  const s3 = new S3();
  job.updateProgress(60);
  const objects = await s3.removeCodeBuildFolder(job.data.release);
  job.updateProgress(100);
  return { pollRemoved, release, objects };
}

function pollName(id: number) {
  return `Check status of Release #${id}`;
}
