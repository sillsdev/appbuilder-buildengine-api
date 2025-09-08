import { prisma } from '$lib/server/prisma';
import type { PageServerLoad } from './$types';

export const load = (async () => {
  return { clients: await prisma.client.findMany() };
}) satisfies PageServerLoad;
