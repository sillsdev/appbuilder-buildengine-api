import type { Job } from 'bullmq';
import { readFile } from 'fs/promises';
import { CodeBuild } from '../aws/codebuild';
import type { BullMQ } from '../bullmq';
import { prisma } from '../prisma';
import { Build } from '$lib/server/models/build';
import { Release } from '$lib/server/models/release';

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
        data: {
          build_guid: lastBuildGuid,
          codebuild_url: CodeBuild.getCodeBuildUrl('publish_app', lastBuildGuid),
          console_text_url: CodeBuild.getConsoleTextUrl('publish_app', lastBuildGuid),
          status: Release.Status.Active
        }
      });
    }
    job.updateProgress(100);
    return { lastBuildGuid };
  } catch (e) {
    job.log(`${e}`);
    await prisma.release.update({
      where: { id: job.data.releaseId },
      data: {
        result: Build.Result.Failure,
        status: Release.Status.Completed,
        error: String(e)
      }
    });
  }
}
