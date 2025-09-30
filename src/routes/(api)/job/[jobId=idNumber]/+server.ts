import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

// GET /job/[id]
export const GET: RequestHandler = async ({ params }) => {
  const id = Number(params.jobId);
  const job = await prisma.job.findUnique({
    where: { id },
    select: {
      id: true,
      request_id: true,
      git_url: true,
      app_id: true,
      publisher_id: true,
      created: true,
      updated: true
    }
  });
  if (!job) return ErrorResponse(404, 'Job not found');
  return new Response(
    JSON.stringify({
      ...job,
      client_id: undefined,
      _links: {
        self: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/job/${job.id}`
        }
      }
    })
  );
};

// PUT /job/[id]
export const PUT: RequestHandler = async () => {
  return ErrorResponse(405, 'PUT /job/[id] is not supported at this time', { Allow: 'GET' });
};

// DELETE /job/[id]
export const DELETE: RequestHandler = async ({ params }) => {
  const job_id = Number(params.jobId);
  await prisma.$transaction([
    prisma.release.deleteMany({ where: { build: { job_id } } }),
    prisma.build.deleteMany({ where: { job_id } }),
    prisma.job.deleteMany({ where: { id: job_id } })
  ]);
  return new Response(JSON.stringify({}));
};
