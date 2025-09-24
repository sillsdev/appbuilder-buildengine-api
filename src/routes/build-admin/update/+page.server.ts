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

const buildSchema = v.object({
  job_id: idSchema,
  status: convertEmptyStrToNull(),
  build_guid: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), stringIdSchema))),
  result: convertEmptyStrToNull(),
  error: convertEmptyStrToNull(),
  artifact_url_base: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), v.url()))),
  artifact_files: convertEmptyStrToNull(),
  channel: convertEmptyStrToNull(),
  version_code: v.nullable(idSchema),
  targets: convertEmptyStrToNull(),
  environment: convertEmptyStrToNull()
});

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const build = await prisma.build.findUnique({
    where: {
      id: id.output
    },
    select: selectFrom(buildSchema.entries)
  });

  if (!build) error(404);

  return {
    form: await superValidate(build, valibot(buildSchema))
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  default: async function ({ request, url }) {
    const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
    if (!id.success) {
      error(400, `missing id param`);
    }
    const form = await superValidate(request, valibot(buildSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    let build = { id: 0 };

    try {
      build = await prisma.build.update({
        where: {
          id: id.output
        },
        data: form.data
      });
    } catch (e) {
      console.log(e);
      return error(400, e as Error);
    }

    redirect(303, `/build-admin/view?id=${build.id}`);
  }
};
