import type { RequestHandler } from './$types';
import { ErrorResponse } from '$lib/utils';

// GET /job/[id]
export const GET: RequestHandler = async () => {
  return ErrorResponse(405, 'DELETE /job/[id] is not supported at this time');
};

// PUT /job/[id]
export const PUT: RequestHandler = async () => {
  return ErrorResponse(405, 'PUT /job/[id] is not supported at this time');
};

// DELETE /job/[id]
export const DELETE: RequestHandler = async () => {
  return ErrorResponse(405, 'DELETE /job/[id] is not supported at this time');
};
