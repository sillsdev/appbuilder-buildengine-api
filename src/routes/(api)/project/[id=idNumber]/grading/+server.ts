import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { BullMQ, getQueues } from '$lib/server/bullmq';
import { Grading } from '$lib/server/models/grading';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';
import { stringLimits } from '$lib/valibot';

const gradingSchema = v.strictObject({
  publisher_id: v.pipe(v.string(), v.maxBytes(stringLimits.grading.publisher_id))
});

type ProjectForGrading = {
  id: number;
  url: string | null;
};

async function findProject(id: number, clientId: number | null) {
  return await prisma.project.findFirst({
    where: {
      id,
      ...(clientId ? { client_id: clientId } : {})
    },
    select: {
      id: true,
      url: true
    }
  });
}

function origin() {
  return process.env.ORIGIN || 'http://localhost:8443';
}

// POST /project/[id]/grading
export const POST: RequestHandler = async ({ request, params, locals }) => {
  const parsed = v.safeParse(gradingSchema, await request.json());
  if (!parsed.success) return ErrorResponse(400, JSON.stringify(v.flatten(parsed.issues)));

  const project = (await findProject(
    Number(params.id),
    locals.clientId
  )) as ProjectForGrading | null;
  if (!project) return ErrorResponse(404, 'Project not found');
  if (!project.url?.startsWith('s3://')) {
    return ErrorResponse(400, 'Project does not have an s3:// URL');
  }

  const grading = await prisma.gradingResult.create({
    data: {
      project_id: project.id,
      status: Grading.Status.Initialized,
      publisher_id: parsed.output.publisher_id
    }
  });
  if (!grading) return ErrorResponse(500, 'Unable to create grading result');

  await getQueues().Grading.add(
    `Generate Grading Report #${grading.uuid} for Project ${project.id}`,
    {
      type: BullMQ.JobType.Grading_Generate,
      gradingResultUUID: grading.uuid
    }
  );

  return new Response(
    JSON.stringify(
      Grading.response(grading, origin(), {
        list: {
          href: `${origin()}/project/${project.id}/grading`
        }
      })
    ),
    { status: 202 }
  );
};

// GET /project/[id]/grading
export const GET: RequestHandler = async ({ params, locals, url }) => {
  const project = await findProject(Number(params.id), locals.clientId);
  if (!project) return ErrorResponse(404, 'Project not found');

  const rawLimit = Number(url.searchParams.get('limit') ?? 20);
  const limit = Math.max(1, Math.min(Number.isFinite(rawLimit) ? rawLimit : 20, 100));
  const rows = await prisma.gradingResult.findMany({
    where: {
      project_id: project.id
    },
    orderBy: [{ created: 'desc' }],
    take: limit
  });

  return new Response(
    JSON.stringify({
      gradingResults: rows.map((row) => Grading.response(row, origin())),
      _links: {
        self: {
          href: `${origin()}/project/${project.id}/grading`
        },
        project: {
          href: `${origin()}/project/${project.id}`
        },
        latest: {
          href: `${origin()}/project/${project.id}/grading/latest`
        }
      }
    })
  );
};
