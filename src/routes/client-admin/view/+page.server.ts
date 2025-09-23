import { error } from '@sveltejs/kit';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { idSchema, paramNumber } from '$lib/valibot';

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const client = await prisma.client.findUnique({
    where: {
      id: id.output
    }
  });

  if (!client) error(404);

  return {
    client
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  deleteClient: async function ({ request }) {
    const form = await superValidate(request, valibot(v.object({ id: idSchema })));
    if (!form.valid) return fail(400, { form, ok: false });

    await prisma.client.delete({ where: { id: form.data.id } });

    return { form, ok: true };
  }
};
