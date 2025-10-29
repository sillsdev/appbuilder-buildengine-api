import type { Prisma } from '@prisma/client';
import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { AWSCommon } from '$lib/server/aws/common';
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
  if (!parsed.success) return ErrorResponse(400, JSON.stringify(v.flatten(parsed.issues)));
  // TODO enqueue project creation job
  const withoutStorage = { ...parsed.output, storage_type: undefined };
  const project = await prisma.project.create({
    data: {
      ...withoutStorage,
      status: 'completed',
      result: 'SUCCESS',
      client_id: locals.clientId
    },
    include: {
      client: true
    }
  });
  const url = `s3://${AWSCommon.getProjectsBucket()}/${getS3Folder(project)}`;
  await prisma.project.update({ where: { id: project.id }, data: { url } });
  return new Response(
    JSON.stringify({
      ...project,
      url,
      client_id: undefined,
      client: undefined,
      _links: {
        self: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/project/${project.id}`
        }
      }
    })
  );
};
// TODO create bucket???
function getS3Folder(
  project: Prisma.projectGetPayload<{
    select: {
      client_id: true;
      app_id: true;
      language_code: true;
      id: true;
      project_name: true;
      client: { select: { prefix: true } };
    };
  }>
) {
  const s3client = project.client ? project.client.prefix + '/' : '';
  const s3folder = `${project.language_code}-${project.id}-${project.project_name}`
    .replace(' ', '-')
    .replace(/[^a-zA-Z0-9-]/, '');
  return `${s3client}${project.app_id}/${s3folder}`;
}

// GET /project
export const GET: RequestHandler = async () => {
  return ErrorResponse(405, 'GET /project is not supported at this time', { Allow: 'POST' });
};
