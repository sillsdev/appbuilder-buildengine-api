import type { Actions, PageServerLoad } from './$types';
import { returnTo, tryVerifyCookie } from '$lib/server/auth';
import { QueueConnected } from '$lib/server/bullmq';

export const load: PageServerLoad = async (event) => {
  return {
    serviceAvailable: QueueConnected()
  };
};
export const actions: Actions = {
  async login(event) {
    await tryVerifyCookie(event, false);
    throw returnTo(event);
  }
};
