import type { RequestHandler } from './$types';
import { ErrorResponse } from '$lib/utils';

// GET /job/[id]/build/[id]
export const GET: RequestHandler = async () => {
  return ErrorResponse(405, 'DELETE /job/[id]/build/[id] is not supported at this time');
};

// PUT /job/[id]/build/[id]
export const PUT: RequestHandler = async () => {
  return ErrorResponse(405, 'PUT /job/[id]/build/[id] is not supported at this time', {
    Allow: 'GET'
  });
};

// DELETE /job/[id]/build/[id]
export const DELETE: RequestHandler = async () => {
  return ErrorResponse(405, 'DELETE /job/[id]/build/[id] is not supported at this time', {
    Allow: 'GET'
  });
};
