import { GetFederationTokenCommand, STSClient } from '@aws-sdk/client-sts';
import type { Prisma } from '@prisma/client';
import { randomBytes } from 'crypto';
import { AWSVars } from './vars';

export class STS {
  public stsClient: STSClient;

  public constructor() {
    this.stsClient = new STSClient({ region: AWSVars.artifactsRegion() });
  }

  public async getFederationToken(Name: string, Policy: string, ReadOnly: boolean) {
    const result = await this.stsClient.send(new GetFederationTokenCommand({ Name, Policy }));
    return {
      ...result.Credentials,
      Region: AWSVars.artifactsRegion(),
      ReadOnly
    };
  }

  public async getProjectAccessToken(
    project: Prisma.projectGetPayload<{ select: { url: true } }>,
    externalId: string,
    readOnly: boolean
  ) {
    return await this.getS3AccessToken(project.url!, externalId, readOnly);
  }

  /**
   * Get a federated token scoped to the folder described by an s3:// url
   *
   * @param url s3://BUCKET/FOLDER
   * @param externalId name used to identify the token holder
   * @param readOnly whether to issue a read-only token
   */
  public async getS3AccessToken(url: string, externalId: string, readOnly: boolean) {
    // https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sts-2011-06-15.html#getfederationtoken
    // AWS limits the name:
    //   The regex used to validate this parameter is a string of characters consisting of
    //   upper- and lower-case alphanumeric characters with no spaces. You can also include
    //   underscores or any of the following characters: =,.@-
    // https://docs.aws.amazon.com/STS/latest/APIReference/API_GetFederationToken.html
    // Max of 32 characters
    const tokenName = `${externalId
      .split('|')
      .at(-1)!
      .replace(/[^a-zA-Z0-9_=,.@-]/g, '_')}.${randomBytes(16).toString('hex')}`.substring(0, 32);
    const policy = readOnly ? STS.getReadOnlyPolicy(url) : STS.getReadWritePolicy(url);
    return await this.getFederationToken(tokenName, policy, readOnly);
  }

  public static getReadWritePolicy(url: string) {
    // Note: s3 arns cannot contain region or account id
    return STS.getPolicy(
      url,
      JSON.stringify({
        Version: '2012-10-17',
        Statement: [
          {
            Effect: 'Allow',
            Action: 's3:ListBucket',
            Resource: 'arn:aws:s3:::BUCKET',
            Condition: {
              StringLike: {
                's3:prefix': ['FOLDER/', 'FOLDER/*']
              }
            }
          },
          {
            Effect: 'Allow',
            Action: [
              's3:GetObject',
              's3:PutObject',
              's3:GetObjectAcl',
              's3:PutObjectAcl',
              's3:GetObjectTagging',
              's3:PutObjectTagging',
              's3:DeleteObject',
              's3:DeleteObjectVersion',
              's3:AbortMultipartUpload',
              's3:ListMultipartUploadParts'
            ],
            Resource: ['arn:aws:s3:::BUCKET/FOLDER', 'arn:aws:s3:::BUCKET/FOLDER/*']
          }
        ]
      })
    );
  }

  public static getReadOnlyPolicy(url: string) {
    return STS.getPolicy(
      url,
      JSON.stringify({
        Version: '2012-10-17',
        Statement: [
          {
            Effect: 'Allow',
            Action: 's3:ListBucket',
            Resource: 'arn:aws:s3:::BUCKET',
            Condition: {
              StringLike: {
                's3:prefix': ['FOLDER/', 'FOLDER/*']
              }
            }
          },
          {
            Effect: 'Allow',
            Action: ['s3:GetObject', 's3:GetObjectAcl', 's3:GetObjectTagging'],
            Resource: ['arn:aws:s3:::BUCKET/FOLDER', 'arn:aws:s3:::BUCKET/FOLDER/*']
          }
        ]
      })
    );
  }

  public static getPolicy(url: string, policy: string) {
    const path = url.substring(5);
    return policy
      .replace(/BUCKET/g, path.split('/')[0])
      .replace(/FOLDER/g, path.split('/').slice(1).join('/'));
  }
}
