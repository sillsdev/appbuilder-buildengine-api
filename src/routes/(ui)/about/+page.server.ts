import type { PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';

export const load = (async () => {
  return {
    appVersions: await prisma.appVersion.findMany()
  };
}) satisfies PageServerLoad;
