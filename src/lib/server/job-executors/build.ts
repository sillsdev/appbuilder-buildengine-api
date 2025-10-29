import type { Prisma } from '@prisma/client';
import type { Job } from 'bullmq';
import { readFile } from 'node:fs/promises';
import { Build } from '../../models/build';
import { CodeBuild } from '../aws/codebuild';
import { CodeCommit } from '../aws/codecommit';
import type { BullMQ } from '../bullmq';
import { prisma } from '../prisma';

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

    // Don't start job if a job for this build is currently running
    const builds = await prisma.build.count({
      where: {
        job_id: build.job_id,
        status: { in: [Build.Status.Active, Build.Status.PostProcessing] }
      }
    });
    if (builds > 0) {
      job.log('Existing active builds found. Build cancelled');
      // TODO retry after???
      return { existing: builds };
    }
    const gitUrl = build.job.git_url;
    // Check to see if codebuild project
    const codeCommitProject = gitUrl.startsWith('ssh://');
    if (codeCommitProject) {
      job.log('Starting build with CodeCommit');
      // Left this block intact to make it easier to remove when codecommit is not supported
      const codecommit = new CodeCommit();
      const branch = 'master';
      const repoUrl = await codecommit.getSourceURL(gitUrl);
      if (!repoUrl) throw new Error('No repoUrl found!');
      const commitId = await codecommit.getCommitId(gitUrl, branch);
      if (!commitId) throw new Error('No commitId found!');
      job.updateProgress(25);

      const script = (await readFile('scripts/appbuilders_build.yml')).toString();
      job.updateProgress(50);
      // Start the build
      const codeBuild = new CodeBuild();
      const versionCode = (await getVersionCode(build.job)) + 1;
      const lastBuildGuid = await codeBuild.startBuild(
        repoUrl,
        commitId,
        build,
        script,
        versionCode,
        codeCommitProject
      );
      job.updateProgress(75);
      if (lastBuildGuid) {
        await prisma.build.update({
          where: { id: build.id },
          data: {
            build_guid: lastBuildGuid,
            codebuild_url: CodeBuild.getCodeBuildUrl('build_app', lastBuildGuid),
            console_text_url: CodeBuild.getConsoleTextUrl('build_app', lastBuildGuid),
            status: Build.Status.Active
          }
        });
      }
      job.updateProgress(100);
      return {
        repoUrl,
        commitId,
        versionCode,
        lastBuildGuid
      };
    } else {
      job.log('Starting build with CodeBuild');
      const script = (await readFile('scripts/appbuilders_s3_build.yml')).toString();
      job.updateProgress(50);
      // Start the build
      const codeBuild = new CodeBuild();
      const commitId = ''; // TODO: Remove when git is removed
      const versionCode = await getVersionCode(build.job); // Is there a reason this is not incremented here??
      const lastBuildGuid = await codeBuild.startBuild(
        gitUrl,
        commitId,
        build,
        script,
        versionCode,
        codeCommitProject
      );
      job.updateProgress(75);
      if (lastBuildGuid) {
        await prisma.build.update({
          where: { id: build.id },
          data: {
            build_guid: lastBuildGuid,
            codebuild_url: CodeBuild.getCodeBuildUrl('build_app', lastBuildGuid),
            console_text_url: CodeBuild.getConsoleTextUrl('build_app', lastBuildGuid),
            status: Build.Status.Active
          }
        });
      }
      job.updateProgress(100);
      return {
        versionCode,
        lastBuildGuid
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

export async function postProcess(job: Job<BullMQ.Build.PostProcess>): Promise<unknown> {
  return;
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
