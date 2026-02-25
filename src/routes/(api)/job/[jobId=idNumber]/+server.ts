import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';
import { stringLimits } from '$lib/valibot';

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

const updateSchema = v.strictObject({
  publisher_id: v.pipe(v.string(), v.maxBytes(stringLimits.job.publisher_id))
});

// PUT /job/[id]
export const PUT: RequestHandler = async ({ request, params }) => {
  const id = Number(params.jobId);
  const parsed = v.safeParse(updateSchema, await request.json());
  if (!parsed.success) return ErrorResponse(400, JSON.stringify(v.flatten(parsed.issues)));
  const job = await prisma.job.update({
    where: {
      id
    },
    data: { ...parsed.output },
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
