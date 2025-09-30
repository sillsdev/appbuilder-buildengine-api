import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

const projectSchema = v.strictObject({
  app_id: v.pipe(
    v.string(),
    v.picklist([
      'scriptureappbuilder',
      'dictionaryappbuilder',
      'readingappbuilder',
      'keyboardappbuilder'
    ])
  ),
  project_name: v.string(),
  language_code: v.string(),
  storage_type: v.literal('s3')
});

// POST project (create project)
export const POST: RequestHandler = async ({ request, locals }) => {
  const parsed = v.safeParse(projectSchema, await request.json());
  console.log(parsed.success);
  if (!parsed.success) {
    console.log(v.flatten(parsed.issues));
    const ret = ErrorResponse(400, JSON.stringify(v.flatten(parsed.issues)));
    console.log(ret.status);
    return ret;
  }
  // TODO enqueue project creation job
  const project = await prisma.project.create({
    data: { ...parsed.output, status: 'initialized', client_id: locals.clientId }
  });
  return new Response(
    JSON.stringify({
      ...project,
      client_id: undefined,
      _links: {
        self: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/project/${project.id}`
        }
      }
    })
  );
};

// GET /project
export const GET: RequestHandler = async () => {
  return ErrorResponse(405, 'GET /project is not supported at this time');
};
