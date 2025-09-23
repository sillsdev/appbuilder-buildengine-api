import { redirect } from '@sveltejs/kit';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';

const clientSchema = v.object({
  accessToken: v.pipe(
    v.string(),
    v.transform((s) => s.trim()),
    v.minLength(1)
  ),
  prefix: v.pipe(
    v.string(),
    v.transform((s) => s.trim()),
    v.minLength(1)
  )
});

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
      data: {
        access_token: form.data.accessToken,
        prefix: form.data.prefix
      }
    });

    redirect(303, `/client-admin/view?id=${client.id}`);
  }
};
