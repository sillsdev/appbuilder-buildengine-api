import type { LayoutServerLoad } from './$types';

export const load = (async ({ locals }) => {
  return { userEmail: locals.userEmail };
}) satisfies LayoutServerLoad;
