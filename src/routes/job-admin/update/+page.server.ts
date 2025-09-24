import { error, redirect } from '@sveltejs/kit';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import {
  convertEmptyStrToNull,
  idSchema,
  paramNumber,
  selectFrom,
  stringIdSchema
} from '$lib/valibot';

const jobSchema = v.strictObject({
  request_id: stringIdSchema,
  git_url: v.pipe(v.string(), v.url()),
  app_id: v.string(),
  publisher_id: v.string(),
  client_id: v.nullable(idSchema),
  existing_version_code: v.nullable(idSchema),
  jenkins_build_url: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), v.url()))),
  jenkins_publish_url: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), v.url())))
});

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const job = await prisma.job.findUnique({
    where: {
      id: id.output
    },
    select: selectFrom(jobSchema.entries)
  });

  if (!job) error(404);

  return {
    form: await superValidate(job, valibot(jobSchema))
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  default: async function ({ request, url }) {
    const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
    if (!id.success) {
      error(400, `missing id param`);
    }
    const form = await superValidate(request, valibot(jobSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    let job = { id: 0 };

    try {
      job = await prisma.job.update({
        where: {
          id: id.output
        },
        data: form.data
      });
    } catch (e) {
      console.log(e);
      return error(400, e as Error);
    }

    redirect(303, `/job-admin/view?id=${job.id}`);
  }
};
