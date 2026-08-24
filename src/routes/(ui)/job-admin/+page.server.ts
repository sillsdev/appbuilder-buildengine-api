import type { Prisma } from '@prisma/client';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { applicationTypes, tableSchema } from '$lib/valibot';

const select = {
  id: true,
  request_id: true,
  app_id: true,
  git_url: true,
  client: {
    select: {
      id: true,
      prefix: true
    }
  }
} as const satisfies Prisma.jobSelect;

const searchSchema = v.object({
  appType: v.nullable(v.picklist(applicationTypes)),
  ...tableSchema.entries
});

export const load = (async () => {
  const jobs = await prisma.job.findMany({ select, take: 20, orderBy: { id: 'desc' } });
  return {
    jobs,
    count: await prisma.job.count(),
    form: await superValidate(
      {
        sort: { field: 'id', direction: 'desc' },
        page: {
          page: 0,
          size: 20
        }
      },
      valibot(searchSchema)
    )
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  page: async function ({ request }) {
    const form = await superValidate(request, valibot(searchSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    const where = {
      AND: [
        form.data.appType ? { app_id: form.data.appType } : {},
        form.data.search
          ? {
              OR: [
                { request_id: { contains: form.data.search, mode: 'insensitive' } },
                { client: { prefix: { contains: form.data.search, mode: 'insensitive' } } },
                { git_url: { contains: form.data.search, mode: 'insensitive' } }
              ]
            }
          : {}
      ]
    } as const satisfies Prisma.jobWhereInput;

    const jobs = await prisma.job.findMany({
      where,
      select,
      orderBy: form.data.sort ? { [form.data.sort.field]: form.data.sort.direction } : undefined,
      skip: form.data.page.page * form.data.page.size,
      take: form.data.page.size
    });

    return {
      form,
      ok: true,
      query: {
        data: jobs,
        count: await prisma.job.count({ where })
      }
    };
  }
};
