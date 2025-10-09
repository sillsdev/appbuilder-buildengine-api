import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { STS } from '$lib/server/aws/sts';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

const sts = new STS();

// POST /project/[id]/token
export const POST: RequestHandler = async ({ request, params }) => {
  const body = v.safeParse(
    v.object({
      name: v.string(),
      ReadOnly: v.optional(v.boolean(), false)
    }),
    await request.json()
  );

  if (!body.success) return ErrorResponse(400, JSON.stringify(v.flatten(body.issues)));

  const project = await prisma.project.findUnique({ where: { id: parseInt(params.id) } });
  if (!project) return ErrorResponse(404, 'Project not found');
  if (!project.url?.startsWith('s3://')) {
    return ErrorResponse(400, 'Attempting to get token for wrong project type');
  }

  try {
    return new Response(
      JSON.stringify(
        await sts.getProjectAccessToken(project, body.output.name, body.output.ReadOnly)
      )
    );
  } catch (e) {
    return ErrorResponse(500, e instanceof Error ? e.message : String(e));
  }
};
