import {
  CodeCommitClient,
  GetBranchCommand,
  GetRepositoryCommand
} from '@aws-sdk/client-codecommit';
import { SpanStatusCode, trace } from '@opentelemetry/api';
import { AWSCommon } from './common';

export class CodeCommit extends AWSCommon {
  public codeCommitClient;

  public constructor() {
    super();
    this.codeCommitClient = CodeCommit.getCodeCommitClient();
  }
  /**
   * Configure and get the CodeCommit Client
   * @return \Aws\CodeBuild\CodeCommitClient
   */
  public static getCodeCommitClient() {
    const span = trace.getActiveSpan();
    let client: CodeCommitClient | null = null;
    try {
      client = new CodeCommitClient({
        region: AWSCommon.getArtifactsBucketRegion()
      });
    } catch (e) {
      span?.recordException(e as Error);
      span?.setStatus({
        code: SpanStatusCode.ERROR,
        message: (e as Error).message
      });
    } finally {
      span?.addEvent('CodeCommit - getCodeCommitClient', {
        'code-commit.client.api-version': client?.config.apiVersion,
        'code-commit.client.service-id': client?.config.serviceId
      });
    }
    return client!;
  }
  /**
   * Returns http url of code commit archive derived from git url needed for CodeBuild
   *
   * @param string git_url
   * @return string http codecommit url
   */
  public async getSourceURL(git_url: string) {
    const span = trace.getActiveSpan();
    let cloneUrl: string | undefined = undefined;
    try {
      const repo = git_url.substring(git_url.indexOf('/') + 1);
      const repoInfo = await this.codeCommitClient.send(
        new GetRepositoryCommand({
          repositoryName: repo
        })
      );
      cloneUrl = repoInfo.repositoryMetadata?.cloneUrlHttp;
    } catch (e) {
      span?.recordException(e as Error);
      span?.setStatus({
        code: SpanStatusCode.ERROR,
        message: (e as Error).message
      });
    } finally {
      span?.addEvent('CodeCommit - getSourceURL', {
        'code-commit.git-url': git_url,
        'code-commit.source-url': cloneUrl ?? ''
      });
    }
    return cloneUrl;
  }
  /**
   * Return ssh url of code commit archive derived from git url needed for CodeBuild
   *
   * @param string git_url
   * @return string http codecommit url
   */
  public async getSourceSshURL(git_url: string) {
    const span = trace.getActiveSpan();
    let cloneUrl: string | undefined = undefined;
    try {
      const repo = git_url.substring(git_url.indexOf('/') + 1);
      const repoInfo = await this.codeCommitClient.send(
        new GetRepositoryCommand({
          repositoryName: repo
        })
      );
      cloneUrl = repoInfo.repositoryMetadata?.cloneUrlSsh;
    } catch (e) {
      span?.recordException(e as Error);
      span?.setStatus({
        code: SpanStatusCode.ERROR,
        message: (e as Error).message
      });
    } finally {
      span?.addEvent('CodeCommit - getSourceSshURL', {
        'code-commit.git-url': git_url,
        'code-commit.source-ssh-url': cloneUrl ?? ''
      });
    }
    return cloneUrl;
  }

  /**
   *  Returns commit id of the specified branch for the specified repo
   *
   * @param string git_url
   * @param string branch
   * @return string commit id
   */
  public async getCommitId(git_url: string, branch: string) {
    const span = trace.getActiveSpan();
    let commitId: string | undefined = undefined;
    try {
      const repo = git_url.substring(git_url.indexOf('/') + 1);
      const result = await this.codeCommitClient.send(
        new GetBranchCommand({
          branchName: branch,
          repositoryName: repo
        })
      );
      commitId = result.branch?.commitId;
    } catch (e) {
      span?.recordException(e as Error);
      span?.setStatus({
        code: SpanStatusCode.ERROR,
        message: (e as Error).message
      });
    } finally {
      span?.addEvent('CodeCommit - getCommitId', {
        'code-commit.git-url': git_url,
        'code-commit.commit-id': commitId ?? ''
      });
    }
    return commitId;
  }
}
