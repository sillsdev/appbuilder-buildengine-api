import { redirect } from '@sveltejs/kit';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import { clientSchema } from '../valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';

export const load = (async () => {
  return {
    form: await superValidate(valibot(clientSchema))
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  default: async function ({ request }) {
    const form = await superValidate(request, valibot(clientSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    const client = await prisma.client.create({
      data: form.data
    });

    redirect(303, `/client-admin/view?id=${client.id}`);
  }
};
