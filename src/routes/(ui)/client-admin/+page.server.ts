import type { Prisma } from '@prisma/client';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { tableSchema } from '$lib/valibot';

const select = {
  id: true,
  prefix: true,
  access_token: true,
  created: true,
  updated: true,
  development: true,
  description: true,
  _count: {
    select: {
      job: true,
      project: true
    }
  }
} as const satisfies Prisma.clientSelect;

export const load = (async () => {
  const clients = await prisma.client.findMany({ select, take: 20, orderBy: { id: 'desc' } });
  return {
    clients,
    count: await prisma.client.count(),
    form: await superValidate(
      {
        sort: { field: 'id', direction: 'desc' },
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

    const searchDev = form.data.search && 'development'.includes(form.data.search.toLowerCase());

    const where = {
      OR:
        searchDev || form.data.search
          ? [
              { development: searchDev || undefined },
              {
                prefix: form.data.search
                  ? { contains: form.data.search, mode: 'insensitive' }
                  : undefined
              },
              {
                description: form.data.search
                  ? { contains: form.data.search, mode: 'insensitive' }
                  : undefined
              }
            ]
          : undefined
    } as const satisfies Prisma.clientWhereInput;

    const clients = await prisma.client.findMany({
      select,
      where,
      orderBy: form.data.sort ? { [form.data.sort.field]: form.data.sort.direction } : undefined,
      skip: form.data.page.page * form.data.page.size,
      take: form.data.page.size
    });

    return {
      form,
      ok: true,
      query: {
        data: clients,
        count: await prisma.client.count({ where })
      }
    };
  }
};
