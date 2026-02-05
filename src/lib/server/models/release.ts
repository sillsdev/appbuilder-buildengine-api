import type { Prisma } from '@prisma/client';
import { basename, extname } from 'node:path';
import { getArtifactUrl } from './artifacts';

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

  export function artifacts(
    release: Prisma.releaseGetPayload<{
      select: {
        artifact_url_base: true;
        console_text_url: true;
        artifact_files: true;
      };
    }>
  ) {
    const { artifact_url_base: base, artifact_files: files } = release;
    return {
      [Release.Artifact.CloudWatch]: release.console_text_url,
      [Release.Artifact.ConsoleText]: getArtifactUrl(/console\.log/, base, files),
      [Release.Artifact.PublishUrl]: getArtifactUrl(/publish_url\.txt/, base, files)
    } as Record<string, string | undefined>;
  }
}
