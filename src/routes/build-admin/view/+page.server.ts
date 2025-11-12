import { error } from '@sveltejs/kit';
import * as v from 'valibot';
import type { PageServerLoad } from './$types';
import { Build } from '$lib/models/build';
import { prisma } from '$lib/server/prisma';
import { idSchema, paramNumber } from '$lib/valibot';

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const build = await prisma.build.findUnique({
    where: {
      id: id.output
    }
  });

  if (!build) error(404);

  return {
    build,
    artifacts: build.artifact_files ? Build.artifacts(build) : {}
  };
}) satisfies PageServerLoad;
