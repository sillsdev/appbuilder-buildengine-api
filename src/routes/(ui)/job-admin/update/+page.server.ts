import { SpanStatusCode, trace } from '@opentelemetry/api';
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
  stringIdSchema,
  stringLimits
} from '$lib/valibot';

const jobSchema = v.strictObject({
  request_id: stringIdSchema,
  git_url: v.pipe(v.string(), v.url(), v.maxBytes(stringLimits.job.git_url)),
  app_id: v.pipe(v.string(), v.maxBytes(stringLimits.job.app_id)),
  publisher_id: v.pipe(v.string(), v.maxBytes(stringLimits.job.publisher_id)),
  client_id: v.nullable(idSchema),
  existing_version_code: v.nullable(idSchema),
  jenkins_build_url: v.pipe(
    convertEmptyStrToNull(stringLimits.job.jenkins_build_url),
    v.nullable(v.pipe(v.string(), v.url()))
  ),
  jenkins_publish_url: v.pipe(
    convertEmptyStrToNull(stringLimits.job.jenkins_publish_url),
    v.nullable(v.pipe(v.string(), v.url()))
  )
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
      trace.getActiveSpan()?.recordException(e as Error);
      trace.getActiveSpan()?.setStatus({
        code: SpanStatusCode.ERROR,
        message: (e as Error).message
      });
      return error(400, (e as Error).message);
    }

    redirect(303, `/job-admin/view?id=${job.id}`);
  }
};
