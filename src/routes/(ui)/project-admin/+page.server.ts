import type { Prisma } from '@prisma/client';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { applicationTypes, tableSchema } from '$lib/valibot';

const select: Prisma.projectSelect = {
  id: true,
  project_name: true,
  app_id: true,
  client: {
    select: {
      id: true,
      prefix: true
    }
  },
  url: true
};

const searchSchema = v.object({
  appType: v.nullable(v.picklist(applicationTypes)),
  ...tableSchema.entries
});

export const load = (async () => {
  const projects = await prisma.project.findMany({ select, take: 20, orderBy: { id: 'desc' } });
  return {
    projects,
    count: await prisma.project.count(),
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
                { project_name: { contains: form.data.search, mode: 'insensitive' } },
                { client: { prefix: { contains: form.data.search, mode: 'insensitive' } } },
                { url: { contains: form.data.search, mode: 'insensitive' } }
              ]
            }
          : {}
      ]
    } as const satisfies Prisma.projectWhereInput;

    const projects = await prisma.project.findMany({
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
        data: projects,
        count: await prisma.project.count({ where })
      }
    };
  }
};
