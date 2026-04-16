import type { LayoutServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';

export const load = (async ({ locals }) => {
  return {
    userEmail: locals.userEmail,
    count: {
      client: await prisma.client.count(),
      project: await prisma.project.count(),
      job: await prisma.job.count(),
      build: await prisma.build.count(),
      release: await prisma.release.count()
    }
  };
}) satisfies LayoutServerLoad;
