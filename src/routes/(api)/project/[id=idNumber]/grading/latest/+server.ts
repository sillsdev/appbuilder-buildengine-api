import type { RequestHandler } from './$types';
import { Grading } from '$lib/models/grading';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

function origin() {
  return process.env.ORIGIN || 'http://localhost:8443';
}

// GET /project/[id]/grading/latest
export const GET: RequestHandler = async ({ params, locals }) => {
  const project = await prisma.project.findFirst({
    where: {
      id: Number(params.id),
      ...(locals.clientId ? { client_id: locals.clientId } : {})
    },
    select: { id: true }
  });
  if (!project) return ErrorResponse(404, 'Project not found');

  const grading = await prisma.gradingResult.findFirst({
    where: {
      project_id: project.id
    },
    orderBy: [{ created: 'desc' }, { id: 'desc' }]
  });
  if (!grading) return ErrorResponse(404, 'Grading result not found');

  return new Response(JSON.stringify(Grading.response(grading, origin())));
};
