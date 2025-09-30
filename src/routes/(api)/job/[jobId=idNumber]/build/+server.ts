import type { RequestHandler } from './$types';
import { ErrorResponse } from '$lib/utils';

// POST /job/[id]/build
export const POST: RequestHandler = async () => {
  return ErrorResponse(405, 'POST /job/[id]/build is not supported at this time');
};

// GET /job/[id]/build
export const GET: RequestHandler = async () => {
  return ErrorResponse(405, 'GET /job/[id]/build is not supported at this time');
};
