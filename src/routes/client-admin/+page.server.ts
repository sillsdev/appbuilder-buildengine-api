import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { idSchema, tableSchema } from '$lib/valibot';

export const load = (async () => {
  const clients = await prisma.client.findMany({ take: 20, orderBy: { id: 'desc' } });
  return {
    clients,
    count: await prisma.client.count(),
    form: await superValidate(
      {
        page: {
          page: 0,
          size: 20
        }
      },
      valibot(tableSchema)
    )
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  page: async function ({ request }) {
    const form = await superValidate(request, valibot(tableSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    const clients = await prisma.client.findMany({
      orderBy: form.data.sort ? { [form.data.sort.field]: form.data.sort.direction } : undefined,
      skip: form.data.page.page * form.data.page.size,
      take: form.data.page.size
    });

    return {
      form,
      ok: true,
      query: {
        data: clients
      }
    };
  },
  deleteClient: async function ({ request }) {
    const form = await superValidate(request, valibot(v.object({ id: idSchema })));
    if (!form.valid) return fail(400, { form, ok: false });

    await prisma.client.delete({ where: { id: form.data.id } });

    return { form, ok: true };
  }
};
