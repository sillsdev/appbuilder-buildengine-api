import type { PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';

export const load = (async () => {
  return {
    aggregate: {
      project: await prisma.project.groupBy({
        by: ['app_id'],
        _count: true,
        orderBy: { _count: { app_id: 'desc' } }
      }),
      job: await prisma.job.groupBy({
        by: ['app_id'],
        _count: true,
        orderBy: { _count: { app_id: 'desc' } }
      }),
      build: await prisma.build.groupBy({
        by: ['result'],
        _count: true,
        orderBy: { _count: { result: 'desc' } }
      }),
      release: await prisma.release.groupBy({
        by: ['result'],
        _count: true,
        orderBy: { _count: { result: 'desc' } }
      })
    }
  };
}) satisfies PageServerLoad;
