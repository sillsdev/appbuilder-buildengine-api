import type { RequestHandler } from './$types';
import { ErrorResponse } from '$lib/utils';

// GET /job/[id]/build/[id]/release/[id]
export const GET: RequestHandler = async () => {
  return ErrorResponse(
    405,
    'DELETE /job/[id]/build/[id]/release/[id] is not supported at this time'
  );
};

// DELETE /job/[id]/build/[id]/release/[id]
export const DELETE: RequestHandler = async () => {
  return ErrorResponse(
    405,
    'DELETE /job/[id]/build/[id]/release/[id] is not supported at this time'
  );
};
