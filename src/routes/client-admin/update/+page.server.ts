import { error, redirect } from '@sveltejs/kit';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { idSchema, paramNumber } from '$lib/valibot';

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
    form: await superValidate(
      { accessToken: client.access_token, prefix: client.prefix },
      valibot(clientSchema)
    )
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  default: async function ({ request, url }) {
    const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
    if (!id.success) {
      error(400, `missing id param`);
    }
    const form = await superValidate(request, valibot(clientSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    const client = await prisma.client.update({
      where: {
        id: id.output
      },
      data: {
        access_token: form.data.accessToken,
        prefix: form.data.prefix
      }
    });

    redirect(303, `/client-admin/view?id=${client.id}`);
  }
};
