import type { PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';

export const load = (async () => {
  return {
    aggregate: {
      project: await prisma.project.groupBy({ by: ['app_id'], _count: true }),
      job: await prisma.job.groupBy({ by: ['app_id'], _count: true }),
      build: await prisma.build.groupBy({ by: ['result'], _count: true }),
      release: await prisma.release.groupBy({ by: ['result'], _count: true })
    }
  };
}) satisfies PageServerLoad;
