import type { Prisma } from '@prisma/client';
import { AWSCommon } from './aws/common';

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
