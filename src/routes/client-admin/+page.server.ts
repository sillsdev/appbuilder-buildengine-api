import { prisma } from '$lib/server/prisma';
import { tableSchema } from '$lib/valibot';
import type { Actions, PageServerLoad } from './$types';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';

export const load = (async () => {
	const clients = await prisma.client.findMany({ take: 20 });
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

    const instances = await prisma.client.findMany({
      orderBy:
        form.data.sort?.field === 'id'
          ? { id: form.data.sort.direction }
        	: undefined,
      skip: form.data.page.page * form.data.page.size,
      take: form.data.page.size
    });

    return {
      form,
      ok: true,
      query: {
        data: instances
      }
    };
  }
};
