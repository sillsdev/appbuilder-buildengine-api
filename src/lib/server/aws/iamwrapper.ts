import { GetRoleCommand, IAMClient } from '@aws-sdk/client-iam';
import { trace } from '@opentelemetry/api';
import { AWSVars } from './vars';

export class IAmWrapper {
  public iamClient;
  public constructor() {
    this.iamClient = new IAMClient({ region: AWSVars.region() });
  }

  /**
   * This method returns the role arn
   * @param string projectName - base project name, i + e + build_app or publish_app
   * @return string arn for role
   */
  public async getRoleArn(projectName: string) {
    try {
      const fullRoleName = AWSVars.roleName(projectName);
      const result = await this.iamClient.send(
        new GetRoleCommand({
          RoleName: fullRoleName
        })
      );
      const roleArn = result.Role?.Arn ?? '';
      trace.getActiveSpan()?.setAttribute('iam.role-arn', roleArn);
      return roleArn;
    } catch {
      return '';
    }
  }
}
