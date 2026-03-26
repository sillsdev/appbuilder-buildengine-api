import type { Prisma } from '@prisma/client';
import { Build } from './build';
import { Release } from './release';

export type BuildForArtifacts = Prisma.buildGetPayload<{
  select: { id: true; artifact_url_base: true; artifact_files: true; version_code: true };
}>;

export type ReleaseForArtifacts = Prisma.releaseGetPayload<{
  select: { id: true; artifact_url_base: true; artifact_files: true };
}>;

export type ProviderForArtifacts = BuildForArtifacts | ReleaseForArtifacts;

export function beginArtifacts(artifacts_provider: ProviderForArtifacts, baseUrl: string) {
  artifacts_provider.artifact_url_base = baseUrl;
  artifacts_provider.artifact_files = null;
}

export function handleArtifact(provider: ProviderForArtifacts, key: string, contents: string = '') {
  if ('version_code' in provider) {
    const [type, name] = Build.artifactType(key);
    if (type !== Build.Artifact.Unknown) {
      if (provider.artifact_files) {
        provider.artifact_files += ',' + name;
      } else {
        provider.artifact_files = name;
      }

      const code = (contents && parseInt(contents)) || NaN;

      if (type === Build.Artifact.VersionCode && !isNaN(code)) {
        provider.version_code = Number(contents);
      }
    }
  } else {
    const [type, name] = Release.artifactType(key);
    if (type !== Release.Artifact.Unknown) {
      if (provider.artifact_files) {
        provider.artifact_files += ',' + name;
      } else {
        provider.artifact_files = name;
      }
    }
  }
}
