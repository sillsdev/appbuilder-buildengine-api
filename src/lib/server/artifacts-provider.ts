import type { Prisma } from '@prisma/client';
import { AWSCommon } from './aws/common';
import { Build } from './models/build';
import { Release } from './models/release';

export type BuildForPrefix = Prisma.buildGetPayload<{
  select: { id: true; job: { select: { id: true; app_id: true } } };
}>;

export type ReleaseForPrefix = Prisma.releaseGetPayload<{
  select: { id: true; build: { select: { job: { select: { id: true; app_id: true } } } } };
}>;

export type ProviderForPrefix = BuildForPrefix | ReleaseForPrefix;

export function getBasePrefixUrl(artifacts_provider: ProviderForPrefix, productStage: string) {
  const artifactPath =
    'build' in artifacts_provider
      ? AWSCommon.getArtifactPath(artifacts_provider.build.job, productStage, true)
      : AWSCommon.getArtifactPath(artifacts_provider.job, productStage, true);
  return `${artifactPath}/${artifacts_provider.id}`;
}

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

      if (type === Build.Artifact.VersionCode) {
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
