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

const releaseSchema = v.object({
  build_id: idSchema,
  status: convertEmptyStrToNull(stringLimits.release.status),
  result: convertEmptyStrToNull(stringLimits.release.result),
  error: v.pipe(
    convertEmptyStrToNull(stringLimits.release.error),
    v.nullable(v.pipe(v.string(), v.url()))
  ),
  channel: v.pipe(v.string(), v.maxBytes(stringLimits.release.channel)),
  title: convertEmptyStrToNull(stringLimits.release.title),
  defaultLanguage: convertEmptyStrToNull(stringLimits.release.defaultLanguage),
  build_guid: v.pipe(convertEmptyStrToNull(), v.nullable(stringIdSchema)),
  promote_from: convertEmptyStrToNull(stringLimits.release.promote_from),
  targets: convertEmptyStrToNull(stringLimits.release.targets),
  environment: convertEmptyStrToNull(),
  artifact_url_base: v.pipe(
    convertEmptyStrToNull(stringLimits.release.artifact_url_base),
    v.nullable(v.pipe(v.string(), v.url()))
  ),
  artifact_files: convertEmptyStrToNull(stringLimits.release.artifact_files)
});

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const release = await prisma.release.findUnique({
    where: {
      id: id.output
    },
    select: selectFrom(releaseSchema.entries)
  });

  if (!release) error(404);

  return {
    form: await superValidate(release, valibot(releaseSchema))
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  default: async function ({ request, url }) {
    const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
    if (!id.success) {
      error(400, `missing id param`);
    }
    const form = await superValidate(request, valibot(releaseSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    let release = { id: 0 };

    try {
      release = await prisma.release.update({
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

    redirect(303, `/release-admin/view?id=${release.id}`);
  }
};
