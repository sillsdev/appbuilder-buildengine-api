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

  const project = await prisma.project.findUnique({
    where: {
      id: id.output
    },
    select: {
      id: true,
      project_name: true,
      created: true,
      status: true,
      result: true,
      language_code: true,
      app_id: true,
      url: true,
      client: { select: { id: true, prefix: true, development: true } },
      error: true
    }
  });

  if (!project) error(404);

  return {
    project
  };
}) satisfies PageServerLoad;
