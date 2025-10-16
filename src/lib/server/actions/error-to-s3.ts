import { S3 } from '../aws/s3';
import { Build } from '../models/build';
import { Release } from '../models/release';
import { prisma } from '../prisma';
import { Utils } from '../utils';

export class CopyErrorToS3Operation {
  private id;
  private parms;
  private maxRetries = 50;
  private maxDelay = 30;
  private alertAfter = 5;

  public constructor(id: number, parms: string) {
    this.id = id;
    this.parms = parms;
  }
  public async performOperation() {
    console.log(`[${Utils.getPrefix()}] CopyErrorToS3Operation ID: ${this.id}`);
    if (this.parms === 'release') {
      const release = await prisma.release.findUnique({ where: { id: this.id } });
      if (release) {
        const s3 = new S3();
        await s3.copyS3Folder(release);
        await prisma.release.update({
          where: { id: this.id },
          data: { status: Release.Status.Completed }
        });
      }
    } else {
      const build = await prisma.build.findUnique({ where: { id: this.id } });
      if (build) {
        const s3 = new S3();
        await s3.copyS3Folder(build);
        await prisma.build.update({
          where: { id: this.id },
          data: { status: Build.Status.Completed }
        });
      }
    }
  }
  public getMaximumRetries() {
    return this.maxRetries;
  }
  public getMaximumDelay() {
    return this.maxDelay;
  }
  public getAlertAfterAttemptCount() {
    return this.alertAfter;
  }
}
