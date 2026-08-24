import type { Prisma } from '@prisma/client';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { tableSchema } from '$lib/valibot';

const select = {
  id: true,
  build: {
    select: {
      id: true,
      job_id: true,
      job: {
        select: {
          client: {
            select: {
              development: true
            }
          }
        }
      }
    }
  },
  status: true,
  result: true,
  codebuild_url: true
} as const satisfies Prisma.releaseSelect;

export const load = (async () => {
  const releases = await prisma.release.findMany({
    select,
    take: 20,
    orderBy: { id: 'desc' }
  });
  return {
    releases,
    count: await prisma.release.count(),
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

    const jobOrBuildId = parseInt(form.data.search);

    const where = {
      AND: [
        form.data.search
          ? {
              OR: [
                {
                  build: {
                    OR: [
                      { job: { request_id: { contains: form.data.search, mode: 'insensitive' } } },
                      { build_guid: { contains: form.data.search, mode: 'insensitive' } },
                      { job_id: isNaN(jobOrBuildId) ? undefined : jobOrBuildId }
                    ]
                  }
                },
                { build_id: isNaN(jobOrBuildId) ? undefined : jobOrBuildId },
                { build_guid: { contains: form.data.search, mode: 'insensitive' } },
                { result: { contains: form.data.search, mode: 'insensitive' } },
                { status: { contains: form.data.search, mode: 'insensitive' } }
              ]
            }
          : {}
      ]
    } as const satisfies Prisma.releaseWhereInput;

    const releases = await prisma.release.findMany({
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
        data: releases,
        count: await prisma.release.count({ where })
      }
    };
  }
};
