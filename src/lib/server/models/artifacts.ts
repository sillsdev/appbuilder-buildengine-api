import type { Prisma } from '@prisma/client';

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
      : getArtifactPath(artifacts_provider.job, productStage);
  return `${artifactPath}/${artifacts_provider.id}`;
}
