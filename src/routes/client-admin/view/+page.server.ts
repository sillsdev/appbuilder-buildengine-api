import { error } from '@sveltejs/kit';
import type { PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';

export const load = (async ({ url }) => {
  const idString = url.searchParams.get('id');
  const id = parseInt(idString ?? '');
  if (!idString || isNaN(id)) {
    error(400, `missing id param`);
  }

  const client = await prisma.client.findUnique({
    where: {
      id
    }
  });

  if (!client) error(404);

  return {
    client
  };
}) satisfies PageServerLoad;
