import type { RequestHandler } from './$types';

// GET system/check
export const GET: RequestHandler = () => {
  return new Response(JSON.stringify({}));
};
