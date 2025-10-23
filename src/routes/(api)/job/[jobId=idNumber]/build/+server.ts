import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';
import { artifacts } from '$lib/models/artifacts';

const buildSchema = v.strictObject({
  targets: v.string(),
  environment: v.record(v.string(), v.string())
});

// POST /job/[id]/build
export const POST: RequestHandler = async ({ request, params }) => {
  const parsed = v.safeParse(buildSchema, await request.json());
  if (!parsed.success) return ErrorResponse(400, JSON.stringify(v.flatten(parsed.issues)));
  const job = await prisma.job.findUnique({
    where: { id: Number(params.jobId) },
    select: { id: true }
  });
  if (!job) return ErrorResponse(404, 'Job not found');
  const build = await prisma.build.create({
    data: {
      ...parsed.output,
      environment: JSON.stringify(parsed.output.environment),
      status: 'initialized',
      job_id: job.id
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
  });
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

// GET /job/[id]/build
export const GET: RequestHandler = async () => {
  return ErrorResponse(405, 'GET /job/[id]/build is not supported at this time', { Allow: 'POST' });
};
