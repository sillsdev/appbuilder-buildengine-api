import type { RequestHandler } from './$types';
import { Grading } from '$lib/server/models/grading';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

function origin() {
  return process.env.ORIGIN || 'http://localhost:8443';
}

// GET /project/[id]/grading/[gradingResultId]
export const GET: RequestHandler = async ({ params, locals }) => {
  const project = await prisma.project.findFirst({
    where: {
      id: Number(params.id),
      ...(locals.clientId ? { client_id: locals.clientId } : {})
    },
    select: { id: true }
  });
  if (!project) return ErrorResponse(404, 'Project not found');

  const grading = await prisma.gradingResult.findUnique({
    where: {
      id: Number(params.gradingResultId)
    }
  });
  if (!grading) return ErrorResponse(404, 'Grading result not found');

  return new Response(JSON.stringify(Grading.response(grading, origin())));
};
