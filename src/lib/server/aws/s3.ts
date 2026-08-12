import {
  CopyObjectCommand,
  DeleteObjectsCommand,
  GetObjectCommand,
  HeadObjectCommand,
  ListObjectsV2Command,
  NoSuchKey,
  PutObjectCommand,
  S3Client,
  S3ServiceException,
  type _Object
} from '@aws-sdk/client-s3';
import { SpanStatusCode, trace } from '@opentelemetry/api';
import { basename, dirname, extname } from 'node:path';
import { S3SyncClient } from 's3-sync-client';
import { AWSVars } from './vars';
import { env } from '$env/dynamic/private';
import {
  type ProviderForArtifacts,
  beginArtifacts,
  handleArtifact
} from '$lib/server/models/artifact-handle';
import {
  type BuildForPrefix,
  type ProviderForPrefix,
  getBasePrefixUrl
} from '$lib/server/models/artifacts';

export class S3 {
  public s3Client;
  public constructor() {
    this.s3Client = new S3Client({
      region: AWSVars.artifactsRegion()
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
    return `arn:aws:s3:::${AWSVars.artifacts()}/${getBasePrefixUrl(build, productStage)}/${filename ?? ''}`;
  }
  /**
   * This reads a file from the build output
   *
   * @param Build build Current build object
   * @param string fileName Name of the file without path
   * @return string Contains the contents of the file
   */
  public async readS3File(
    artifacts_provider: ProviderForPrefix,
    fileName: string,
    errorIfNotExists = true
  ) {
    let fileContents = '';
    const bucket = AWSVars.artifacts();
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
      const span = trace.getActiveSpan();
      span?.recordException(e as Error);
      // There is not a good way to check for file exists.  If file doesn't exist,
      // it will be caught here and an empty string returned.
      if (e instanceof NoSuchKey) {
        if (errorIfNotExists) {
          span?.setStatus({
            code: SpanStatusCode.ERROR,
            message: `Error from S3 while getting object "${filePath}" from "${bucket}". No such key exists.`
          });
        }
      } else if (e instanceof S3ServiceException) {
        span?.setStatus({
          code: SpanStatusCode.ERROR,
          message: `Error from S3 while getting object from ${bucket}.  ${e.name}: ${e.message}`
        });
      } else {
        span?.setStatus({
          code: SpanStatusCode.ERROR,
          message: (e as Error).message
        });
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
    const span = trace.getActiveSpan();
    const artifactsBucket = AWSVars.artifacts();
    const destFolderPrefix = getBasePrefixUrl(artifacts_provider, AWSVars.appEnv());
    const sourcePrefix = getBasePrefixUrl(artifacts_provider, 'codebuild-output') + '/';
    const destPrefix = destFolderPrefix + '/';
    beginArtifacts(artifacts_provider, `https://${artifactsBucket}.s3.amazonaws.com/${destPrefix}`);
    span?.addEvent(`S3 - Copy Folder`, {
      's3.bucket': artifactsBucket,
      's3.source': sourcePrefix,
      's3.dest': destPrefix
    });
    try {
      await this.deleteMatchingObjects(artifactsBucket, destFolderPrefix);
    } catch (e) {
      span?.recordException(e as Error);
      span?.setStatus({
        code: SpanStatusCode.ERROR,
        message: (e as Error).message
      });
      if (!(e instanceof S3ServiceException)) {
        throw e;
      }
    }
    const result = await this.s3Client.send(
      new ListObjectsV2Command({
        Bucket: artifactsBucket,
        Prefix: sourcePrefix
      })
    );
    (
      await Promise.all(
        result.Contents?.map((file) =>
          this.copyS3File(file, sourcePrefix, destPrefix, artifacts_provider)
        ) ?? []
      )
    )
      .filter((r) => !!r)
      .forEach((r) => handleArtifact(artifacts_provider, r.file, r.contents));
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
  private async copyS3File(
    file: _Object,
    sourcePrefix: string,
    destPrefix: string,
    artifacts_provider: ProviderForPrefix & ProviderForArtifacts
  ) {
    const span = trace.getActiveSpan();
    const artifactsBucket = AWSVars.artifacts();
    let fileContents = '';
    const fileNameWithPrefix = file['Key']!;
    const fileName = fileNameWithPrefix.substring(sourcePrefix.length);
    span?.addEvent(`S3 - Copy File`, {
      's3.bucket': artifactsBucket,
      's3.source': sourcePrefix,
      's3.file': fileName
    });
    switch (fileName) {
      case 'manifest.txt':
      case 'play-listing/default-language.txt':
        return;
      //case: 'version.json': FUTURE: get versionCode from version.json
      case 'version_code.txt':
        fileContents = await this.readS3File(artifacts_provider, fileName);
        break;
    }
    const sourceDir = dirname(fileNameWithPrefix);
    const sourceBasename = basename(fileNameWithPrefix);
    const sourceFile = artifactsBucket + '/' + sourceDir + '/' + encodeURI(sourceBasename);
    const destinationFile = destPrefix + fileName;
    try {
      await this.s3Client.send(
        new CopyObjectCommand({
          Bucket: artifactsBucket,
          CopySource: sourceFile,
          Key: destinationFile,
          ACL: 'public-read',
          ContentType: this.getFileType(fileName),
          MetadataDirective: 'REPLACE'
        })
      );
      return { file: fileName, contents: fileContents };
    } catch (e) {
      span?.recordException(e as Error);
      if (e instanceof Error) {
        span?.setStatus({
          code: SpanStatusCode.ERROR,
          message: `File was not renamed ${sourceFile}\nexception: ${e.message}`
        });
      } else {
        span?.setStatus({
          code: SpanStatusCode.ERROR,
          message: (e as Error).message
        });
        throw e;
      }
    }
  }

  public async writeFileToS3(
    fileContents: string,
    fileName: string,
    artifacts_provider: ProviderForPrefix & ProviderForArtifacts
  ) {
    const fileS3Bucket = AWSVars.artifacts();
    const destPrefix: string = getBasePrefixUrl(artifacts_provider, AWSVars.appEnv());
    const fileS3Key = destPrefix + '/' + fileName;

    trace.getActiveSpan()?.addEvent(`S3 - Write File`, {
      's3.bucket': fileS3Bucket,
      's3.dest': destPrefix,
      's3.file': fileName
    });

    await this.s3Client.send(
      new PutObjectCommand({
        Bucket: fileS3Bucket,
        Key: fileS3Key,
        Body: fileContents,
        ACL: 'public-read',
        ContentType: this.getFileType(fileName)
      })
    );
    handleArtifact(artifacts_provider, fileS3Key, fileContents);
  }

  public async objectExists(key: string) {
    const bucket = AWSVars.artifacts();
    try {
      await this.s3Client.send(
        new HeadObjectCommand({
          Bucket: bucket,
          Key: key
        })
      );
      return true;
    } catch (e) {
      if (e instanceof S3ServiceException && e.$metadata.httpStatusCode === 404) {
        return false;
      }
      throw e;
    }
  }

  public async removeCodeBuildFolder(artifacts_provider: ProviderForPrefix) {
    const s3Folder = getBasePrefixUrl(artifacts_provider, 'codebuild-output') + '/';
    const s3Bucket = AWSVars.artifacts();
    trace.getActiveSpan()?.addEvent(`S3 - Remove CodeBuild Folder`, {
      's3.bucket': s3Bucket,
      's3.folder': s3Folder
    });
    return await this.deleteMatchingObjects(s3Bucket, s3Folder);
  }

  public async uploadFolder(folderName: string, bucket: string) {
    trace.getActiveSpan()?.addEvent(`S3 - Upload Folder`, {
      's3.bucket': bucket,
      's3.folder': folderName
    });
    const client = new S3SyncClient({
      client: new S3Client({
        region: AWSVars.artifactsRegion(),
        credentials: {
          accessKeyId: env.AWS_ACCESS_KEY_ID,
          secretAccessKey: env.AWS_SECRET_ACCESS_KEY
        }
      })
    });
    return await client.sync(folderName, bucket);
  }

  private getFileType(fileName: string) {
    switch (extname(fileName)) {
      case '.html':
        return 'text/html';
      case '.png':
        return 'image/png';
      case '.jpg':
      case '.jpeg':
        return 'image/jpeg';
      case '.txt':
      case '.log':
        return 'text/plain';
      case '.json':
        return 'application/json';
      default:
        return 'application/octet-stream';
    }
  }

  private async deleteMatchingObjects(Bucket: string, Prefix: string) {
    trace.getActiveSpan()?.addEvent(`S3 - Delete Objects`, {
      's3.bucket': Bucket,
      's3.prefix': Prefix
    });
    // If destination folder already exists from some previous build, delete
    const existing = await this.s3Client.send(
      new ListObjectsV2Command({
        Bucket,
        Prefix
      })
    );
    if (existing.Contents) {
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
}
