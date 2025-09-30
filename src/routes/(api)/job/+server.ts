import type { RequestHandler } from './$types';
import { ErrorResponse } from '$lib/utils';

// POST /job
export const POST: RequestHandler = async () => {
  return ErrorResponse(405, 'POST /job is not supported at this time');
};

// GET /job
export const GET: RequestHandler = async () => {
  return ErrorResponse(405, 'GET /job is not supported at this time');
};
