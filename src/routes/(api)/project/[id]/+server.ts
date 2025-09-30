import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

// GET /project/[id]
export const GET: RequestHandler = async ({ params }) => {
  const id = parseInt(params.id);
  const project = await prisma.project.findUnique({ where: { id } });
  if (isNaN(id) || !project) return ErrorResponse(404, 'Project not found');
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

// PUT /project/[id]
export const PUT: RequestHandler = async () => {
  return ErrorResponse(405, 'PUT /project/[id] is not supported at this time');
};

// DELETE /project/[id]
export const DELETE: RequestHandler = async () => {
  return ErrorResponse(405, 'DELETE /project/[id] is not supported at this time');
};
