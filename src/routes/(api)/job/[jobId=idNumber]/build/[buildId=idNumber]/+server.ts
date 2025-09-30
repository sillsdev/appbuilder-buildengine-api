import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';
import { artifacts } from '$lib/utils/artifacts';

// GET /job/[id]/build/[id]
export const GET: RequestHandler = async ({ params }) => {
  const job = await prisma.job.findUnique({
    where: { id: Number(params.jobId) },
    select: {
      id: true,
      build: {
        where: {
          id: Number(params.buildId)
        },
        select: {
          id: true,
          job_id: true,
          status: true,
          result: true,
          error: true,
          targets: true,
          environment: true,
          created: true,
          updated: true,
          artifact_url_base: true,
          console_text_url: true,
          artifact_files: true
        }
      }
    }
  });
  if (!job) return ErrorResponse(404, 'Job not found');
  const build = job.build.at(0);
  if (!build) return ErrorResponse(404, 'Build not found');
  return new Response(
    JSON.stringify({
      ...build,
      artifacts: artifacts(build),
      client_id: undefined,
      artifact_url_base: undefined,
      console_text_url: undefined,
      artifact_files: undefined,
      environment: undefined,
      _links: {
        self: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/job/${build.job_id}/build/${build.id}`
        },
        job: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/job/${build.job_id}`
        }
      }
    })
  );
};

// PUT /job/[id]/build/[id]
export const PUT: RequestHandler = async () => {
  return ErrorResponse(405, 'PUT /job/[id]/build/[id] is not supported at this time', {
    Allow: 'GET'
  });
};

// DELETE /job/[id]/build/[id]
export const DELETE: RequestHandler = async () => {
  return ErrorResponse(405, 'DELETE /job/[id]/build/[id] is not supported at this time', {
    Allow: 'GET'
  });
};
