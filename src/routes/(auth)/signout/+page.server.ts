import type { PageServerLoad } from './$types';
import { invalidateLogin } from '$lib/server/auth';

export const load: PageServerLoad = async (event) => {
  throw invalidateLogin(event);
};
