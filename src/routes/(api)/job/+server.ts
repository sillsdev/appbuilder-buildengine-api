import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';
import { stringIdSchema } from '$lib/valibot';

const jobSchema = v.strictObject({
  request_id: stringIdSchema,
  git_url: v.pipe(v.string(), v.url()),
  app_id: v.string(),
  publisher_id: v.string()
});

// POST /job
export const POST: RequestHandler = async ({ request, locals }) => {
  const parsed = v.safeParse(jobSchema, await request.json());
  if (!parsed.success) return ErrorResponse(400, JSON.stringify(v.flatten(parsed.issues)));
  const job = await prisma.job.create({
    data: { ...parsed.output, client_id: locals.clientId },
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

// GET /job
export const GET: RequestHandler = async () => {
  return ErrorResponse(405, 'GET /job is not supported at this time', { Allow: 'POST' });
};
