import type { Prisma } from '@prisma/client';
import { readFile } from 'node:fs/promises';
import { CodeBuild, type ReleaseForCodeBuild } from '../aws/codebuild';
import { Build } from '../../models/build';
import { Release } from '../../models/release';
import { prisma } from '../prisma';
import { Utils } from '../utils';

export class ManageReleasesAction {
  public performAction() {
    // for all releases where status is not complete
    // if initialized, try start release
    // if active, chech status
  }
  /*===============================================  logging ============================================*/
  /**
   *
   * get release details for logging +
   * @param Release release
   * @return Array
   */
  public getlogReleaseDetails(
    release: Prisma.releaseGetPayload<{
      select: {
        id: true;
        status: true;
        build_guid: true;
        result: true;
        build: { select: { job: { select: { id: true } } } };
      };
    }>
  ) {
    const build = release.build;
    const job = build.job;

    const jobName = build.job.name();
    const log = {
      jobName: jobName,
      jobId: job.id,
      'Release-id': release.id,
      'Release-Status': release.status,
      'Release-Build': release.build_guid,
      'Release-Result': release.result
    };

    console.log(
      `Release=${release.id}, Build=${release.build_guid}, Status=${release.status}, Result=${release.result}`
    );

    return log;
  }
  /**
   *
   * @param Release release
   */
  private async tryStartRelease(release: ReleaseForCodeBuild) {
    try {
      const prefix = Utils.getPrefix();
      console.log(
        `[${prefix}] tryStartRelease: Starting Build of ${release.jobName()} for Channel ${release.channel}`
      );

      const script = (await readFile('scripts/appbuilders_publish.yml')).toString();

      // Start the build
      const codeBuild = new CodeBuild();
      const lastBuildGuid = await codeBuild.startRelease(release, script);
      if (lastBuildGuid) {
        console.log(`[${prefix}] Launched Build LastBuildNumber=${lastBuildGuid}`);
        await prisma.release.update({
          where: { id: release.id },
          data: {
            build_guid: lastBuildGuid,
            codebuild_url: CodeBuild.getCodeBuildUrl('publish_app', lastBuildGuid),
            console_text_url: CodeBuild.getConsoleTextUrl('publish_app', lastBuildGuid),
            status: Release.Status.Active
          }
        });
      }
    } catch (e) {
      console.log(`[${Utils.getPrefix()}] tryStartRelease: Exception:${e}`);
    }
  }
  /**
   * @param Release release
   */
  private async checkReleaseStatus(
    release: Prisma.releaseGetPayload<{
      select: {
        id: true;
        channel: true;
        build_guid: true;
        status: true;
        result: true;
        build: {
          select: {
            id: true;
            channel: true;
            job: {
              select: {
                id: true;
              };
            };
          };
        };
      };
    }>
  ) {
    try {
      const prefix = Utils.getPrefix();
      console.log(
        `[${prefix}] checkReleaseStatus: Checking Build of ${release.jobName()} for Channel ${release.channel}`
      );

      const build = release.build;
      console.log('Build id : ' + build.id);
      const job = build.job;
      if (job) {
        const codeBuild = new CodeBuild();

        const buildStatus = await codeBuild.getBuildStatus(
          release.build_guid!,
          CodeBuild.getCodeBuildProjectName('publish_app')
        );
        const phase = buildStatus?.currentPhase;
        let status = buildStatus?.buildStatus;
        console.log(` phase: ${phase} status: ${status}`);
        if (codeBuild.isBuildComplete(buildStatus)) {
          console.log(' Build Complete');
        } else {
          console.log(' Build Incomplete');
        }

        let savedStatus = release.status;
        let savedResult = release.result;

        if (codeBuild.isBuildComplete(buildStatus)) {
          savedStatus = Release.Status.PostProcessing;
          status = codeBuild.getStatus(buildStatus);
          switch (status) {
            case CodeBuild.Status.Failed:
            case CodeBuild.Status.Fault:
            case CodeBuild.Status.TimedOut:
              savedResult = Build.Result.Failure;
              this.handleFailure(release);
              break;
            case CodeBuild.Status.Stopped:
              savedResult = Build.Result.Aborted;
              this.handleFailure(release);
              break;
            case CodeBuild.Status.Succeeded:
              savedResult = Build.Result.Success;
              await prisma.build.update({
                where: { id: build.id },
                data: { channel: release.channel }
              });
              OperationQueue.findOrCreate(OperationQueue.SAVETOS3, release.id, 'release');
              break;
          }
        }
        const saved = await prisma.release.update({
          where: { id: release.id },
          data: {
            status: savedStatus,
            result: savedResult
          }
        });
        if (!saved) {
          throw new Error(
            `Unable to update Build entry, model errors: ${JSON.stringify(release.getFirstErrors(), null, 4)}`
          );
        }
      }
    } catch (e) {
      console.log(`[${Utils.getPrefix()}] checkBuildStatus: Exception:${e}`);
      this.failRelease(release.id);
    }
  }
  private handleFailure(release: Prisma.releaseGetPayload<{ select: { id: true } }>) {
    release.error = release.cloudWatch();
    const release_id = release.id;
    const task = OperationQueue.SAVEERRORTOS3;
    OperationQueue.findOrCreate(task, release_id, 'release');
  }

  private async failRelease(id: number) {
    try {
      await prisma.release.update({
        where: { id },
        data: { result: Build.Result.Failure, status: Release.Status.Completed }
      });
    } catch (e) {
      console.log(`[${Utils.getPrefix()}] failRelease Exception:${e}`);
    }
  }
}
