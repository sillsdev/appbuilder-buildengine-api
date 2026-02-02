import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { tableSchema } from '$lib/valibot';

export const load = (async () => {
  const releases = await prisma.release.findMany({
    take: 20,
    orderBy: { id: 'desc' },
    include: { build: true }
  });
  return {
    releases,
    count: await prisma.release.count(),
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

    const releases = await prisma.release.findMany({
      orderBy: form.data.sort ? { [form.data.sort.field]: form.data.sort.direction } : undefined,
      skip: form.data.page.page * form.data.page.size,
      take: form.data.page.size
    });

    return {
      form,
      ok: true,
      query: {
        data: releases
      }
    };
  }
};
