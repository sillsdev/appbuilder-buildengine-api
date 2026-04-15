import type { RequestHandler } from './$types';

// GET health
export const GET: RequestHandler = async () => {
  return new Response(null, { status: 200 });
};
