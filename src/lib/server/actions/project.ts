import type { Prisma } from '@prisma/client';
import { IAmWrapper } from '../aws/iamwrapper';
import { prisma } from '../prisma';
import { Utils } from '../utils';
import { Project } from '$lib/models/project';

export class ProjectUpdateOperation {
  private id;
  private parms;
  public iAmWrapper; // Public for ut

  public constructor(id: number, parms: string) {
    this.id = id;
    this.parms = parms;
    this.iAmWrapper = new IAmWrapper();
  }
  public async performOperation() {
    console.log(`[${Utils.getPrefix()}] ProjectUpdateOperation ID: ${this.id}`);
    const project = await prisma.project.findUnique({
      where: { id: this.id },
      include: { client: true }
    });
    if (project) {
      console.log('Found record');
      const parmsArray = this.parms.split(',');
      const publishing_key = parmsArray[0];
      const user_id = parmsArray[1];
      this.checkRemoveUserFromGroup(project);
      this.updateProject(project, user_id, publishing_key);
    } else {
      console.log("Didn't find record");
    }
  }
  /**
   * If the user/group combination associated with the current project is
   * the only project that exists, then remove the IAM user from the IAM group
   *
   * @param Project project
   * @return void
   */
  private async checkRemoveUserFromGroup(
    project: Prisma.projectGetPayload<{ select: { user_id: true } }> & Project.ProjectGroup
  ) {
    console.log('checkRemoveUserFromGroup');
    const projects = await prisma.project.count({
      where: { user_id: project.user_id, group_id: project.group_id }
    });
    if (projects < 2) {
      // Remove the user from the group
      console.log(
        `CheckRemoveUserFromGroup: Removing [${project.user_id}] from group [${Project.groupName(project)}]`
      );
      this.iAmWrapper.removeUserFromIamGroup(project.user_id!, Project.groupName(project));
    }
  }
  private async updateProject(
    project: Prisma.projectGetPayload<{ select: { id: true; url: true } }> & Project.ProjectGroup,
    user_id: string,
    publishing_key: string
  ) {
    console.log('updateProject');
    await this.iAmWrapper.createAwsAccount(user_id);
    this.iAmWrapper.addUserToIamGroup(user_id, Project.groupName(project));
    const public_key = await this.iAmWrapper.addPublicSshKey(user_id, publishing_key);
    const publicKeyId = public_key?.SSHPublicKey?.SSHPublicKeyId;
    const url = this.adjustUrl(project.url!, publicKeyId!);
    await prisma.project.update({
      where: { id: project.id },
      data: {
        user_id,
        publishing_key,
        url
      }
    });
  }
  private adjustUrl(url: string, newPublicKeyId: string) {
    const oldPublicKeyId = url.split('@')[0];
    return url.replace(oldPublicKeyId, 'ssh://' + newPublicKeyId);
  }
}
