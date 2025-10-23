import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';
import { releaseArtifacts } from '$lib/models/artifacts';

// GET /job/[id]/build/[id]/release/[id]
export const GET: RequestHandler = async ({ params }) => {
  const job = await prisma.job.findUnique({
    where: { id: Number(params.jobId) },
    select: {
      id: true,
      build: {
        where: { id: Number(params.buildId) },
        select: {
          id: true,
          release: {
            where: { id: Number(params.releaseId) },
            select: {
              id: true,
              build_id: true,
              status: true,
              result: true,
              error: true,
              title: true,
              defaultLanguage: true,
              channel: true,
              created: true,
              updated: true,
              artifact_url_base: true,
              console_text_url: true,
              artifact_files: true
            }
          }
        }
      }
    }
  });
  if (!job) return ErrorResponse(404, 'Job not found');
  const build = job.build.at(0);
  if (!build) return ErrorResponse(404, 'Build not found');
  const release = build.release.at(0);
  if (!release) return ErrorResponse(404, 'Release not found');

  return new Response(
    JSON.stringify({
      ...release,
      artifacts: releaseArtifacts(release),
      artifact_url_base: undefined,
      console_text_url: undefined,
      artifact_files: undefined,
      _links: {}
    })
  );
};

// DELETE /job/[id]/build/[id]/release/[id]
export const DELETE: RequestHandler = async () => {
  return ErrorResponse(
    405,
    'DELETE /job/[id]/build/[id]/release/[id] is not supported at this time',
    { Allow: 'GET' }
  );
};
