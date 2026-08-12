import { error } from '@sveltejs/kit';
import * as v from 'valibot';
import type { PageServerLoad } from './$types';
import { Grading } from '$lib/server/models/grading';
import { prisma } from '$lib/server/prisma';
import { idSchema, paramNumber } from '$lib/valibot';

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const project = await prisma.project.findUnique({
    where: {
      id: id.output
    },
    include: {
      gradingResult: {
        take: 5,
        orderBy: {
          created: 'desc'
        }
      }
    }
  });

  if (!project) error(404);

  const projectToReturn = {
    ...project,
    gradingResult: project.gradingResult.map((r) => Grading.response(r))
  };
  return {
    project: projectToReturn
  };
}) satisfies PageServerLoad;
