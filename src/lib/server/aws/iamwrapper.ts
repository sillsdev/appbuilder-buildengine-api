import { CodeCommitClient } from '@aws-sdk/client-codecommit';
import {
  AddUserToGroupCommand,
  CreateUserCommand,
  DuplicateSSHPublicKeyException,
  EntityAlreadyExistsException,
  GetRoleCommand,
  GetSSHPublicKeyCommand,
  GetUserCommand,
  IAMClient,
  ListSSHPublicKeysCommand,
  RemoveUserFromGroupCommand,
  UploadSSHPublicKeyCommand
} from '@aws-sdk/client-iam';
import { AWSCommon } from './common';
import { env } from '$env/dynamic/private';

export class IAmWrapper extends AWSCommon {
  public iamClient;
  public constructor() {
    super();
    this.iamClient = this.getIamClientNoCredentials();
  }
  getIamClientNoCredentials() {
    return new IAMClient({ region: AWSCommon.getAwsRegion() });
  }

  getIamClient() {
    return new IAMClient({
      region: AWSCommon.getAwsRegion(),
      credentials: {
        secretAccessKey: env.AWS_SECRET_ACCESS_KEY,
        accessKeyId: env.AWS_ACCESS_KEY_ID
      }
    });
  }
  getCodecommitClient() {
    return new CodeCommitClient({
      region: AWSCommon.getAwsRegion(),
      credentials: {
        secretAccessKey: env.AWS_SECRET_ACCESS_KEY,
        accessKeyId: env.AWS_ACCESS_KEY_ID
      }
    });
  }

  /**
   * Determines whether the role for the specified project
   * and the current production stage exists
   *
   * @param string projectName - base project name, i + e + build_app or publish_app
   * @return boolean - true if role associated with base project exists for this production stage
   */
  public async doesRoleExist(projectName: string) {
    try {
      const fullRoleName = AWSCommon.getRoleName(projectName);
      console.log(`Check role ${fullRoleName} exists`);
      await this.iamClient.send(
        new GetRoleCommand({
          RoleName: fullRoleName // REQUIRED
        })
      );
      return true;
    } catch {
      return false;
    }
  }
  /**
   * This method returns the role arn
   * @param string projectName - base project name, i + e + build_app or publish_app
   * @return string arn for role
   */
  public async getRoleArn(projectName: string) {
    try {
      const fullRoleName = AWSCommon.getRoleName(projectName);
      const result = await this.iamClient.send(
        new GetRoleCommand({
          RoleName: fullRoleName
        })
      );
      const roleArn = result.Role?.Arn ?? '';
      console.log(`Role Arn is ${roleArn}`);
      return roleArn;
    } catch {
      return '';
    }
  }

  /**
   * gets the iam arn of a specific iam policy
   *
   * @param string base policy name, e + g + s3-appbuild-secrets
   * @return string arn for policy
   */
  public static getPolicyArn(basePolicyName: string) {
    return `arn:aws:iam.${AWSCommon.getAWSUserAccount()}:policy/${basePolicyName}-${AWSCommon.getAppEnv()}`;
  }
  /**
   * gets the iam user if it exists or creates one if it does not
   *
   * @param string user_id - Project user id
   * @return User User from Iam;
   */
  public async createAwsAccount(user_id: string) {
    const iamClient = this.getIamClient();

    try {
      return iamClient.send(
        new CreateUserCommand({
          Path: '/sab-codecommit-users/',
          UserName: user_id
        })
      );
    } catch (e) {
      if (e instanceof EntityAlreadyExistsException) {
        // They already have an account - pass back their account
        return await iamClient.send(
          new GetUserCommand({
            UserName: user_id
          })
        );
      } else {
        throw e;
      }
    }
  }

  /**
   * adds a user to the specified IAM Group
   *
   * @param string userName
   * @param string groupName
   * @return IAM always returns empty array so that is what is being returned
   */
  public async addUserToIamGroup(userName: string, groupName: string) {
    const iamClient = this.getIamClient();

    return await iamClient.send(
      new AddUserToGroupCommand({
        GroupName: groupName,
        UserName: userName
      })
    );
  }
  /**
   * removes a user to the specified IAM Group
   *
   * @param string userName
   * @param string groupName
   * @return IAM always returns empty array so that is what is being returned
   */
  public async removeUserFromIamGroup(userName: string, groupName: string) {
    const iamClient = this.getIamClient();

    return await iamClient.send(
      new RemoveUserFromGroupCommand({
        GroupName: groupName,
        UserName: userName
      })
    );
  }

  /**
   * adds the public key for a user to IAM
   *
   * @param string username - The name of the user associated with the key
   * @param string publicKey - The ssh key for the user
   * @return SSHPublicKey - See AWS API documentation
   */
  public async addPublicSshKey(username: string, publicKey: string) {
    const iamClient = this.getIamClient();
    try {
      return await iamClient.send(
        new UploadSSHPublicKeyCommand({
          SSHPublicKeyBody: publicKey,
          UserName: username
        })
      );
    } catch (e) {
      if (e instanceof DuplicateSSHPublicKeyException) {
        const keysForRequester = await iamClient.send(
          new ListSSHPublicKeysCommand({
            UserName: username
          })
        );

        for (const requesterKey of keysForRequester.SSHPublicKeys ?? []) {
          const key = await iamClient.send(
            new GetSSHPublicKeyCommand({
              UserName: username,
              SSHPublicKeyId: requesterKey.SSHPublicKeyId,
              Encoding: 'SSH'
            })
          );

          if (this.isEqual(key.SSHPublicKey?.SSHPublicKeyBody ?? ' ', publicKey)) {
            return key;
          }
        }
        throw new Error(`'SAB: Unable to find a matching ssh key for user + ${e}`);
      } else {
        throw new Error(`'SAB: Unable to add ssh key to user + ${e}`);
      }
    }
  }
  /**
   * used to determine whether two openSSH formatted keys are equal (without regard to comment portion of key)
   * @param openSSHKey1 format expected:  type data comment
   * @param openSSHKey2 format expected:  type data comment
   * @return bool
   */
  private isEqual(openSSHKey1: string, openSSHKey2: string) {
    const [type1, data1] = openSSHKey1.split(' ');
    const [type2, data2] = openSSHKey2.split(' ');

    if (type1 === type2 && data1 === data2) {
      return true;
    }

    return false;
  }
}
