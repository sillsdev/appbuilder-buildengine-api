import {
  CopyObjectCommand,
  DeleteObjectsCommand,
  GetObjectCommand,
  ListObjectsV2Command,
  NoSuchKey,
  PutObjectCommand,
  S3Client,
  S3ServiceException,
  type _Object
} from '@aws-sdk/client-s3';
import { basename, dirname, extname } from 'node:path';
import { S3SyncClient } from 's3-sync-client';
import { AWSCommon } from './common';
import { env } from '$env/dynamic/private';
import {
  type BuildForPrefix,
  type ProviderForArtifacts,
  type ProviderForPrefix,
  beginArtifacts,
  getBasePrefixUrl,
  handleArtifact
} from '$lib/models/artifacts';
import { Utils } from '$lib/server/utils';

export class S3 extends AWSCommon {
  public s3Client;
  public constructor() {
    super();
    this.s3Client = S3.getS3Client();
  }

  /**
   * Configure and get the S3 Client
   * @return \Aws\S3\S3Client
   */
  public static getS3Client() {
    return new S3Client({
      region: AWSCommon.getArtifactsBucketRegion()
    });
  }
  public getS3ClientWithCredentials() {
    return new S3Client({
      region: AWSCommon.getArtifactsBucketRegion(),
      credentials: {
        accessKeyId: env.AWS_ACCESS_KEY_ID,
        secretAccessKey: env.AWS_SECRET_ACCESS_KEY
      }
    });
  }

  /**
   * gets the s3 arn of a specific file
   *
   * @param Build build Current build object
   * @param string productStage - stg or prd
   * @param string filename - Name of s3 file that arn is requested for
   * @return string prefix
   */
  public static getS3Arn(build: BuildForPrefix, productStage: string, filename: string | null) {
    return `arn:aws:s3:::${S3.getArtifactsBucket()}/${getBasePrefixUrl(build, productStage)}/${filename ?? ''}`;
  }
  /**
   * This reads a file from the build output
   *
   * @param Build build Current build object
   * @param string fileName Name of the file without path
   * @return string Contains the contents of the file
   */
  public async readS3File(artifacts_provider: ProviderForPrefix, fileName: string) {
    let fileContents = '';
    const bucket = S3.getArtifactsBucket();
    const filePath = getBasePrefixUrl(artifacts_provider, 'codebuild-output') + '/' + fileName;
    try {
      const result = await this.s3Client.send(
        new GetObjectCommand({
          Bucket: bucket,
          Key: filePath
        })
      );
      fileContents = await result.Body!.transformToString();
    } catch (e) {
      // There is not a good way to check for file exists.  If file doesn't exist,
      // it will be caught here and an empty string returned.
      if (e instanceof NoSuchKey) {
        console.error(
          `Error from S3 while getting object "${filePath}" from "${bucket}". No such key exists.`
        );
      } else if (e instanceof S3ServiceException) {
        console.error(
          `Error from S3 while getting object from ${bucket}.  ${e.name}: ${e.message}`
        );
      } else {
        throw e;
      }
    }
    return fileContents;
  }

  /**
   * copyS3BuildFolder copies the files from where they have been saved encrypted in s3 by codebuild
   * to the final unencrypted artifacts folder.
   * NOTE: This move is required because the initial codebuild version encrypts the files
   * with a key and there is no option to build them without encryption.
   *
   * @param Build or Release artifacts_provider - The build or release
   */
  public async copyS3Folder(artifacts_provider: ProviderForPrefix & ProviderForArtifacts) {
    const artifactsBucket = S3.getArtifactsBucket();
    const sourcePrefix = getBasePrefixUrl(artifacts_provider, 'codebuild-output') + '/';
    const destPrefix = getBasePrefixUrl(artifacts_provider, S3.getAppEnv()) + '/';
    const publicBaseUrl = `https://${artifactsBucket}.s3.amazonaws.com/${destPrefix}`;
    beginArtifacts(artifacts_provider, publicBaseUrl);
    const destFolderPrefix = getBasePrefixUrl(artifacts_provider, S3.getAppEnv());
    try {
      await this.deleteMatchingObjects(artifactsBucket, destFolderPrefix);
    } catch (e) {
      if (e instanceof S3ServiceException) {
        console.error(`[${[Utils.getPrefix()]}] copyS3Build
            Folder: Exception:\n${e}`);
      } else {
        throw e;
      }
    }
    const result = await this.s3Client.send(
      new ListObjectsV2Command({
        Bucket: artifactsBucket,
        Prefix: sourcePrefix
      })
    );
    result.Contents?.forEach((file) =>
      this.copyS3File(file, sourcePrefix, destPrefix, artifacts_provider)
    );
  }

  /**
   * This method copies a single file from the encrypted source archive to
   * the unencrypted destination archive
   *
   * @param AWS/File file - AWS object for source file
   * @param string sourcePrefix - The AWS path to the source folder
   * @param string destPrefix - The AWS path to the destination folder
   * @param ArtifactsProvider artifacts_provider - Successful build associated with the copy
   */
  public async copyS3File(
    file: _Object,
    sourcePrefix: string,
    destPrefix: string,
    artifacts_provider: ProviderForPrefix & ProviderForArtifacts
  ) {
    const artifactsBucket = S3.getArtifactsBucket();
    let fileContents = '';
    const fileNameWithPrefix = file['Key']!;
    const fileName = fileNameWithPrefix.substring(sourcePrefix.length);
    switch (fileName) {
      case 'manifest.txt':
        return;
      case 'play-listing/default-language.txt':
        return;
      //case: 'version.json': FUTURE: get versionCode from version.json
      case 'version_code.txt':
        fileContents = await this.readS3File(artifacts_provider, fileName);
        break;
      default:
        console.log(fileName);
        break;
    }
    const sourceDir = dirname(fileNameWithPrefix);
    const sourceBasename = basename(fileNameWithPrefix);
    const sourceFile = artifactsBucket + '/' + sourceDir + '/' + encodeURI(sourceBasename);
    const destinationFile = destPrefix + fileName;
    try {
      const ret = await this.s3Client.send(
        new CopyObjectCommand({
          Bucket: artifactsBucket,
          CopySource: sourceFile,
          Key: destinationFile,
          ACL: 'public-read',
          ContentType: this.getFileType(fileName),
          MetadataDirective: 'REPLACE'
        })
      );
      if ('build' in artifacts_provider) {
        handleArtifact(artifacts_provider, destinationFile, fileContents);
      } else {
        handleArtifact(artifacts_provider, destinationFile);
      }
      return ret;
    } catch (e) {
      if (e instanceof Error) {
        console.error(`File was not renamed ${sourceFile}\nexception: ${e.stack}`);
      } else {
        throw e;
      }
    }
  }

  public async writeFileToS3(
    fileContents: string,
    fileName: string,
    artifacts_provider: ProviderForPrefix & ProviderForArtifacts
  ) {
    const fileS3Bucket = S3.getArtifactsBucket();
    const destPrefix: string = getBasePrefixUrl(artifacts_provider, S3.getAppEnv());
    const fileS3Key = destPrefix + '/' + fileName;

    await this.s3Client.send(
      new PutObjectCommand({
        Bucket: fileS3Bucket,
        Key: fileS3Key,
        Body: fileContents,
        ACL: 'public-read',
        ContentType: this.getFileType(fileName)
      })
    );

    if ('build' in artifacts_provider) {
      handleArtifact(artifacts_provider, fileS3Key, fileContents);
    } else {
      handleArtifact(artifacts_provider, fileS3Key);
    }
  }
  public async removeCodeBuildFolder(artifacts_provider: ProviderForPrefix) {
    const s3Folder = getBasePrefixUrl(artifacts_provider, 'codebuild-output') + '/';
    const s3Bucket = S3.getArtifactsBucket();
    console.log(`Deleting S3 bucket: ${s3Bucket} key: ${s3Folder}`);
    return this.deleteMatchingObjects(s3Bucket, s3Folder);
  }

  public async uploadFolder(folderName: string, bucket: string) {
    const client = new S3SyncClient({ client: this.getS3ClientWithCredentials() });
    return await client.sync(folderName, bucket);
  }

  private getFileType(fileName: string) {
    switch (extname(fileName)) {
      case 'html':
        return 'text/html';
      case 'png':
        return 'image/png';
      case 'jpg':
      case 'jpeg':
        return 'image/jpeg';
      case 'txt':
      case 'log':
        return 'text/plain';
      case 'json':
        return 'application/json';
      default:
        return 'application/octet-stream';
    }
  }

  private async deleteMatchingObjects(Bucket: string, Prefix: string) {
    // If destination folder already exists from some previous build, delete
    const existing = await this.s3Client.send(
      new ListObjectsV2Command({
        Bucket,
        Prefix
      })
    );
    return await this.s3Client.send(
      new DeleteObjectsCommand({
        Bucket,
        Delete: {
          Objects: existing.Contents?.map(({ Key }) => ({ Key }))
        }
      })
    );
  }
}
