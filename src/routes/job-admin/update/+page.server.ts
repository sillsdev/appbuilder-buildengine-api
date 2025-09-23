import { error, redirect } from '@sveltejs/kit';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { convertEmptyStrToNull, idSchema, paramNumber, stringIdSchema } from '$lib/valibot';

const jobSchema = v.object({
  requestId: stringIdSchema,
  gitUrl: v.pipe(v.string(), v.url()),
  appId: v.string(),
  publisherId: v.string(),
  clientId: v.nullable(idSchema),
  existingVersion: v.nullable(idSchema),
  jenkinsBuildUrl: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), v.url()))),
  jenkinsPublishUrl: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), v.url())))
});

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const job = await prisma.job.findUnique({
    where: {
      id: id.output
    }
  });

  if (!job) error(404);

  return {
    form: await superValidate(
      {
        requestId: job.request_id,
        gitUrl: job.git_url,
        appId: job.app_id,
        publisherId: job.publisher_id,
        clientId: job.client_id,
        existingVersion: job.existing_version_code,
        jenkinsBuildUrl: job.jenkins_build_url,
        jenkinsPublishUrl: job.jenkins_publish_url
      },
      valibot(jobSchema)
    )
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

    const job = await prisma.job.update({
      where: {
        id: id.output
      },
      data: {
        request_id: form.data.requestId,
        git_url: form.data.gitUrl,
        app_id: form.data.appId,
        publisher_id: form.data.publisherId,
        client_id: form.data.clientId,
        existing_version_code: form.data.existingVersion,
        jenkins_build_url: form.data.jenkinsBuildUrl,
        jenkins_publish_url: form.data.jenkinsPublishUrl
      }
    });

    redirect(303, `/job-admin/view?id=${job.id}`);
  }
};
