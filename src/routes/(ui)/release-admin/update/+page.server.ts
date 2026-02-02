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

const releaseSchema = v.object({
  build_id: idSchema,
  status: convertEmptyStrToNull(),
  result: convertEmptyStrToNull(),
  error: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), v.url()))),
  channel: v.string(),
  title: convertEmptyStrToNull(),
  defaultLanguage: convertEmptyStrToNull(),
  build_guid: v.pipe(convertEmptyStrToNull(), v.nullable(stringIdSchema)),
  promote_from: convertEmptyStrToNull(),
  targets: convertEmptyStrToNull(),
  environment: convertEmptyStrToNull(),
  artifact_url_base: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), v.url()))),
  artifact_files: convertEmptyStrToNull()
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
      console.log(e);
      return error(400, e as Error);
    }

    redirect(303, `/release-admin/view?id=${release.id}`);
  }
};
