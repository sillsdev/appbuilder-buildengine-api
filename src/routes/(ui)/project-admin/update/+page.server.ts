import { error, redirect } from '@sveltejs/kit';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { prisma } from '$lib/server/prisma';
import { convertEmptyStrToNull, idSchema, paramNumber, selectFrom } from '$lib/valibot';

const projectSchema = v.object({
  status: convertEmptyStrToNull(),
  result: convertEmptyStrToNull(),
  error: convertEmptyStrToNull(),
  url: v.pipe(convertEmptyStrToNull(), v.nullable(v.pipe(v.string(), v.url()))),
  user_id: convertEmptyStrToNull(),
  group_id: convertEmptyStrToNull(),
  app_id: convertEmptyStrToNull(),
  client_id: v.nullable(idSchema),
  project_name: convertEmptyStrToNull(),
  language_code: convertEmptyStrToNull(),
  publishing_key: convertEmptyStrToNull()
});

export const load = (async ({ url }) => {
  const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
  if (!id.success) {
    error(400, `missing id param`);
  }

  const project = await prisma.project.findUnique({
    where: {
      id: id.output
    },
    select: selectFrom(projectSchema.entries)
  });

  if (!project) error(404);

  return {
    form: await superValidate(project, valibot(projectSchema))
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  default: async function ({ request, url }) {
    const id = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('id'));
    if (!id.success) {
      error(400, `missing id param`);
    }
    const form = await superValidate(request, valibot(projectSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    let project = { id: 0 };

    try {
      project = await prisma.project.update({
        where: {
          id: id.output
        },
        data: form.data
      });
    } catch (e) {
      console.log(e);
      return error(400, e as Error);
    }

    redirect(303, `/project-admin/view?id=${project.id}`);
  }
};
