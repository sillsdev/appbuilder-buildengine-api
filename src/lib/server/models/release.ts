import { basename, extname } from 'node:path';

// eslint-disable-next-line @typescript-eslint/no-namespace
export namespace Release {
  export enum Status {
    Initialized = 'initialized',
    Accepted = 'accepted',
    Active = 'active',
    Expired = 'expired',
    Completed = 'completed',
    PostProcessing = 'postprocessing'
  }

  export enum Artifact {
    CloudWatch = 'cloudWatch',
    ConsoleText = 'consoleText',
    PublishUrl = 'publishUrl',
    Unknown = 'unknown'
  }

  export function artifactType(key: string): [Artifact, string] {
    const ext = extname(key);
    const file = basename(key);
    let type = Artifact.Unknown;
    if (file === 'cloudWatch') {
      type = Artifact.CloudWatch;
    } else if (ext === 'log') {
      type = Artifact.ConsoleText;
    } else if (file === 'publish_url.txt') {
      type = Artifact.PublishUrl;
    }
    return [type, file];
  }
}
