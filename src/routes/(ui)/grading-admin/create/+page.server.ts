import { redirect } from '@sveltejs/kit';
import { fail, superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { BullMQ, getQueues } from '$lib/server/bullmq';
import { Grading } from '$lib/server/models/grading';
import { prisma } from '$lib/server/prisma';
import { idSchema, paramNumber, stringLimits } from '$lib/valibot';

const createSchema = v.object({
  project_id: idSchema,
  publisher_id: v.pipe(v.string(), v.maxBytes(stringLimits.grading.publisher_id))
});

export const load = (async ({ url }) => {
  const projectId = v.safeParse(v.pipe(paramNumber, idSchema), url.searchParams.get('project_id'));
  return {
    form: await superValidate(
      {
        project_id: projectId.success ? projectId.output : 0,
        publisher_id: ''
      },
      valibot(createSchema)
    )
  };
}) satisfies PageServerLoad;

export const actions: Actions = {
  default: async function ({ request }) {
    const form = await superValidate(request, valibot(createSchema));
    if (!form.valid) return fail(400, { form, ok: false });

    const project = await prisma.project.findUnique({
      where: { id: form.data.project_id },
      select: { id: true, url: true }
    });
    if (!project) return fail(404, { form, ok: false, message: 'Project not found' });
    if (!project.url?.startsWith('s3://')) {
      return fail(400, {
        form,
        ok: false,
        message: 'Project does not have an s3:// URL'
      });
    }

    const gradingResult = await prisma.gradingResult.create({
      data: {
        project_id: project.id,
        publisher_id: form.data.publisher_id,
        status: Grading.Status.Initialized
      }
    });

    await getQueues().Grading.add(
      `Generate Grading Report #${gradingResult.uuid} for Project ${project.id}`,
      {
        type: BullMQ.JobType.Grading_Generate,
        gradingResultUUID: gradingResult.uuid
      }
    );

    redirect(303, `/grading-admin/view?id=${gradingResult.uuid}`);
  }
};
