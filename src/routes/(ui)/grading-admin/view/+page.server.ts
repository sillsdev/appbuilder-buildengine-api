import { error } from '@sveltejs/kit';
import * as v from 'valibot';
import type { PageServerLoad } from './$types';
import { Grading } from '$lib/server/models/grading';
import { prisma } from '$lib/server/prisma';

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(v.string(), v.uuid()), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const gradingResult = await prisma.gradingResult.findFirst({
    where: {
      uuid: id.output
    },
    include: {
      project: true
    },
    orderBy: {
      created: 'desc'
    }
  });

  if (!gradingResult) error(404);

  return {
    rawGradingResult: gradingResult,
    gradingResult: Grading.response(gradingResult)
  };
}) satisfies PageServerLoad;
