import type { Prisma } from '@prisma/client';
import { Build } from './build';
import { Release } from './release';

export function getArtifactUrl(
  pattern: RegExp,
  base: string | null,
  artifact_files: string | null
) {
  const filename = getArtifactFilename(pattern, artifact_files);
  if (filename) {
    return (base ?? '') + encodeURI(filename);
  }
  return undefined;
}
export function getArtifactFilename(pattern: RegExp, artifact_files: string | null) {
  return artifact_files?.split(',').find((f) => f.match(pattern)) ?? null;
}
export function getArtfactFilenameCount(pattern: RegExp, artifact_files: string | null) {
  return artifact_files?.split(',').filter((f) => f.match(pattern)).length ?? 0;
}
export function getArtifactUrls(
  pattern: RegExp,
  artifact_url_base: string | null,
  artifact_files: string | null
) {
  const urls = artifact_files
    ?.split(',')
    .filter((f) => f.match(pattern))
    .map((f) => (artifact_url_base ?? '') + encodeURI(f));
  return urls?.length ? urls : null;
}

export type BuildForPrefix = Prisma.buildGetPayload<{
  select: { id: true; job: { select: { id: true; app_id: true } } };
}>;

export type ReleaseForPrefix = Prisma.releaseGetPayload<{
  select: { id: true; build: { select: { job: { select: { id: true; app_id: true } } } } };
}>;

export type ProviderForPrefix = BuildForPrefix | ReleaseForPrefix;

export function getArtifactPath(
  job: Prisma.jobGetPayload<{ select: { app_id: true; id: true } }>,
  productionStage: string,
  isPublish = false
) {
  return `${productionStage}/jobs/${isPublish ? 'publish' : 'build'}_${job.app_id}_${job.id}`;
}

export function getBasePrefixUrl(artifacts_provider: ProviderForPrefix, productStage: string) {
  const artifactPath =
    'build' in artifacts_provider
      ? getArtifactPath(artifacts_provider.build.job, productStage, true)
      : getArtifactPath(artifacts_provider.job, productStage, true);
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
