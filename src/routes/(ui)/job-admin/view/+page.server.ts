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

  const job = await prisma.job.findUnique({
    where: {
      id: id.output
    },
    select: {
      id: true,
      request_id: true,
      git_url: true,
      app_id: true,
      publisher_id: true,
      client: { select: { id: true, prefix: true } },
      existing_version_code: true,
      created: true
    }
  });

  if (!job) error(404);

  return {
    job
  };
}) satisfies PageServerLoad;
