import type { RequestHandler } from './$types';
import { ErrorResponse } from '$lib/utils';

// POST /project/[id]/token
export const POST: RequestHandler = async () => {
  // TODO get access token
  return ErrorResponse(405, 'POST /project/[id]/token is not supported at this time');
};
