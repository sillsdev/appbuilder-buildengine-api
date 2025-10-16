import type { Prisma } from '@prisma/client';
import { readFile } from 'node:fs/promises';
import { type BuildForCodeBuild, CodeBuild } from '../aws/codebuild';
import { CodeCommit } from '../aws/codecommit';
import { Build } from '../models/build';
import { prisma } from '../prisma';
import { Utils } from '../utils';

export class ManageBuildsAction {
  public performAction() {
    const prefix = Utils.getPrefix();
    console.log(`[${prefix}] ManageBuilds Action start`);
    // for all builds where status != complete
    // if initialized, try start build
    // else check status
  }

  /**
   * Try to start a build.  If it starts, then update the database.
   * @param Build build
   */
  private async tryStartBuild(
    build: BuildForCodeBuild & {
      job: Prisma.jobGetPayload<{ select: { existing_version_code: true; git_url: true } }>;
    }
  ) {
    try {
      const prefix = Utils.getPrefix();
      console.log(`[${prefix}] tryStartBuild: Starting Build of ${build.jobName()}`);

      // Find the repo and commit id to be built
      const job = build.job;

      // Don't start job if a job for this build is currently running
      const builds = Build.findAllRunningByJobId(job.id);
      if (builds.length > 0) {
        console.log('...is currentlyBuilding so wait');
        return;
      }
      const gitUrl = job.git_url;
      // Check to see if codebuild project
      const codeCommitProject = gitUrl.startsWith('ssh://');
      if (codeCommitProject) {
        // Left this block intact to make it easier to remove when codecommit is not supported
        const codecommit = new CodeCommit();
        const branch = 'master';
        const repoUrl = await codecommit.getSourceURL(gitUrl);
        const commitId = await codecommit.getCommitId(gitUrl, branch);

        const script = (await readFile('scripts/appbuilders_build.yml')).toString();
        // Start the build
        const codeBuild = new CodeBuild();
        const versionCode = (await this.getVersionCode(job)) + 1;
        const lastBuildGuid = await codeBuild.startBuild(
          repoUrl,
          commitId,
          build,
          script,
          versionCode,
          codeCommitProject
        );
        if (lastBuildGuid) {
          console.log(`[${prefix}] Launched Build LastBuildNumber=${lastBuildGuid}`);
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
      } else {
        const script = (await readFile('scripts/appbuilders_s3_build.yml')).toString();
        // Start the build
        const codeBuild = new CodeBuild();
        const commitId = ''; // TODO: Remove when git is removed
        const versionCode = await this.getVersionCode(job);
        const lastBuildGuid = await codeBuild.startBuild(
          gitUrl,
          commitId,
          build,
          script,
          versionCode,
          codeCommitProject
        );
        if (lastBuildGuid) {
          console.log(`[${prefix}] Launched Build LastBuildNumber=${lastBuildGuid}`);
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
      }
    } catch (e) {
      console.log(`[${Utils.getPrefix()}] tryStartBuild: Exception:${e}`);
      this.failBuild(build.id);
    }
  }

  /**
   *
   * @param Build build
   */
  private async checkBuildStatus(
    build: Prisma.buildGetPayload<{
      select: {
        id: true;
        build_guid: true;
        status: true;
        result: true;
        job: { select: { id: true } };
      };
    }>
  ) {
    try {
      const prefix = Utils.getPrefix();
      console.log(`[${prefix}] checkBuildStatus: Check Build of ${build.jobName()}`);

      const job = build.job;
      if (job) {
        const codeBuild = new CodeBuild();
        const buildStatus = await codeBuild.getBuildStatus(
          build.build_guid!,
          CodeBuild.getCodeBuildProjectName('build_app')
        );
        const phase = buildStatus?.currentPhase;
        let status = buildStatus?.buildStatus;
        console.log(` phase: ${phase} status: ${status}`);
        if (codeBuild.isBuildComplete(buildStatus)) {
          console.log(' Build Complete');
        } else {
          console.log(' Build Incomplete');
        }

        let savedStatus = build.status;
        let savedResult = build.result;

        if (codeBuild.isBuildComplete(buildStatus)) {
          savedStatus = Build.Status.PostProcessing;
          status = codeBuild.getStatus(buildStatus);
          switch (status) {
            case CodeBuild.Status.Failed:
            case CodeBuild.Status.Fault:
            case CodeBuild.Status.TimedOut:
              savedResult = Build.Result.Failure;
              this.handleFailure(build);
              break;
            case CodeBuild.Status.Stopped:
              savedResult = Build.Result.Aborted;
              this.handleFailure(build);
              break;
            case CodeBuild.Status.Succeeded:
              OperationQueue.findOrCreate(OperationQueue.SAVETOS3, build.id, 'build');
              break;
          }
        }
        const saved = await prisma.build.update({
          where: { id: build.id },
          data: { status: savedStatus, result: savedResult }
        });
        if (!saved) {
          throw new Error(
            `Unable to update Build entry, model errors: ${JSON.stringify(build.getFirstErrors(), null, 4)}`
          );
        }
        const log = Build.getlogBuildDetails(build);
        log['job id'] = job.id;
        console.log(
          `Job=${job.id}, Build=${build.build_guid}, Status=${saved.status}, Result=${saved.result}`
        );
      }
    } catch (e) {
      console.log(`[${Utils.getPrefix()}] checkBuildStatus: Exception:${e}`);
      this.failBuild(build.id);
    }
  }

  private handleFailure(build: unknown) {
    build.error = build.cloudWatch();
    const build_id = build.id;
    const task = OperationQueue.SAVEERRORTOS3;
    OperationQueue.findOrCreate(task, build_id, 'build');
  }
  private async getVersionCode(
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
  private async failBuild(id: number) {
    try {
      await prisma.build.update({
        where: { id },
        data: {
          result: Build.Result.Failure,
          status: Build.Status.Completed
        }
      });
    } catch (e) {
      console.log(`[${Utils.getPrefix()}] failBuild Exception: ${e}`);
    }
  }
}
