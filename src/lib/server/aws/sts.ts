import { GetFederationTokenCommand, STSClient } from '@aws-sdk/client-sts';
import type { Prisma } from '@prisma/client';
import { randomBytes } from 'crypto';
import { AWSCommon } from './common';

export class STS extends AWSCommon {
  public stsClient: STSClient;

  public constructor() {
    super();
    this.stsClient = STS.getStsClient();
  }

  /**
   * Configure and get the S3 Client
   * @return \Aws\Sts\StsClient
   */
  public static getStsClient() {
    // version was set by PHP code to 2011-06-15 ???
    return new STSClient({ region: AWSCommon.getArtifactsBucketRegion() });
  }

  /**
   * @param string name - name of federated user
   * @param string policy - IAM policy in json format
   * @param bool readOnly - is it readonly
   * @return array - array of credentials needed for using AWS resources
   */
  public async getFederationToken(Name: string, Policy: string, ReadOnly: boolean) {
    const result = await this.stsClient.send(new GetFederationTokenCommand({ Name, Policy }));
    return {
      ...result.Credentials,
      Region: AWSCommon.getArtifactsBucketRegion(),
      ReadOnly
    };
  }

  public async getProjectAccessToken(
    project: Prisma.projectGetPayload<{ select: { url: true } }>,
    externalId: string,
    readOnly: boolean
  ) {
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
      .replace(/[^a-zA-Z0-9_=,.@-]/, '_')}.${randomBytes(16).toString('hex')}`.substring(0, 32);
    const policy = readOnly ? STS.getReadOnlyPolicy(project) : STS.getReadWritePolicy(project);
    return await this.getFederationToken(tokenName, policy, readOnly);
  }

  /**
   * @param Project project
   * @return string
   */
  public static getReadWritePolicy(project: Prisma.projectGetPayload<{ select: { url: true } }>) {
    // Note: s3 arns cannot contain region or account id
    return STS.getPolicy(
      project,
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
              's3:PutLifeCycleConfiguration'
            ],
            Resource: ['arn:aws:s3:::BUCKET/FOLDER', 'arn:aws:s3:::BUCKET/FOLDER/*']
          }
        ]
      })
    );
  }
  /**
   * @param Project project
   * @return string
   */
  public static getReadOnlyPolicy(project: Prisma.projectGetPayload<{ select: { url: true } }>) {
    return STS.getPolicy(
      project,
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

  /**
   * @param Project project
   * @return string
   */
  public static getPolicy(
    project: Prisma.projectGetPayload<{ select: { url: true } }>,
    policy: string
  ) {
    const path = project.url!.substring(5);
    return policy
      .replace('BUCKET', path.split('/')[0])
      .replace('FOLDER', path.split('/').slice(1).join('/'));
  }
}
