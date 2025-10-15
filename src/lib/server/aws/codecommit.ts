import {
  CodeCommitClient,
  GetBranchCommand,
  GetRepositoryCommand
} from '@aws-sdk/client-codecommit';
import { AWSCommon } from './common';
import { Utils } from '$lib/server/utils';

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
    return new CodeCommitClient({
      region: AWSCommon.getArtifactsBucketRegion()
    });
  }
  /**
   * Returns http url of code commit archive derived from git url needed for CodeBuild
   *
   * @param string git_url
   * @return string http codecommit url
   */
  public async getSourceURL(git_url: string) {
    console.log(`[${Utils.getPrefix()}] getSourceURL URL: ${git_url}`);
    const repo = git_url.substring(git_url.indexOf('/') + 1);
    const repoInfo = await this.codeCommitClient.send(
      new GetRepositoryCommand({
        repositoryName: repo
      })
    );
    const cloneUrl = repoInfo.repositoryMetadata?.cloneUrlHttp;
    console.log(`cloneUrl: ${cloneUrl}`);
    return cloneUrl;
  }
  /**
   * Return ssh url of code commit archive derived from git url needed for CodeBuild
   *
   * @param string git_url
   * @return string http codecommit url
   */
  public async getSourceSshURL(git_url: string) {
    console.log(`[${Utils.getPrefix()}] getSourceURL URL: ${git_url}`);
    const repo = git_url.substring(git_url.indexOf('/') + 1);
    const repoInfo = await this.codeCommitClient.send(
      new GetRepositoryCommand({
        repositoryName: repo
      })
    );
    const cloneUrl = repoInfo.repositoryMetadata?.cloneUrlSsh;
    console.log(`cloneUrl: ${cloneUrl}`);
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
    console.log(`[${Utils.getPrefix()}] getCommitId URL: ${git_url} Branch: ${branch}`);
    const repo = git_url.substring(git_url.indexOf('/') + 1);
    const result = await this.codeCommitClient.send(
      new GetBranchCommand({
        branchName: branch,
        repositoryName: repo
      })
    );
    const commitId = result.branch?.commitId;
    console.log(`commitId: ${commitId}`);
    return commitId;
  }
}
