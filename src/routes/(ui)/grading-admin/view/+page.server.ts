import { error } from '@sveltejs/kit';
import * as v from 'valibot';
import type { PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { idSchema, paramNumber } from '$lib/valibot';

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const gradingResult = await prisma.gradingResult.findUnique({
    where: {
      id: id.output
    },
    include: {
      project: true
    }
  });

  if (!gradingResult) error(404);

  return {
    gradingResult
  };
}) satisfies PageServerLoad;
