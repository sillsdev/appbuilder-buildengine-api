import type { Prisma } from '@prisma/client';
import type { Job } from 'bullmq';
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { CodeBuild } from '../aws/codebuild';
import { S3 } from '../aws/s3';
import { AWSVars } from '../aws/vars';
import { BullMQ, getQueues } from '../bullmq';
import { Build } from '../models/build';
import { prisma } from '../prisma';
import { trimStrings } from '$lib/valibot';

export async function product(job: Job<BullMQ.Build.Product>): Promise<unknown> {
  try {
    const build = await prisma.build.findUniqueOrThrow({
      where: {
        id: job.data.buildId
      },
      include: {
        job: true
      }
    });
    job.updateProgress(10);

    const gitUrl = build.job.git_url;

    job.log('Starting build with CodeBuild');
    const script = (
      await readFile(join(process.cwd(), './scripts/appbuilders_s3_build.yml'))
    ).toString();
    job.updateProgress(50);
    // Start the build
    const codeBuild = new CodeBuild();
    const versionCode = await getVersionCode(build.job); // Is there a reason this is not incremented here??
    const lastBuildGuid = await codeBuild.startBuild(gitUrl, build, script, versionCode);
    job.updateProgress(75);
    if (lastBuildGuid) {
      await prisma.build.update({
        where: { id: build.id },
        data: trimStrings(
          {
            build_guid: lastBuildGuid,
            codebuild_url: CodeBuild.getCodeBuildUrl('build_app', lastBuildGuid),
            console_text_url: CodeBuild.getConsoleTextUrl('build_app', lastBuildGuid),
            status: Build.Status.Active
          },
          'build',
          job.log
        )
      });
    }
    const name = pollName(build.id);
    await getQueues().Polling.upsertJobScheduler(name, BullMQ.RepeatEveryMinute, {
      name,
      data: {
        type: BullMQ.JobType.Poll_Build,
        buildId: build.id
      }
    });
    job.updateProgress(100);
    return {
      versionCode,
      lastBuildGuid
    };
  } catch (e) {
    job.log(`${e}`);
    await prisma.build.update({
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

export async function cancel(job: Job<BullMQ.Build.Cancel>): Promise<unknown> {
  const pollRemoved = await getQueues().Polling.removeJobScheduler(pollName(job.data.build.id));
  job.updateProgress(10);
  const codeBuild = new CodeBuild();
  job.updateProgress(20);
  const build = await codeBuild.cancelBuild(job.data.guid, AWSVars.projectName('build_app'));
  job.updateProgress(50);
  const s3 = new S3();
  job.updateProgress(60);
  const objects = await s3.removeCodeBuildFolder(job.data.build);
  job.updateProgress(100);
  return { pollRemoved, build, objects };
}

async function getVersionCode(
  job: Prisma.jobGetPayload<{ select: { id: true; existing_version_code: true } }>
) {
  const build = await prisma.build.aggregate({
    where: {
      job_id: job.id,
      status: Build.Status.Completed,
      result: Build.Result.Success
    },
    _max: {
      version_code: true
    }
  });
  return build._max.version_code ?? job.existing_version_code ?? 0;
}

function pollName(id: number) {
  return `Check status of Build #${id}`;
}
