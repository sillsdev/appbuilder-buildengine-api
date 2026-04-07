import { superValidate } from 'sveltekit-superforms';
import { valibot } from 'sveltekit-superforms/adapters';
import * as v from 'valibot';
import type { Actions, PageServerLoad } from './$types';
import { returnTo, tryVerifyCookie } from '$lib/server/auth';
import { QueueConnected } from '$lib/server/bullmq';

const loginSchema = v.object({
  returnTo: v.optional(v.string())
});

export const load: PageServerLoad = async (event) => {
  return {
    serviceAvailable: QueueConnected(),
    form: await superValidate(valibot(loginSchema))
  };
};
export const actions: Actions = {
  async login(event) {
    const form = await superValidate(event.request, valibot(loginSchema));
    if (form.valid && form.data.returnTo) {
      event.url.searchParams.set('returnTo', form.data.returnTo);
    }
    await tryVerifyCookie(event, false);
    throw returnTo(event);
  }
};
